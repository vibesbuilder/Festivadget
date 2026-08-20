<?php
// Sending logic: sends a payload to all stored subscriptions (web push protocol
// via minishlink/web-push). Expired subscriptions (404/410) are removed.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/texts.php';
require_once __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Sends the payload to the given subscription rows (endpoint/p256dh/auth).
 * Expired subscriptions (404/410) are removed.
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
            // Endpoint is dead (subscription deleted/expired) -> clean up.
            $del->execute([$report->getEndpoint()]);
            $removed++;
        }
    }

    // Central log - covers admin push, cron digest and news push.
    $failed = count($rows) - $sent - $removed;
    app_log(
        $failed > 0 ? 'warn' : 'info',
        'push',
        sprintf(
            'Send "%s": %d delivered, %d failed, %d expired subscriptions removed (%d subscriptions)',
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
 * Send to ALL subscriptions (e.g. a manual push from the admin).
 * @param array{title?:string,body?:string,url?:string,tag?:string} $payload
 * @return array{total:int,sent:int,removed:int}
 */
function push_broadcast(array $payload): array
{
    $rows = push_db()->query('SELECT endpoint, p256dh, auth FROM push_subscriptions')->fetchAll();
    return push_send_rows($rows, $payload);
}

/**
 * Category-aware news push: safety goes to EVERYONE; otherwise only to
 * subscriptions that chose this category (categories NULL = all, empty = safety only).
 * title/body may be language maps ({de:…,en:…}); the text is resolved per
 * subscription language and only truncated afterwards.
 * @return array{total:int,sent:int,removed:int}
 */
function push_send_news(array $payload, string $category): array
{
    $pdo = push_db();
    if ($category === 'safety') {
        $rows = $pdo->query('SELECT endpoint, p256dh, auth, lang FROM push_subscriptions')->fetchAll();
    } else {
        $stmt = $pdo->prepare(
            'SELECT endpoint, p256dh, auth, lang FROM push_subscriptions
             WHERE categories IS NULL OR FIND_IN_SET(?, categories)'
        );
        $stmt->execute([$category]);
        $rows = $stmt->fetchAll();
    }

    $total = ['total' => 0, 'sent' => 0, 'removed' => 0];
    foreach (push_rows_by_lang($rows) as $lang => $group) {
        $title = push_localize($payload['title'] ?? '', $lang);
        $body  = push_localize($payload['body'] ?? '', $lang);
        if (mb_strlen($body) > 180) {
            $body = mb_substr($body, 0, 177) . '…';
        }
        $localized = array_merge($payload, [
            'title' => $title !== '' ? $title : push_tr($lang, 'Neuigkeit'),
            'body'  => $body,
        ]);
        $r = push_send_rows($group, $localized);
        foreach ($total as $key => $_v) {
            $total[$key] += $r[$key];
        }
    }
    return $total;
}
