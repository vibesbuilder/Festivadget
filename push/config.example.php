<?php
// Template for push/config.php - do NOT commit real values (config.php is gitignored).
// Copy this file to config.php and fill in the values.

declare(strict_types=1);

return [
    // MySQL credentials (World4You: from the customer area).
    'db' => [
        'host'    => 'localhost',
        'name'    => 'festivadget',
        'user'    => '',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // VAPID keys: generate once with `php push/vapid-keys.php`.
    // push/vapid.php delivers the publicKey to the app at runtime
    // (VITE_VAPID_PUBLIC_KEY in the build env is only an optional fallback now).
    'vapid' => [
        'subject'    => 'mailto:webmaster@example.org',
        'publicKey'  => '',
        'privateKey' => '',
    ],

    // Password hash for the admin page: php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);"
    'adminPasswordHash' => '',

    // Secret for the HTTP call of cron-send.php (external pinger).
    // Call: https://your-domain.example/push/cron-send.php?key=THIS_SECRET
    'cronSecret' => '',

    // Weather (weather.php): FALLBACK values - normally you maintain everything in
    // the CMS tab "Weather" (writes push/weather-settings.json, which overrides
    // this section). provider: geosphere | openweather | weatherapi | met_norway;
    // station_id optional (TAWES measurement, GeoSphere only).
    'weather' => [
        'provider'   => 'geosphere',
        'lat'        => 47.928,
        'lon'        => 14.083,
        'location'   => 'Inzersdorf im Kremstal',
        'station_id' => '',
    ],

    // Folder with the deployed content data (public/data on the server).
    'dataDir' => __DIR__ . '/../data',

    // Automatic pushes (cron-send.php) - on/off and filters.
    // Note: these three can also be overridden live in the CMS (settings).
    'autoPushUpcoming' => true, // „Gleich live"-Digest bald startender Acts (aus slots.json)
    'upcomingWindowMin' => 60,  // digest lead time in minutes - adapt to the cron frequency
    'autoPushNews'     => true, // neu veröffentlichte News automatisch pushen (aus news.json)
    // Which news gets pushed: categories from this list OR pinned === true.
    'pushNewsCategories' => ['safety'],

    // Telegram live news (telegram-hook.php). Unmoderated, allowed senders only.
    'telegram' => [
        'botToken'       => '',            // von @BotFather
        'webhookSecret'  => '',            // frei wählbares Geheimnis (in setWebhook gesetzt)
        'allowedUserIds' => [],            // erlaubte User-ID(s), z. B. [123456789, 987654321]
        'allowedChatIds' => [],            // optional: erlaubte Gruppen-/Chat-ID(s), z. B. [-1001234567890]
        'liveNewsFile'   => __DIR__ . '/../data/live-news.json',
        'tz'             => 'Europe/Vienna',
        'maxItems'       => 200,           // ältere Live-News werden abgeschnitten
        // Live news additionally as web push: via the #push tag (always) OR automatically
        // for these categories. Prerequisite: web push set up (see docs/PUSH.md).
        'pushAutoCategories' => ['safety'],
    ],

    // Server importer (admin UI "Sources", push/cms/importer.php). Only if used.
    // Controls ONLY the live import on the server - independent of
    // content-sources.config.ts (local build import).
    'sources' => [
        // 'token' = ONLY the Joomla API token (single quotes), WITHOUT
        // "Authorization: Bearer " in front - the importer adds that itself.
        'joomla'    => ['baseUrl' => '', 'token' => ''],
        'wordpress' => ['baseUrl' => '', 'user' => '', 'appPassword' => ''],
    ],
];
