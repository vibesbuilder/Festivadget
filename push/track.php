<?php
// Anonymer Nutzungs-Zähler + Client-Fehlermeldungen.
// POST JSON { page, anon, session, lang?, theme? }        -> eine Statistik-Zeile
// POST JSON { type:"error", message, route, anon, session } -> Protokoll-Eintrag
// Wird vom Client per sendBeacon gerufen (nur im Produktiv-Build).
// Keine IPs, keine User-Agents – siehe stats-db.php / log.php.

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

// Eingaben hart normalisieren (Endpoint ist naturgemäß offen).
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

// --- Client-Fehler → Protokoll (Quelle "client") ---------------------------
if (($body['type'] ?? '') === 'error') {
    $route   = substr(preg_replace('#[^a-z0-9/_-]#i', '', (string) ($body['route'] ?? '')) ?? '', 0, 64);
    $message = (string) ($body['message'] ?? '');
    if ($message !== '') {
        app_log('error', 'client', ($route !== '' ? $route . ' – ' : '') . $message);
    }
    http_response_code(204);
    exit;
}

// --- Seitenaufruf → Statistik ----------------------------------------------
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
        // Mikrosekunden: Events derselben Sekunde bleiben eindeutig sortierbar.
        ->execute([$now->format('Y-m-d H:i:s.u'), $now->format('Y-m-d'), $page, $anon, $session, $lang, $theme]);
    http_response_code(204);
} catch (Throwable) {
    // Statistik ist nachrangig – nie einen Fehler an den Client eskalieren.
    http_response_code(204);
}
