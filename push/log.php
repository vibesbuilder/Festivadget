<?php
// Central server log (app_log): push sends, subscription changes,
// admin logins, weather/Telegram errors and client errors from the app.
//
// Principles:
// - app_log() is FAIL-SILENT: logging must never break an endpoint.
// - No IPs, no user agents, no personal data.
// - Retention ~90 days (cleanup runs occasionally alongside writes).

declare(strict_types=1);

require_once __DIR__ . '/stats-db.php';

const APP_LOG_RETENTION_DAYS = 90;

function app_log_init(PDO $pdo): void
{
    // DDL compatible with MySQL and SQLite (like app_stats_events).
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
        // Index already exists.
    }
}

/** Write an entry. $level: info|warn|error, $source: short tag (push, auth, …). */
function app_log(string $level, string $source, string $message): void
{
    try {
        $pdo = stats_db();
        app_log_init($pdo);
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna'));
        // Strip control characters, cap the length (the source may be client input).
        $message = substr(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $message) ?? '', 0, 500);
        $level   = in_array($level, ['info', 'warn', 'error'], true) ? $level : 'info';
        $pdo->prepare('INSERT INTO app_log (ts, level, source, message) VALUES (?, ?, ?, ?)')
            ->execute([$now->format('Y-m-d H:i:s.u'), $level, substr($source, 0, 16), $message]);
        // Occasional cleanup (~1 % of writes is plenty).
        if (random_int(1, 100) === 1) {
            $cutoff = $now->modify('-' . APP_LOG_RETENTION_DAYS . ' days')->format('Y-m-d H:i:s');
            $pdo->prepare('DELETE FROM app_log WHERE ts < ?')->execute([$cutoff]);
        }
    } catch (Throwable) {
        // Logging is secondary - never escalate.
    }
}

/** Last entries (newest first), optionally filtered by level/source. */
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

/** Existing sources (for the filter in the log tab). */
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
