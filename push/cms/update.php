<?php
// CMS-Updater (Task #92.4, Komfort-Variante): spielt ein Update-Paket
// (festivadget-update-v*.zip) per 1-Klick ein. Kundeninhalte sind hart
// geschützt: data/ (Inhalte + Uploads), push/config.php und die
// CMS-/Wetter-Laufzeitdateien werden NIE überschrieben – zusätzlich zur
// Paket-Seite (das Update-ZIP enthält data/ gar nicht erst).

declare(strict_types=1);

// Diese Pfade (Prefix-Match, relativ zum Webroot) bleiben immer unangetastet.
const CMS_UPDATE_PROTECTED = [
    'data/',
    'install/',
    'push/config.php',
    'push/cms-settings.json',
    'push/weather-settings.json',
];

/** Webroot der Installation (Ziel des Updates). */
function cms_update_root(): string
{
    return dirname(__DIR__, 2);
}

/** Installierte Version laut VERSION-Datei (Release-Pakete legen sie an). */
function cms_update_version(): string
{
    $f = cms_update_root() . '/VERSION';
    return is_file($f) ? trim((string) file_get_contents($f)) : '';
}

/**
 * Alle Datei-Einträge des ZIPs als [name => Inhalt-Lesefunktion].
 * ZipArchive (Standard am Hosting) mit PharData-Fallback (ext-phar).
 *
 * @return array<string, callable():string|false>|string Fehlercode-String bei Problemen.
 */
function cms_update_entries(string $zipPath)
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return 'zip-invalid';
        }
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === '' || str_ends_with($name, '/')) {
                continue; // Ordner-Einträge
            }
            $entries[$name] = static fn() => $zip->getFromIndex($i);
        }
        return $entries;
    }
    if (extension_loaded('phar')) {
        try {
            $phar = new PharData($zipPath);
        } catch (Throwable) {
            return 'zip-invalid';
        }
        $entries = [];
        $it = new RecursiveIteratorIterator($phar);
        foreach ($it as $file) {
            /** @var PharFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            // Pfad innerhalb des Archivs (nach dem "phar://...zip/"-Präfix).
            $name = substr($file->getPathname(), strlen($phar->getPath()) + 8);
            $name = str_replace('\\', '/', ltrim($name, '/'));
            $path = $file->getPathname();
            $entries[$name] = static fn() => file_get_contents($path);
        }
        return $entries;
    }
    return 'zip-missing'; // weder ZipArchive noch Phar verfügbar
}

/**
 * Update-Paket anwenden. Liefert
 * ['ok'=>bool, 'error'=>?string, 'updated'=>int, 'skipped'=>int, 'version'=>string].
 */
function cms_update_apply(string $zipPath): array
{
    $entries = cms_update_entries($zipPath);
    if (is_string($entries)) {
        return ['ok' => false, 'error' => $entries, 'updated' => 0, 'skipped' => 0, 'version' => ''];
    }

    // Plausibilität: sieht das nach einem Festivadget-Update-Paket aus?
    if (!isset($entries['index.html']) || !isset($entries['push/db.php'])) {
        return ['ok' => false, 'error' => 'not-update-package', 'updated' => 0, 'skipped' => 0, 'version' => ''];
    }
    // Volles Release-Paket (mit data/) ist zum Updaten falsch – ablehnen,
    // bevor Kundeninhalte auch nur theoretisch zur Debatte stehen.
    foreach ($entries as $name => $_) {
        if (str_starts_with($name, 'data/')) {
            return ['ok' => false, 'error' => 'full-package', 'updated' => 0, 'skipped' => 0, 'version' => ''];
        }
    }

    $root = cms_update_root();
    $updated = $skipped = 0;
    foreach ($entries as $name => $read) {
        // Pfad-Härtung: keine Traversals, keine absoluten Pfade.
        if (str_contains($name, '..') || str_starts_with($name, '/') || preg_match('/^[a-zA-Z]:/', $name)) {
            return ['ok' => false, 'error' => 'bad-path', 'updated' => $updated, 'skipped' => $skipped, 'version' => ''];
        }
        $protected = false;
        foreach (CMS_UPDATE_PROTECTED as $prefix) {
            if ($name === rtrim($prefix, '/') || str_starts_with($name, $prefix)) {
                $protected = true;
                break;
            }
        }
        if ($protected) {
            $skipped++;
            continue;
        }
        $data = $read();
        if ($data === false) {
            return ['ok' => false, 'error' => 'read-failed', 'updated' => $updated, 'skipped' => $skipped, 'version' => ''];
        }
        $target = "$root/$name";
        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return ['ok' => false, 'error' => 'write-failed', 'updated' => $updated, 'skipped' => $skipped, 'version' => ''];
        }
        if (@file_put_contents($target, $data, LOCK_EX) === false) {
            return ['ok' => false, 'error' => 'write-failed', 'updated' => $updated, 'skipped' => $skipped, 'version' => ''];
        }
        $updated++;
    }

    return ['ok' => true, 'error' => null, 'updated' => $updated, 'skipped' => $skipped, 'version' => cms_update_version()];
}
