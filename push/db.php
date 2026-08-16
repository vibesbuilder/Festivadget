<?php
// Konfiguration + Datenbank (PDO/MySQL) inkl. Schema-Bootstrap.

declare(strict_types=1);

function push_config(): array
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
    return $cfg;
}

function push_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = push_config()['db'];
    $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    push_init_schema($pdo);
    return $pdo;
}

function push_init_schema(PDO $pdo): void
{
    // Abos. endpoint ist eindeutig (Re-Subscribe aktualisiert die Keys).
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS push_subscriptions (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            endpoint   VARCHAR(512) NOT NULL UNIQUE,
            p256dh     VARCHAR(255) NOT NULL,
            auth       VARCHAR(255) NOT NULL,
            ua         VARCHAR(255) NULL,
            categories VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Migration für bestehende Installationen: Kategorie-Präferenz je Abo
    // (CSV der gewählten Kategorien; NULL = alle). Idempotent.
    try {
        $pdo->exec('ALTER TABLE push_subscriptions ADD COLUMN categories VARCHAR(255) NULL');
    } catch (Throwable $e) {
        // Spalte existiert bereits → ignorieren.
    }
    // „Mein Plan": favorisierte Slot-IDs (CSV) für persönliche Erinnerungen.
    // Leer/NULL = kein Plan-Abo. Idempotent.
    try {
        $pdo->exec('ALTER TABLE push_subscriptions ADD COLUMN plan TEXT NULL');
    } catch (Throwable $e) {
        // Spalte existiert bereits → ignorieren.
    }

    // Idempotenz für zeitgesteuerte Pushes (z. B. ref = "slot:fr-main-greeen").
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS push_log (
            id      INT AUTO_INCREMENT PRIMARY KEY,
            ref     VARCHAR(191) NOT NULL UNIQUE,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Anonyme Zeitreihe der Abo-Zahlen (nur Zähler, KEINE personenbezogenen Daten).
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS push_stats (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            taken_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            total     INT NOT NULL,
            c_info    INT NOT NULL,
            c_lineup  INT NOT NULL,
            c_general INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

/**
 * Aktuelle Abo-Zahlen (live). Safety = total (alle erhalten Safety). Abos mit
 * categories = NULL (Altbestand/Default) zählen als „alle Kategorien".
 * @return array{total:int,c_info:int,c_lineup:int,c_general:int}
 */
function push_stats_current(): array
{
    $row = push_db()->query(
        "SELECT COUNT(*) total,
                SUM(categories IS NULL OR FIND_IN_SET('info', categories))    c_info,
                SUM(categories IS NULL OR FIND_IN_SET('lineup', categories))  c_lineup,
                SUM(categories IS NULL OR FIND_IN_SET('general', categories)) c_general
         FROM push_subscriptions"
    )->fetch();
    return [
        'total'     => (int) ($row['total'] ?? 0),
        'c_info'    => (int) ($row['c_info'] ?? 0),
        'c_lineup'  => (int) ($row['c_lineup'] ?? 0),
        'c_general' => (int) ($row['c_general'] ?? 0),
    ];
}

/** Schreibt einen Snapshot der Abo-Zahlen – höchstens alle $minIntervalMin Minuten. */
function push_stats_snapshot(int $minIntervalMin = 55): bool
{
    $pdo  = push_db();
    $last = $pdo->query('SELECT MAX(taken_at) FROM push_stats')->fetchColumn();
    if ($last && (time() - strtotime((string) $last)) < $minIntervalMin * 60) {
        return false; // zu früh – Tabelle nicht zumüllen
    }
    $c = push_stats_current();
    $pdo->prepare('INSERT INTO push_stats (total, c_info, c_lineup, c_general) VALUES (?, ?, ?, ?)')
        ->execute([$c['total'], $c['c_info'], $c['c_lineup'], $c['c_general']]);
    return true;
}

/** Letzte N Snapshots (neueste zuerst). */
function push_stats_recent(int $limit = 24): array
{
    $limit = max(1, min(200, $limit));
    return push_db()->query(
        "SELECT taken_at, total, c_info, c_lineup, c_general
         FROM push_stats ORDER BY taken_at DESC LIMIT $limit"
    )->fetchAll();
}
