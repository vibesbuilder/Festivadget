<?php
// Zeitgesteuerter Versand (per Cronjob, z. B. stündlich): sendet einen Digest
// „läuft in der nächsten Stunde" für anstehende Slots, die noch nicht gepusht
// wurden. Idempotent über push_log (ref = "slot:<id>").
//
// Aufruf:
//   CLI : php push/cron-send.php
//   HTTP: https://app.example.at/push/cron-send.php?key=<cronSecret>  (externer Pinger)

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sender.php';

$cfg    = push_config();
$isCli  = PHP_SAPI === 'cli';

// HTTP-Aufrufe brauchen das Geheimnis (gegen Missbrauch).
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $secret = (string) ($cfg['cronSecret'] ?? '');
    if ($secret === '' || ($_GET['key'] ?? '') !== $secret) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Kein/falscher key.']);
        exit;
    }
}

$dataDir = $cfg['dataDir'];

function load_json(string $file): array
{
    return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
}

$slots   = load_json("$dataDir/slots.json");
$artists = load_json("$dataDir/artists.json");
$stages  = load_json("$dataDir/stages.json");
// News-Quelle: admin-news.json (Admin-UI) ersetzt den Build-Stand, sobald vorhanden.
$news    = is_file("$dataDir/admin-news.json")
    ? load_json("$dataDir/admin-news.json")
    : load_json("$dataDir/news.json");

// Admin-Schalter (app-config.json) haben Vorrang vor config.php.
$appCfg          = load_json("$dataDir/app-config.json");
$autoPushUpcoming = $appCfg['autoPushUpcoming'] ?? ($cfg['autoPushUpcoming'] ?? false);
$autoPushNews     = $appCfg['autoPushNews']     ?? ($cfg['autoPushNews']     ?? false);

$artistById = [];
foreach ($artists as $a) {
    $artistById[$a['id']] = $a['name'] ?? $a['id'];
}
$stageById = [];
foreach ($stages as $s) {
    $stageById[$s['id']] = $s['name'] ?? $s['id'];
}

$tz       = new DateTimeZone('Europe/Vienna');
$now      = new DateTimeImmutable('now', $tz);
// Vorlaufzeit des „Gleich live"-Digests (Minuten). An die Cron-Frequenz anpassen:
// bei häufigem Cron (z. B. alle 15 Min) kleiner wählen, sonst kommt der Push zu früh.
$winMin    = max(1, (int) ($appCfg['upcomingWindowMin'] ?? ($cfg['upcomingWindowMin'] ?? 60)));
$windowEnd = $now->add(new DateInterval('PT' . $winMin . 'M'));

$pdo  = push_db();
$seen = $pdo->query('SELECT ref FROM push_log')->fetchAll(PDO::FETCH_COLUMN);
$seen = array_flip($seen);
$ins  = $pdo->prepare('INSERT IGNORE INTO push_log (ref) VALUES (?)');

$result = ['ok' => true, 'upcoming' => 0, 'plan' => 0, 'news' => 0, 'sent' => 0];

