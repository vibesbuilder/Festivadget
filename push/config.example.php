<?php
// Vorlage für push/config.php – echte Werte NICHT committen (config.php ist in .gitignore).
// Kopiere diese Datei nach config.php und trage die Werte ein.

declare(strict_types=1);

return [
    // MySQL-Zugangsdaten (World4You: aus dem Kundenbereich).
    'db' => [
        'host'    => 'localhost',
        'name'    => 'festivadget',
        'user'    => '',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // VAPID-Schlüssel: einmal mit `php push/vapid-keys.php` erzeugen.
    // publicKey kommt zusätzlich als VITE_VAPID_PUBLIC_KEY in den Client-Build.
    'vapid' => [
        'subject'    => 'mailto:info@rockimdorf.at',
        'publicKey'  => '',
        'privateKey' => '',
    ],

    // Passwort-Hash für die Admin-Seite: php -r "echo password_hash('DEIN_PASSWORT', PASSWORD_DEFAULT);"
    'adminPasswordHash' => '',

    // Geheimnis für den HTTP-Aufruf von cron-send.php (externer Pinger).
    // Aufruf: https://app.rockimdorf.at/push/cron-send.php?key=DIESES_GEHEIMNIS
    'cronSecret' => '',

    // Wetter (weather.php): FALLBACK-Werte – im Normalfall pflegst du alles im
    // CMS-Tab „Wetter" (schreibt push/weather-settings.json, die diese Sektion
    // überschreibt). provider: geosphere | openweather | weatherapi | met_norway;
    // station_id optional (TAWES-Messwert, nur GeoSphere).
    'weather' => [
        'provider'   => 'geosphere',
        'lat'        => 47.928,
        'lon'        => 14.083,
        'location'   => 'Inzersdorf im Kremstal',
        'station_id' => '',
    ],

    // Ordner mit den ausgelieferten Inhaltsdaten (public/data am Server).
    'dataDir' => __DIR__ . '/../data',

    // Automatische Pushes (cron-send.php) – ein/aus und Filter.
    // Hinweis: diese drei lassen sich auch live im CMS (Einstellungen) überschreiben.
    'autoPushUpcoming' => true, // „Gleich live"-Digest bald startender Acts (aus slots.json)
    'upcomingWindowMin' => 60,  // Vorlaufzeit des Digests in Minuten – an die Cron-Frequenz anpassen
    'autoPushNews'     => true, // neu veröffentlichte News automatisch pushen (aus news.json)
    // Welche News gepusht werden: Kategorien aus dieser Liste ODER pinned === true.
    'pushNewsCategories' => ['safety'],

    // Telegram-Live-News (telegram-hook.php). Unmoderiert, nur erlaubte Absender.
    'telegram' => [
        'botToken'       => '',            // von @BotFather
        'webhookSecret'  => '',            // frei wählbares Geheimnis (in setWebhook gesetzt)
        'allowedUserIds' => [],            // erlaubte User-ID(s), z. B. [123456789, 987654321]
        'allowedChatIds' => [],            // optional: erlaubte Gruppen-/Chat-ID(s), z. B. [-1001234567890]
        'liveNewsFile'   => __DIR__ . '/../data/live-news.json',
        'tz'             => 'Europe/Vienna',
        'maxItems'       => 200,           // ältere Live-News werden abgeschnitten
        // Live-News zusätzlich als Web-Push: per Tag #push (immer) ODER automatisch
        // für diese Kategorien. Voraussetzung: Web-Push eingerichtet (siehe docs/PUSH.md).
        'pushAutoCategories' => ['safety'],
    ],

    // Server-Importer (Admin-UI „Quellen", push/cms/importer.php). Nur falls genutzt.
    // Steuert NUR den Live-Import auf dem Server – unabhängig von
    // content-sources.config.ts (lokaler Build-Import).
    'sources' => [
        // 'token' = NUR der Joomla-API-Token (einfache Quotes), OHNE
        // "Authorization: Bearer " davor – das setzt der Importer selbst.
        'joomla'    => ['baseUrl' => 'https://rockimdorf.at', 'token' => ''],
        'wordpress' => ['baseUrl' => '', 'user' => '', 'appPassword' => ''],
    ],
];
