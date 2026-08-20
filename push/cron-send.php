<?php
// Scheduled sending (via cron job, e.g. hourly): sends a "live within the next
// hour" digest for upcoming slots that have not been pushed yet.
// Idempotent via push_log (ref = "slot:<id>").
//
// Usage:
//   CLI : php push/cron-send.php
//   HTTP: https://app.example.at/push/cron-send.php?key=<cronSecret>  (external pinger)

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sender.php';
require_once __DIR__ . '/texts.php';

$cfg    = push_config();
$isCli  = PHP_SAPI === 'cli';

// HTTP calls need the secret (against abuse).
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $secret = (string) ($cfg['cronSecret'] ?? '');
    if ($secret === '' || ($_GET['key'] ?? '') !== $secret) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Missing or invalid key.']);
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
// News source: admin-news.json (admin UI) replaces the build state once present.
$news    = is_file("$dataDir/admin-news.json")
    ? load_json("$dataDir/admin-news.json")
    : load_json("$dataDir/news.json");

// Admin switches (app-config.json) take precedence over config.php.
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
// Lead time of the "live soon" digest (minutes). Adapt to the cron frequency:
// choose smaller for a frequent cron (e.g. every 15 min), else the push arrives too early.
$winMin    = max(1, (int) ($appCfg['upcomingWindowMin'] ?? ($cfg['upcomingWindowMin'] ?? 60)));
$windowEnd = $now->add(new DateInterval('PT' . $winMin . 'M'));

$pdo  = push_db();
$seen = $pdo->query('SELECT ref FROM push_log')->fetchAll(PDO::FETCH_COLUMN);
$seen = array_flip($seen);
$ins  = $pdo->prepare('INSERT IGNORE INTO push_log (ref) VALUES (?)');

$result = ['ok' => true, 'upcoming' => 0, 'plan' => 0, 'news' => 0, 'sent' => 0];

// --- (a) Upcoming slots: general digest + personal "My plan" reminders ---
if ($autoPushUpcoming) {
    // All slots starting within the window (basis for the digest AND plan reminders).
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

    // (a1) "Live soon" digest (= line-up) to subscriptions WITHOUT "My plan" that
    //      subscribed to line-up (categories NULL = all, or contains 'lineup'). Full
    //      scope; every slot only once for this group (ref = digest:<id>).
    $digest = [];
    foreach ($upcoming as $id => $u) {
        $ref = 'digest:' . $id;
        if (!isset($seen[$ref])) {
            $digest[$ref] = $u['label'];
        }
    }
    if ($digest) {
        $rows = $pdo->query(
            "SELECT endpoint, p256dh, auth, lang FROM push_subscriptions
             WHERE (plan IS NULL OR plan = '')
               AND (categories IS NULL OR FIND_IN_SET('lineup', categories))"
        )->fetchAll();
        foreach (push_rows_by_lang($rows) as $lang => $group) {
            $report = push_send_rows($group, [
                'title' => push_tr($lang, 'Gleich live'),
                'body'  => implode("\n", array_values($digest)),
                'url'   => '/timetable',
                'tag'   => 'upcoming',
            ]);
            $result['sent'] += $report['sent'];
        }
        foreach (array_keys($digest) as $ref) {
            $ins->execute([$ref]);
            $seen[$ref] = true;
        }
        $result['upcoming'] = count($digest);
    }

    // (a2) "My plan" subscribers: personal reminder per favorited act +
    //      personalized digest of the NON-favorited acts (only when they subscribed
    //      to line-up). Dedup per device+slot (ref = up:<hash>:<slotId>) - every
    //      slot only once per device, regardless of channel.
    $planRows = $pdo->query(
        "SELECT endpoint, p256dh, auth, plan, categories, lang FROM push_subscriptions
         WHERE plan IS NOT NULL AND plan <> ''"
    )->fetchAll();
    foreach ($planRows as $row) {
        $rowLang     = in_array((string) ($row['lang'] ?? ''), PUSH_LANGS, true)
            ? (string) $row['lang']
            : push_default_lang();
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
                // favorited -> personal individual push
                $r = push_send_rows([$row], [
                    'title' => push_tr($rowLang, 'Gleich: {name}', ['name' => $u['name']]),
                    'body'  => $u['stage'] . ' · ' . $u['time'],
                    'url'   => '/timetable',
                    'tag'   => 'plan-' . $id,
                ]);
                $ins->execute([$ref]);
                $seen[$ref] = true;
                $result['plan'] += $r['sent'];
                $result['sent'] += $r['sent'];
            } elseif ($wantsLineup) {
                // not favorited -> into the personal "live soon" digest
                $digestItems[] = $u['label'];
                $digestRefs[]  = $ref;
            }
        }
        if ($digestItems) {
            $r = push_send_rows([$row], [
                'title' => push_tr($rowLang, 'Gleich live'),
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

// --- (b) Push newly published news (safety/pinned) -----------------------
if ($autoPushNews) {
    // Which categories to auto-push: admin override (app-config.json) >
    // config.php > default. Safety is always included (reaches everyone).
    $categories = $appCfg['pushNewsCategories'] ?? ($cfg['pushNewsCategories'] ?? ['safety']);
    if (!in_array('safety', $categories, true)) {
        $categories[] = 'safety';
    }
    foreach ($news as $item) {
        $ref = 'news:' . ($item['id'] ?? '');
        if ($ref === 'news:' || isset($seen[$ref])) {
            continue;
        }
        // Only relevant news (chosen categories OR pinned).
        $relevant = in_array($item['category'] ?? '', $categories, true) || !empty($item['pinned']);
        if (!$relevant) {
            continue;
        }
        // publishAt reached and (if set) not expired yet?
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
                // ignore invalid dates
            }
        }

        // title/body may be language maps; resolution + truncation per subscription
        // language is handled by push_send_news.
        $report = push_send_news([
            'title' => $item['title'] ?? '',
            'body'  => $item['body'] ?? '',
            'url'   => '/news',
            'tag'   => 'news',
        ], (string) ($item['category'] ?? 'general'));
        $ins->execute([$ref]);
        $result['news']++;
        $result['sent'] += $report['sent'];
    }
}

// --- (c) Advance the anonymous subscription statistics (at most ~hourly) --
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
