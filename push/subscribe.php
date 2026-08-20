<?php
// Subscription endpoint: stores/removes a browser push subscription.
// POST JSON: { action: "subscribe", subscription: {...} } | { action: "unsubscribe", endpoint: "..." }

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Only POST allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON.']);
    exit;
}

$pdo    = push_db();
$action = $body['action'] ?? '';

if ($action === 'subscribe') {
    $sub = $body['subscription'] ?? null;
    $endpoint = $sub['endpoint'] ?? '';
    $p256dh   = $sub['keys']['p256dh'] ?? '';
    $authKey  = $sub['keys']['auth'] ?? '';

    if ($endpoint === '' || $p256dh === '' || $authKey === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Incomplete subscription.']);
        exit;
    }

    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    // Category preference (safety always implicit). If the field is missing (old
    // client), the column stays NULL = "all". Empty array = safety only.
    $allowed = ['info', 'lineup', 'general'];
    $categories = null;
    if (array_key_exists('categories', $body)) {
        $sel = array_values(array_intersect($allowed, (array) $body['categories']));
        $categories = implode(',', $sel); // '' possible (safety only)
    }

    // App language of the subscription (drives the language of the push texts).
    // Missing field (old client) -> NULL = instance default.
    $lang = null;
    if (in_array((string) ($body['lang'] ?? ''), ['de', 'en', 'fr', 'es'], true)) {
        $lang = (string) $body['lang'];
    }

    // "My plan": favorited slot IDs. Missing field (old client) -> NULL
    // (leave unchanged). Empty array = no plan subscription.
    $plan = null;
    if (array_key_exists('plan', $body)) {
        $ids = array_values(array_filter(array_map(
            static fn($v) => preg_replace('/[^A-Za-z0-9_-]/', '', (string) $v),
            (array) $body['plan']
        )));
        $plan = implode(',', array_slice(array_unique($ids), 0, 500));
    }

    $stmt = $pdo->prepare(
        'INSERT INTO push_subscriptions (endpoint, p256dh, auth, ua, categories, plan, lang)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), ua = VALUES(ua),
                                 categories = COALESCE(VALUES(categories), categories),
                                 plan = COALESCE(VALUES(plan), plan),
                                 lang = COALESCE(VALUES(lang), lang)'
    );
    $stmt->execute([$endpoint, $p256dh, $authKey, $ua, $categories, $plan, $lang]);
    // Log without subscription details (the endpoint would be pseudo-identifying).
    require_once __DIR__ . '/log.php';
    $total = (int) $pdo->query('SELECT COUNT(*) FROM push_subscriptions')->fetchColumn();
    app_log('info', 'subscribe', "Subscription stored/updated ($total subscriptions total)");
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'unsubscribe') {
    $endpoint = $body['endpoint'] ?? '';
    if ($endpoint !== '') {
        $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?')->execute([$endpoint]);
        require_once __DIR__ . '/log.php';
        $total = (int) $pdo->query('SELECT COUNT(*) FROM push_subscriptions')->fetchColumn();
        app_log('info', 'subscribe', "Subscription removed ($total subscriptions total)");
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
