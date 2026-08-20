<?php
// Anonymous usage counter + client error reports.
// POST JSON { page, anon, session, lang?, theme? }        -> one statistics row
// POST JSON { type:"error", message, route, anon, session } -> log entry
// Called by the client via sendBeacon (production build only).
// No IPs, no user agents - see stats-db.php / log.php.

declare(strict_types=1);

require_once __DIR__ . '/stats-db.php';
require_once __DIR__ . '/log.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

// Normalize inputs strictly (the endpoint is inherently open).
$clean = static function ($v, int $max): string {
    $s = strtolower((string) $v);
    $s = preg_replace('/[^a-z0-9_-]/', '', $s) ?? '';
    return substr($s, 0, $max);
};
$anon    = $clean($body['anon'] ?? '', 32);
$session = $clean($body['session'] ?? '', 32);

if (strlen($anon) < 8 || strlen($session) < 8) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

// --- Client errors -> log (source "client") --------------------------------
if (($body['type'] ?? '') === 'error') {
    $route   = substr(preg_replace('#[^a-z0-9/_-]#i', '', (string) ($body['route'] ?? '')) ?? '', 0, 64);
    $message = (string) ($body['message'] ?? '');
    if ($message !== '') {
        app_log('error', 'client', ($route !== '' ? $route . ' – ' : '') . $message);
    }
    http_response_code(204);
    exit;
}

// --- Page view -> statistics ------------------------------------------------
$page  = $clean($body['page'] ?? '', 32);
$lang  = $clean($body['lang'] ?? '', 8);
$theme = $clean($body['theme'] ?? '', 8);

if ($page === '') {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna'));
    stats_db()
        ->prepare('INSERT INTO app_stats_events (ts, day, page, anon, session, lang, theme) VALUES (?, ?, ?, ?, ?, ?, ?)')
        // Microseconds: events of the same second stay uniquely sortable.
        ->execute([$now->format('Y-m-d H:i:s.u'), $now->format('Y-m-d'), $page, $anon, $session, $lang, $theme]);
    http_response_code(204);
} catch (Throwable) {
    // Statistics are secondary - never escalate an error to the client.
    http_response_code(204);
}
