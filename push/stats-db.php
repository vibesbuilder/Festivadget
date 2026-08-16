<?php
// App-Nutzungsstatistik: eigene, schlanke PDO-Schicht (bewusst getrennt von
// db.php/push_db, damit Statistik auch ohne Push-Schema funktioniert).
// Nutzt dieselbe config.php ('db'); optional 'driver' => 'sqlite' + 'path'
// (lokale Entwicklung/Tests). DDL ist MySQL-UND-SQLite-kompatibel.
//
// Datensparsamkeit: pro Seitenaufruf nur Zeitpunkt, Seitenname und zwei
// ZUFÄLLIGE Client-Kennungen (anon = Gerät, session = Sitzung). Keine IPs,
// keine User-Agents, keine personenbezogenen Daten.

declare(strict_types=1);

function stats_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $file = __DIR__ . '/config.php';
    if (!is_file($file)) {
        http_response_code(500);
        exit('push/config.php fehlt – aus config.example.php erstellen.');
    }
    $cfg = require $file;
    return is_array($cfg) ? $cfg : [];
}

function stats_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = stats_config()['db'] ?? [];
    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . ($c['path'] ?? __DIR__ . '/stats.sqlite'));
    } else {
        $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
        $pdo = new PDO($dsn, $c['user'], $c['pass']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    stats_init_schema($pdo);
    return $pdo;
}

function stats_init_schema(PDO $pdo): void
{
    // Bewusst ohne AUTO_INCREMENT/ENGINE – identisches DDL für MySQL + SQLite.
    // ts mit Mikrosekunden (26 Zeichen): mehrere Events derselben Sekunde müssen
    // eindeutig sortierbar bleiben („letzter Stand je Gerät" bei Sprache/Theme).
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_stats_events (
            ts      VARCHAR(26) NOT NULL,
            day     VARCHAR(10) NOT NULL,
            page    VARCHAR(32) NOT NULL,
            anon    VARCHAR(32) NOT NULL,
            session VARCHAR(32) NOT NULL,
            lang    VARCHAR(8)  NOT NULL DEFAULT \'\',
            theme   VARCHAR(8)  NOT NULL DEFAULT \'\'
        )'
    );
    // MySQL kennt kein CREATE INDEX IF NOT EXISTS / ADD COLUMN IF NOT EXISTS →
    // idempotent via try/catch (gleiches Muster wie die Migrationen in db.php).
    foreach ([
        // Migrationen für Bestandsinstallationen der ersten Version.
        "ALTER TABLE app_stats_events ADD COLUMN lang VARCHAR(8) NOT NULL DEFAULT ''",
        "ALTER TABLE app_stats_events ADD COLUMN theme VARCHAR(8) NOT NULL DEFAULT ''",
        'ALTER TABLE app_stats_events MODIFY ts VARCHAR(26) NOT NULL', // MySQL; SQLite ignoriert Längen ohnehin
        'CREATE INDEX idx_stats_day ON app_stats_events (day)',
        'CREATE INDEX idx_stats_page ON app_stats_events (page)',
    ] as $ddl) {
        try {
            $pdo->exec($ddl);
        } catch (Throwable) {
            // Spalte/Index existiert bereits bzw. Dialekt kennt das DDL nicht → ignorieren.
        }
    }
}
