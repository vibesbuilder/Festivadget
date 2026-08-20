<?php
// CMS updater (task #92.4, convenience variant): applies an update package
// (festivadget-update-v*.zip) with one click. Customer content is hard-
// protected: data/ (content + uploads), push/config.php and the CMS/weather
// runtime files are NEVER overwritten - in addition to the package side
// (the update ZIP does not contain data/ in the first place).

declare(strict_types=1);

// These paths (prefix match, relative to the webroot) always stay untouched.
const CMS_UPDATE_PROTECTED = [
    'data/',
    'install/',
    'push/config.php',
    'push/cms-settings.json',
    'push/weather-settings.json',
];

/** Webroot of the installation (target of the update). */
function cms_update_root(): string
{
    return dirname(__DIR__, 2);
}

/** Installed version according to the VERSION file (release packages create it). */
function cms_update_version(): string
{
    $f = cms_update_root() . '/VERSION';
    return is_file($f) ? trim((string) file_get_contents($f)) : '';
}

/**
 * All file entries of the ZIP as [name => content read function].
 * ZipArchive (standard on hosting) with PharData fallback (ext-phar).
 *
 * @return array<string, callable():string|false>|string error code string on problems.
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
            // Path inside the archive (after the "phar://...zip/" prefix).
            $name = substr($file->getPathname(), strlen($phar->getPath()) + 8);
            $name = str_replace('\\', '/', ltrim($name, '/'));
            $path = $file->getPathname();
            $entries[$name] = static fn() => file_get_contents($path);
        }
        return $entries;
    }
    return 'zip-missing'; // neither ZipArchive nor Phar available
}

/**
 * Apply an update package. Returns
 * ['ok'=>bool, 'error'=>?string, 'updated'=>int, 'skipped'=>int, 'version'=>string].
 */
function cms_update_apply(string $zipPath): array
{
    $entries = cms_update_entries($zipPath);
    if (is_string($entries)) {
        return ['ok' => false, 'error' => $entries, 'updated' => 0, 'skipped' => 0, 'version' => ''];
    }

    // Plausibility: does this look like a Festivadget update package?
    if (!isset($entries['index.html']) || !isset($entries['push/db.php'])) {
        return ['ok' => false, 'error' => 'not-update-package', 'updated' => 0, 'skipped' => 0, 'version' => ''];
    }
    // A full release package (with data/) is wrong for updating - reject it
    // before customer content is even theoretically at stake.
    foreach ($entries as $name => $_) {
        if (str_starts_with($name, 'data/')) {
            return ['ok' => false, 'error' => 'full-package', 'updated' => 0, 'skipped' => 0, 'version' => ''];
        }
    }

    $root = cms_update_root();
    $updated = $skipped = 0;
    foreach ($entries as $name => $read) {
        // Path hardening: no traversals, no absolute paths.
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
