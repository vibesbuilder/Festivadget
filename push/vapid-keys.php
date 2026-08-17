<?php
// Erzeugt einmalig ein VAPID-Schlüsselpaar. Aufruf: php push/vapid-keys.php
// - publicKey  → in config.php (die App holt ihn zur Laufzeit via push/vapid.php;
//                VITE_VAPID_PUBLIC_KEY in der Build-Env ist optionaler Fallback)
// - privateKey → NUR in push/config.php auf dem Server (niemals committen!)

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

echo "VITE_VAPID_PUBLIC_KEY=" . $keys['publicKey'] . PHP_EOL;
echo "privateKey=" . $keys['privateKey'] . PHP_EOL;
