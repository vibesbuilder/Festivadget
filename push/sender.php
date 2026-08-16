<?php
// Versand-Logik: schickt eine Payload an alle gespeicherten Abos (Web-Push-Protokoll
// via minishlink/web-push). Abgelaufene Abos (404/410) werden entfernt.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Sendet die Payload an die übergebenen Abo-Zeilen (endpoint/p256dh/auth).
 * Abgelaufene Abos (404/410) werden entfernt.
 * @return array{total:int,sent:int,removed:int}
 */
function push_send_rows(array $rows, array $payload): array
{
    $cfg  = push_config()['vapid'];
    $auth = ['VAPID' => [
        'subject'    => $cfg['subject'],
        'publicKey'  => $cfg['publicKey'],
        'privateKey' => $cfg['privateKey'],
    ]];

    $webPush = new WebPush($auth);
    $json    = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    foreach ($rows as $r) {
        $sub = Subscription::create([
            'endpoint' => $r['endpoint'],
            'keys'     => ['p256dh' => $r['p256dh'], 'auth' => $r['auth']],
        ]);
        $webPush->queueNotification($sub, $json);
    }

    $sent = 0;
    $removed = 0;
    $del = push_db()->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?');

    foreach ($webPush->flush() as $report) {
        if ($report->isSuccess()) {
            $sent++;
        } elseif ($report->isSubscriptionExpired()) {
            // Endpoint ist tot (Abo gelöscht/abgelaufen) → aufräumen.
            $del->execute([$report->getEndpoint()]);
            $removed++;
        }
    }

    // Zentrales Protokoll – deckt Admin-Push, Cron-Digest und News-Push ab.
    $failed = count($rows) - $sent - $removed;
    app_log(
        $failed > 0 ? 'warn' : 'info',
        'push',
        sprintf(
            'Versand "%s": %d gesendet, %d fehlgeschlagen, %d abgelaufene Abos entfernt (%d Abos)',
            substr((string) ($payload['title'] ?? ''), 0, 80),
            $sent,
            $failed,
            $removed,
            count($rows)
        )
    );

    return ['total' => count($rows), 'sent' => $sent, 'removed' => $removed];
}

/**
 * An ALLE Abos senden (z. B. manueller Push aus dem Admin).
 * @param array{title?:string,body?:string,url?:string,tag?:string} $payload
 * @return array{total:int,sent:int,removed:int}
 */
function push_broadcast(array $payload): array
{
    $rows = push_db()->query('SELECT endpoint, p256dh, auth FROM push_subscriptions')->fetchAll();
    return push_send_rows($rows, $payload);
}

/**
 * Kategoriebewusster News-Push: Safety geht an ALLE; sonst nur an Abos, die diese
 * Kategorie gewählt haben (categories NULL = alle, leer = nur Safety).
 * @return array{total:int,sent:int,removed:int}
 */
function push_send_news(array $payload, string $category): array
{
    $pdo = push_db();
    if ($category === 'safety') {
        $rows = $pdo->query('SELECT endpoint, p256dh, auth FROM push_subscriptions')->fetchAll();
    } else {
        $stmt = $pdo->prepare(
            'SELECT endpoint, p256dh, auth FROM push_subscriptions
             WHERE categories IS NULL OR FIND_IN_SET(?, categories)'
        );
        $stmt->execute([$category]);
        $rows = $stmt->fetchAll();
    }
    return push_send_rows($rows, $payload);
}
