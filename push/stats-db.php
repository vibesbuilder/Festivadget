<?php
// App usage statistics: its own slim PDO layer (deliberately separate from
// db.php/push_db so statistics work without the push schema too).
// Uses the same config.php ('db'); optionally 'driver' => 'sqlite' + 'path'
// (local development/tests). The DDL is compatible with MySQL AND SQLite.
//
// Data minimization: per page view only the time, page name and two RANDOM
// client identifiers (anon = device, session = session). No IPs,
// no user agents, no personal data.

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
    // Deliberately without AUTO_INCREMENT/ENGINE - identical DDL for MySQL + SQLite.
    // ts with microseconds (26 chars): multiple events of the same second must
    // remain uniquely sortable ("last state per device" for language/theme).
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
    // MySQL has no CREATE INDEX IF NOT EXISTS / ADD COLUMN IF NOT EXISTS ->
    // idempotent via try/catch (same pattern as the migrations in db.php).
    foreach ([
        // Migrations for existing installations of the first version.
        "ALTER TABLE app_stats_events ADD COLUMN lang VARCHAR(8) NOT NULL DEFAULT ''",
        "ALTER TABLE app_stats_events ADD COLUMN theme VARCHAR(8) NOT NULL DEFAULT ''",
        'ALTER TABLE app_stats_events MODIFY ts VARCHAR(26) NOT NULL', // MySQL; SQLite ignoriert Längen ohnehin
        'CREATE INDEX idx_stats_day ON app_stats_events (day)',
        'CREATE INDEX idx_stats_page ON app_stats_events (page)',
    ] as $ddl) {
        try {
            $pdo->exec($ddl);
        } catch (Throwable) {
            // Column/index already exists or the dialect does not know the DDL -> ignore.
        }
    }
}
