<?php
// Telegram-Webhook für Live-News (unmoderiert, nur erlaubte Absender).
// Schreibt freigegebene Nachrichten direkt in data/live-news.json – die App
// liest diese Datei zusätzlich zur (lokal gebauten) news.json und zeigt sie
// innerhalb von ~2 Minuten an. Der lokale Build fasst live-news.json nie an.
//
// Einrichtung: siehe docs/TELEGRAM.md.

declare(strict_types=1);

require_once __DIR__ . '/db.php'; // nur für push_config()

$cfg = push_config();
$tg = $cfg['telegram'] ?? [];

header('Content-Type: application/json; charset=utf-8');

// 1) Webhook-Secret prüfen (Telegram sendet es im Header, in setWebhook gesetzt).
$secret = $tg['webhookSecret'] ?? '';
$sentSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($secret === '' || !hash_equals($secret, $sentSecret)) {
    require_once __DIR__ . '/log.php';
    app_log('warn', 'telegram', 'Webhook-Aufruf mit falschem/fehlendem Secret abgewiesen.');
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

// 2) Update einlesen.
$update = json_decode(file_get_contents('php://input') ?: '', true);
$msg = $update['message'] ?? $update['edited_message'] ?? $update['channel_post'] ?? null;
if (!is_array($msg)) {
    echo json_encode(['ok' => true]); // nichts zu tun
    exit;
}

$fromId = (int) ($msg['from']['id'] ?? $msg['sender_chat']['id'] ?? 0);
$chatId = (int) ($msg['chat']['id'] ?? 0);
$text = trim((string) ($msg['text'] ?? $msg['caption'] ?? ''));

// Hilfsbefehl: eigene IDs anzeigen (auch ohne Freigabe – verrät nur die eigenen IDs).
// Praktisch, um die Gruppen-ID herauszufinden: Bot in die Gruppe holen, /chatid senden.
if ($text === '/chatid' || $text === '/id') {
    tg_reply($tg, $chatId, "User-ID: {$fromId}\nChat-ID: {$chatId}");
    echo json_encode(['ok' => true]);
    exit;
}

// 3) Allowlist: erlaubter Benutzer ODER erlaubte Gruppe/Chat.
$allowedUsers = array_map('intval', $tg['allowedUserIds'] ?? []);
$allowedChats = array_map('intval', $tg['allowedChatIds'] ?? []);
if (!in_array($fromId, $allowedUsers, true) && !in_array($chatId, $allowedChats, true)) {
    echo json_encode(['ok' => true]); // still ignorieren
    exit;
}

if ($text === '') {
    echo json_encode(['ok' => true]);
    exit;
}

$tz = new DateTimeZone($tg['tz'] ?? 'Europe/Vienna');
$file = $tg['liveNewsFile'];

// --- Befehle ------------------------------------------------------------
if ($text === '/clear') {
    write_live($file, []);
    tg_reply($tg, $chatId, '🗑️ Alle Live-News gelöscht.');
    echo json_encode(['ok' => true]);
    exit;
}

if ($text === '/list') {
    $list = read_live($file);
    if (!$list) {
        tg_reply($tg, $chatId, 'Keine Live-News aktiv.');
    } else {
        $lines = [];
        foreach ($list as $i => $n) {
            $exp = isset($n['expiresAt']) ? ' (bis ' . substr((string) $n['expiresAt'], 11, 5) . ')' : '';
            $lines[] = ($i + 1) . '. ' . ($n['title'] ?? '') . $exp;
        }
        tg_reply($tg, $chatId, "Aktive Live-News:\n" . implode("\n", $lines) . "\n\nLöschen: /del <Nr>");
    }
    echo json_encode(['ok' => true]);
    exit;
}

if (preg_match('#^/del\s+(\d+)$#', $text, $dm)) {
    $list = read_live($file);
    $idx = (int) $dm[1] - 1;
    if ($idx < 0 || $idx >= count($list)) {
        tg_reply($tg, $chatId, "Nr. {$dm[1]} gibt es nicht. /list zeigt die aktuellen.");
    } else {
        $removed = $list[$idx]['title'] ?? '';
        array_splice($list, $idx, 1);
        write_live($file, $list);
        tg_reply($tg, $chatId, "🗑️ Widerrufen: {$removed}");
    }
    echo json_encode(['ok' => true]);
    exit;
}

// --- News aus der Nachricht bauen --------------------------------------
$category = 'general';
$pinned = false;
$expireSpec = null;
$doPush = false;
$now = new DateTimeImmutable('now', $tz);
$publish = $now; // Standard: sofort veröffentlichen

// @HH:mm → geplanter Veröffentlichungszeitpunkt (heute; falls schon vorbei → morgen).
$text = preg_replace_callback('/@(\d{1,2}):(\d{2})/', function (array $m) use (&$publish, $now) {
    $h = (int) $m[1];
    $min = (int) $m[2];
    if ($h > 23 || $min > 59) {
        return $m[0]; // ungültig → im Text lassen
    }
    $cand = $now->setTime($h, $min, 0);
    if ($cand < $now) {
        $cand = $cand->add(new DateInterval('P1D'));
    }
    $publish = $cand;
    return '';
}, $text);

// Hashtags auswerten und aus dem Text entfernen.
$catMap = ['safety' => 'safety', 'info' => 'info', 'lineup' => 'lineup', 'general' => 'general'];
$text = preg_replace_callback('/#(\w+)/u', function (array $m) use (&$category, &$pinned, &$expireSpec, &$doPush, $catMap) {
    $tag = strtolower($m[1]);
    if (isset($catMap[$tag])) {
        $category = $catMap[$tag];
        return '';
    }
    if ($tag === 'pin' || $tag === 'pinned') {
        $pinned = true;
        return '';
    }
    if ($tag === 'push') {
        $doPush = true;
        return '';
    }
    if (preg_match('/^(\d+)(h|m)$/', $tag, $mm)) {
        $expireSpec = $mm[2] === 'h' ? "PT{$mm[1]}H" : "PT{$mm[1]}M";
        return '';
    }
    return $m[0]; // unbekannter Hashtag bleibt im Text
}, $text);
$text = trim($text);

// Erste Zeile = Titel, Rest = Body.
$lines = preg_split('/\r?\n/', $text, 2);
$title = trim($lines[0] ?? '');
$body = trim($lines[1] ?? '');
if ($title === '') {
    echo json_encode(['ok' => true]);
    exit;
}

$item = [
    'id' => 'live-' . time() . '-' . substr(md5($text . $fromId), 0, 4),
    'title' => $title,
    'body' => $body,
    'category' => $category,
    'publishAt' => $publish->format('c'),
];
if ($pinned) {
    $item['pinned'] = true;
}
if ($expireSpec) {
    // Ablauf relativ zum Veröffentlichungszeitpunkt (nicht zum Sendezeitpunkt).
    $item['expiresAt'] = $publish->add(new DateInterval($expireSpec))->format('c');
}

// 4) An live-news.json anhängen (neueste Begrenzung).
$list = read_live($file);
$list[] = $item;
$max = (int) ($tg['maxItems'] ?? 200);
if (count($list) > $max) {
    $list = array_slice($list, -$max);
}
write_live($file, $list);

// Push: bei #push ODER automatisch für bestimmte Kategorien (Standard: safety),
// aber nur bei sofortiger Veröffentlichung (nicht bei geplanten Posts).
$autoCats = $tg['pushAutoCategories'] ?? ['safety'];
$pushNote = '';
if (($doPush || in_array($category, $autoCats, true)) && $publish <= $now) {
    $report = push_news_notification($item);
    if ($report !== null) {
        $pushNote = " · 📣 Push an {$report['sent']}";
    }
}

$confirm = ($publish > $now
    ? '⏰ Geplant für ' . $publish->format('d.m. H:i') . ': ' . $title
    : '✅ Veröffentlicht: ' . $title) . $pushNote;
tg_reply($tg, $chatId, $confirm);
echo json_encode(['ok' => true]);

// --- Helfer -------------------------------------------------------------
function push_news_notification(array $item): ?array
{
    // Web-Push optional: ohne installierte Abhängigkeit/Setup einfach überspringen.
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return null;
    }
    require_once __DIR__ . '/sender.php';
    if (!function_exists('push_broadcast')) {
        return null;
    }
    try {
        return push_broadcast([
            'title' => (string) ($item['title'] ?? 'Festivadget'),
            'body'  => (string) ($item['body'] ?? ''),
            'url'   => '/news',
            'tag'   => 'live-' . ($item['category'] ?? 'news'),
        ]);
    } catch (Throwable $e) {
        require_once __DIR__ . '/log.php';
        app_log('error', 'telegram', 'Push zur Live-News fehlgeschlagen: ' . $e->getMessage());
        return null; // Push-Fehler darf das Posten nie stören
    }
}

function read_live(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function write_live(string $file, array $list): void
{
    file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
}

function tg_reply(array $tg, $chatId, string $text): void
{
    $token = $tg['botToken'] ?? '';
    if ($token === '' || !$chatId || !function_exists('curl_init')) {
        return;
    }
    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['chat_id' => $chatId, 'text' => $text]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
