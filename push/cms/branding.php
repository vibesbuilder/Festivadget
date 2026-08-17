<?php
// Kunden-Branding (Paket Y) – Helfer für den CMS-Tab „Branding".
//
// Gespeichert wird alles in data/app-config.json unter dem Schlüssel
// ``branding`` (die App wendet es zur Laufzeit an, siehe src/lib/branding.ts):
//   colors: { accent, accent2, dark:{bg,surface,surface2,text,muted,border},
//             light:{…} }            – Hex "#rrggbb"
//   font:   "standard"|"system"|"serif"|"plakat"
//   logo:   "/data/uploads/branding-logo.<ext>?v=…"   (leer = Build-Logo)
//   title / shortName                – Browser-Titel bzw. Home-Bildschirm-Label
//   icons:  Versions-Token, wenn eigene PWA-Icons erzeugt wurden
//   manifest: true → App nutzt das dynamische /push/manifest.php

declare(strict_types=1);

// Build-Standardwerte – MÜSSEN mit src/styles/index.css übereinstimmen.
const BRANDING_DEFAULT_COLORS = [
    'accent'  => '#ffb300',
    'accent2' => '#e4572e',
    'dark'    => [
        'bg' => '#121212', 'surface' => '#1c1c1c', 'surface2' => '#262626',
        'text' => '#ffffff', 'muted' => '#b3b3b3', 'border' => '#2e2e2e',
    ],
    'light'   => [
        'bg' => '#f4f4f5', 'surface' => '#ffffff', 'surface2' => '#ececee',
        'text' => '#141416', 'muted' => '#5b5b60', 'border' => '#dedee2',
    ],
];

// Schrift-Sets (reine CSS-Stacks, keine Font-Dateien) – Schlüssel müssen mit
// src/lib/branding.ts (FONT_SETS) übereinstimmen. Werte = deutsche Labels
// (werden bei der Ausgabe durch cms_t() übersetzt).
const BRANDING_FONTS = [
    'standard' => 'Standard (Oswald & Inter, kondensiert)',
    'system'   => 'System (neutral, Gerätestandard)',
    'serif'    => 'Serif (klassisch, Georgia)',
    'plakat'   => 'Plakativ (fett, Arial Black)',
];

function cms_branding(): array
{
    $b = cms_read_config()['branding'] ?? null;
    return is_array($b) ? $b : [];
}

function cms_branding_write(array $b): bool
{
    $cfg = cms_read_config();
    // manifest-Flag ableiten: dynamisches Manifest nur nötig, wenn Titel,
    // Kurzname oder eigene Icons gesetzt sind (Farben allein brauchen es nicht).
    if (!empty($b['title']) || !empty($b['shortName']) || !empty($b['icons'])) {
        $b['manifest'] = true;
    } else {
        unset($b['manifest']);
    }
    if ($b === []) {
        unset($cfg['branding']);
    } else {
        $cfg['branding'] = $b;
    }
    return cms_write_config($cfg);
}

/** "#rrggbb" (auch Großschreibung) validieren → normalisiert oder null. */
function cms_branding_hex($v): ?string
{
    $v = strtolower(trim((string) $v));
    return preg_match('/^#[0-9a-f]{6}$/', $v) === 1 ? $v : null;
}

/** Farben aus dem POST einsammeln (volles Set; ungültige Werte → Standard). */
function cms_branding_colors_from_post(array $post): array
{
    $out = ['accent' => null, 'accent2' => null, 'dark' => [], 'light' => []];
    $out['accent']  = cms_branding_hex($post['c_accent'] ?? '') ?? BRANDING_DEFAULT_COLORS['accent'];
    $out['accent2'] = cms_branding_hex($post['c_accent2'] ?? '') ?? BRANDING_DEFAULT_COLORS['accent2'];
    foreach (['dark' => 'd', 'light' => 'l'] as $group => $prefix) {
        foreach (BRANDING_DEFAULT_COLORS[$group] as $key => $default) {
            $out[$group][$key] = cms_branding_hex($post["c_{$prefix}_{$key}"] ?? '') ?? $default;
        }
    }
    return $out;
}

/**
 * PWA-Icons aus einem hochgeladenen PNG erzeugen (GD): 192/512 transparent
 * eingepasst + 512er-maskable mit vollflächigem Hintergrund (Safe-Zone 80 %).
 * Rückgabe: Fehlertext (deutsch, für cms_t bereits übersetzte Aufrufer) oder null.
 */
function cms_branding_make_icons(string $tmpFile, string $maskBgHex): ?string
{
    if (!function_exists('imagecreatefrompng')) {
        return 'gd-missing';
    }
    $src = @imagecreatefrompng($tmpFile);
    if (!$src) {
        return 'png-invalid';
    }
    imagesavealpha($src, true);
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w < 192 || $h < 192) {
        imagedestroy($src);
        return 'png-too-small';
    }

    $dir = cms_uploads_dir();

    $contain = static function (int $size, float $fill) use ($src, $w, $h) {
        $dst = imagecreatetruecolor($size, $size);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, true);
        $scale = min($size * $fill / $w, $size * $fill / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        imagecopyresampled($dst, $src, intdiv($size - $nw, 2), intdiv($size - $nh, 2), 0, 0, $nw, $nh, $w, $h);
        imagesavealpha($dst, true);
        return $dst;
    };

    foreach ([192, 512] as $size) {
        $img = $contain($size, 1.0);
        $ok  = imagepng($img, "$dir/pwa-icon-$size.png");
        imagedestroy($img);
        if (!$ok) {
            imagedestroy($src);
            return 'write-failed';
        }
    }

    // Maskable: vollflächiger Hintergrund in der (dunklen) Markenfarbe.
    $mask = imagecreatetruecolor(512, 512);
    $hex  = cms_branding_hex($maskBgHex) ?? BRANDING_DEFAULT_COLORS['dark']['bg'];
    [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
    imagefill($mask, 0, 0, imagecolorallocate($mask, (int) $r, (int) $g, (int) $b));
    $scale = min(512 * 0.8 / $w, 512 * 0.8 / $h);
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    imagecopyresampled($mask, $src, intdiv(512 - $nw, 2), intdiv(512 - $nh, 2), 0, 0, $nw, $nh, $w, $h);
    $ok = imagepng($mask, "$dir/pwa-icon-maskable.png");
    imagedestroy($mask);
    imagedestroy($src);
    return $ok ? null : 'write-failed';
}

/** Branding-Logo-Dateien (alle Endungen) im Upload-Ordner entfernen. */
function cms_branding_delete_logo_files(): void
{
    $dir = cms_uploads_dir();
    foreach (CMS_UPLOAD_EXT as $ext) {
        @unlink("$dir/branding-logo.$ext");
    }
}

function cms_branding_delete_icon_files(): void
{
    $dir = cms_uploads_dir();
    foreach (['pwa-icon-192.png', 'pwa-icon-512.png', 'pwa-icon-maskable.png'] as $f) {
        @unlink("$dir/$f");
    }
}