// --- (a) Anstehende Slots: allgemeiner Digest + persönliche „Mein Plan"-Erinnerungen ---
if ($autoPushUpcoming) {
    // Alle Slots, die im Fenster starten (Basis für Digest UND Plan-Erinnerungen).
    $upcoming = []; // slotId => ['name','stage','time','label']
    foreach ($slots as $slot) {
        if (!empty($slot['cancelled'])) {
            continue;
        }
        $id = (string) ($slot['id'] ?? '');
        if ($id === '') {
            continue;
        }
        try {
            $start = new DateTimeImmutable($slot['start']);
        } catch (Throwable $e) {
            continue;
        }
        if ($start < $now || $start > $windowEnd) {
            continue;
        }
        $name  = $artistById[$slot['artistId']] ?? $slot['artistId'];
        $stage = $stageById[$slot['stageId']] ?? $slot['stageId'];
        $time  = $start->setTimezone($tz)->format('H:i');
        $upcoming[$id] = [
            'name'  => $name,
            'stage' => $stage,
            'time'  => $time,
            'label' => sprintf('%s (%s, %s)', $name, $stage, $time),
        ];
    }

    // (a1) „Gleich live"-Digest (= Line-Up) an Abos OHNE „Mein Plan", die Line-Up
    //      abonniert haben (categories NULL = alle, oder enthält 'lineup'). Voller
    //      Umfang; jeder Slot für diese Gruppe nur einmal (ref = digest:<id>).
    $digest = [];
    foreach ($upcoming as $id => $u) {
        $ref = 'digest:' . $id;
        if (!isset($seen[$ref])) {
            $digest[$ref] = $u['label'];
        }
    }
    if ($digest) {
        $rows = $pdo->query(
            "SELECT endpoint, p256dh, auth FROM push_subscriptions
             WHERE (plan IS NULL OR plan = '')
               AND (categories IS NULL OR FIND_IN_SET('lineup', categories))"
        )->fetchAll();
        $report = push_send_rows($rows, [
            'title' => 'Gleich live',
            'body'  => implode("\n", array_values($digest)),
            'url'   => '/timetable',
            'tag'   => 'upcoming',
        ]);
        foreach (array_keys($digest) as $ref) {
            $ins->execute([$ref]);
            $seen[$ref] = true;
        }
        $result['upcoming'] = count($digest);
        $result['sent']    += $report['sent'];
    }

    // (a2) „Mein Plan"-Abonnenten: persönliche Erinnerung je favorisiertem Act +
    //      personalisierter Digest der NICHT-favorisierten Acts (nur wenn sie Line-Up
    //      abonniert haben). Dedup pro Gerät+Slot (ref = up:<hash>:<slotId>) – jeder
    //      Slot pro Gerät nur einmal, egal über welchen Kanal.
    $planRows = $pdo->query(
        "SELECT endpoint, p256dh, auth, plan, categories FROM push_subscriptions
         WHERE plan IS NOT NULL AND plan <> ''"
    )->fetchAll();
    foreach ($planRows as $row) {
        $hash        = substr(md5((string) $row['endpoint']), 0, 16);
        $favIds      = array_flip(array_filter(explode(',', (string) $row['plan'])));
        $wantsLineup = $row['categories'] === null
            || in_array('lineup', explode(',', (string) $row['categories']), true);
        $digestItems = [];
        $digestRefs  = [];
        foreach ($upcoming as $id => $u) {
            $ref = 'up:' . $hash . ':' . $id;
            if (isset($seen[$ref])) {
                continue;
            }
            if (isset($favIds[$id])) {
                // favorisiert → persönliche Einzel-Push
                $r = push_send_rows([$row], [
                    'title' => 'Gleich: ' . $u['name'],
                    'body'  => $u['stage'] . ' · ' . $u['time'],
                    'url'   => '/timetable',
                    'tag'   => 'plan-' . $id,
                ]);
                $ins->execute([$ref]);
                $seen[$ref] = true;
                $result['plan'] += $r['sent'];
                $result['sent'] += $r['sent'];
            } elseif ($wantsLineup) {
                // nicht favorisiert → in den persönlichen „Gleich live"-Digest
                $digestItems[] = $u['label'];
                $digestRefs[]  = $ref;
            }
        }
        if ($digestItems) {
            $r = push_send_rows([$row], [
                'title' => 'Gleich live',
                'body'  => implode("\n", $digestItems),
                'url'   => '/timetable',
                'tag'   => 'upcoming',
            ]);
            foreach ($digestRefs as $ref) {
                $ins->execute([$ref]);
                $seen[$ref] = true;
            }
            $result['sent'] += $r['sent'];
        }
    }
}

// --- (b) Neu veröffentlichte News pushen (safety/pinned) ----------------
if ($autoPushNews) {
    // Welche Kategorien automatisch pushen: Admin-Override (app-config.json) >
    // config.php > Default. Safety ist immer dabei (kommt bei jedem an).
    $categories = $appCfg['pushNewsCategories'] ?? ($cfg['pushNewsCategories'] ?? ['safety']);
    if (!in_array('safety', $categories, true)) {
        $categories[] = 'safety';
    }
    foreach ($news as $item) {
        $ref = 'news:' . ($item['id'] ?? '');
        if ($ref === 'news:' || isset($seen[$ref])) {
            continue;
        }
        // Nur relevante News (gewählte Kategorien ODER angepinnt).
        $relevant = in_array($item['category'] ?? '', $categories, true) || !empty($item['pinned']);
        if (!$relevant) {
            continue;
        }
        // publishAt erreicht und (falls gesetzt) noch nicht abgelaufen?
        try {
            $publish = new DateTimeImmutable($item['publishAt']);
        } catch (Throwable $e) {
            continue;
        }
        if ($publish > $now) {
            continue;
        }
        if (!empty($item['expiresAt'])) {
            try {
                if (new DateTimeImmutable($item['expiresAt']) <= $now) {
                    continue;
                }
            } catch (Throwable $e) {
                // ungültiges Datum ignorieren
            }
        }

        $body = (string) ($item['body'] ?? '');
        if (mb_strlen($body) > 180) {
            $body = mb_substr($body, 0, 177) . '…';
        }
        $report = push_send_news([
            'title' => (string) ($item['title'] ?? 'Neuigkeit'),
            'body'  => $body,
            'url'   => '/news',
            'tag'   => 'news',
        ], (string) ($item['category'] ?? 'general'));
        $ins->execute([$ref]);
        $result['news']++;
        $result['sent'] += $report['sent'];
    }
}

// --- (c) Anonyme Abo-Statistik fortschreiben (höchstens ~stündlich) ------
try {
    $result['statsLogged'] = push_stats_snapshot();
} catch (Throwable $e) {
    $result['statsLogged'] = false;
}

if ($isCli) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
} else {
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
