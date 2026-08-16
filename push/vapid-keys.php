<?php
// Erzeugt einmalig ein VAPID-Schlüsselpaar. Aufruf: php push/vapid-keys.php
// - publicKey  → in den Client-Build (VITE_VAPID_PUBLIC_KEY) UND in config.php
// - privateKey → NUR in push/config.php auf dem Server (niemals committen!)

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

echo "VITE_VAPID_PUBLIC_KEY=" . $keys['publicKey'] . PHP_EOL;
echo "privateKey=" . $keys['privateKey'] . PHP_EOL;
