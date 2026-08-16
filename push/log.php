<?php
// Zentrales Server-Protokoll (app_log): Push-Versand, Abo-Änderungen,
// Admin-Logins, Wetter-/Telegram-Fehler und Client-Fehler aus der App.
//
// Grundsätze:
// - app_log() ist FAIL-SILENT: Logging darf nie einen Endpoint brechen.
// - Keine IPs, keine User-Agents, keine personenbezogenen Daten.
// - Aufbewahrung ~90 Tage (Bereinigung läuft gelegentlich beim Schreiben mit).

declare(strict_types=1);

require_once __DIR__ . '/stats-db.php';

const APP_LOG_RETENTION_DAYS = 90;

function app_log_init(PDO $pdo): void
{
    // DDL MySQL- und SQLite-kompatibel (wie app_stats_events).
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_log (
            ts      VARCHAR(26) NOT NULL,
            level   VARCHAR(8)  NOT NULL,
            source  VARCHAR(16) NOT NULL,
            message VARCHAR(500) NOT NULL
        )'
    );
    try {
        $pdo->exec('CREATE INDEX idx_log_ts ON app_log (ts)');
    } catch (Throwable) {
        // Index existiert bereits.
    }
}

/** Eintrag schreiben. $level: info|warn|error, $source: kurzes Kürzel (push, auth, …). */
function app_log(string $level, string $source, string $message): void
{
    try {
        $pdo = stats_db();
        app_log_init($pdo);
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna'));
        // Steuerzeichen raus, Länge deckeln (Quelle kann Client-Input sein).
        $message = substr(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $message) ?? '', 0, 500);
        $level   = in_array($level, ['info', 'warn', 'error'], true) ? $level : 'info';
        $pdo->prepare('INSERT INTO app_log (ts, level, source, message) VALUES (?, ?, ?, ?)')
            ->execute([$now->format('Y-m-d H:i:s.u'), $level, substr($source, 0, 16), $message]);
        // Gelegentliche Bereinigung (~1 % der Schreibvorgänge reicht völlig).
        if (random_int(1, 100) === 1) {
            $cutoff = $now->modify('-' . APP_LOG_RETENTION_DAYS . ' days')->format('Y-m-d H:i:s');
            $pdo->prepare('DELETE FROM app_log WHERE ts < ?')->execute([$cutoff]);
        }
    } catch (Throwable) {
        // Logging ist nachrangig – nie eskalieren.
    }
}

/** Letzte Einträge (neueste zuerst), optional nach Level/Quelle gefiltert. */
function app_log_recent(?string $level = null, ?string $source = null, int $limit = 200): array
{
    try {
        $pdo = stats_db();
        app_log_init($pdo);
        $where = [];
        $args  = [];
        if ($level !== null && $level !== '') {
            $where[] = 'level = ?';
            $args[]  = $level;
        }
        if ($source !== null && $source !== '') {
            $where[] = 'source = ?';
            $args[]  = $source;
        }
        $sql = 'SELECT ts, level, source, message FROM app_log'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY ts DESC LIMIT ' . max(1, min(1000, $limit));
        $st = $pdo->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

/** Vorhandene Quellen (für den Filter im Protokoll-Tab). */
function app_log_sources(): array
{
    try {
        $pdo = stats_db();
        app_log_init($pdo);
        return array_column(
            $pdo->query('SELECT DISTINCT source FROM app_log ORDER BY source')->fetchAll(),
            'source'
        );
    } catch (Throwable) {
        return [];
    }
}
