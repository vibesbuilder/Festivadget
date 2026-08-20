<?php
// Generates a VAPID key pair once. Usage: php push/vapid-keys.php
// - publicKey  -> into config.php (the app fetches it at runtime via push/vapid.php;
//                VITE_VAPID_PUBLIC_KEY in the build env is an optional fallback)
// - privateKey -> ONLY into push/config.php on the server (never commit!)

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

echo "VITE_VAPID_PUBLIC_KEY=" . $keys['publicKey'] . PHP_EOL;
echo "privateKey=" . $keys['privateKey'] . PHP_EOL;
