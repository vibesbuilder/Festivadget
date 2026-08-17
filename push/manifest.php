<?php
// Dynamisches PWA-Manifest (Paket Y, Kunden-Branding): Name, Farben und Icons
// kommen aus dem CMS (data/app-config.json → branding + hochgeladene Icons in
// data/uploads/pwa-icon-*.png). Die App tauscht den Manifest-Link zur Laufzeit
// auf diese Datei, sobald branding.manifest gesetzt ist; ohne PHP-Backend gilt
// weiter das statische manifest.webmanifest aus dem Build.

declare(strict_types=1);

require_once __DIR__ . '/db.php'; // push_config() → dataDir

$dataDir = rtrim((string) (push_config()['dataDir'] ?? (__DIR__ . '/../data')), "/\\");

/** JSON-Datei aus data/ lesen (oder []). */
$readJson = static function (string $file) use ($dataDir): array {
    $path = "$dataDir/$file";
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
};

$cfg      = $readJson('app-config.json');
$branding = is_array($cfg['branding'] ?? null) ? $cfg['branding'] : [];
$festival = $readJson('app-festival.json') ?: $readJson('festival.json');

$isHex = static fn($v): bool => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) === 1;

$name  = trim((string) ($branding['title'] ?? '')) ?: trim((string) ($festival['name'] ?? '')) ?: 'Festivadget';
$short = trim((string) ($branding['shortName'] ?? '')) ?: trim((string) ($festival['shortName'] ?? ''))
    ?: (function_exists('mb_substr') ? mb_substr($name, 0, 12) : substr($name, 0, 12));

$darkBg = $branding['colors']['dark']['bg'] ?? null;
$accent = $branding['colors']['accent'] ?? null;
$themeColor      = $isHex($darkBg) ? $darkBg : '#121212';
$backgroundColor = $themeColor;

// Eigene Icons (vom CMS erzeugt) bevorzugen, sonst die Build-Icons.
$uploads = "$dataDir/uploads";
$v       = (string) ($branding['icons'] ?? '');
$icons   = [];
if ($v !== '' && is_file("$uploads/pwa-icon-192.png") && is_file("$uploads/pwa-icon-512.png")) {
    $icons[] = ['src' => "/data/uploads/pwa-icon-192.png?v=$v", 'sizes' => '192x192', 'type' => 'image/png'];
    $icons[] = ['src' => "/data/uploads/pwa-icon-512.png?v=$v", 'sizes' => '512x512', 'type' => 'image/png'];
    if (is_file("$uploads/pwa-icon-maskable.png")) {
        $icons[] = [
            'src'     => "/data/uploads/pwa-icon-maskable.png?v=$v",
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ];
    }
} else {
    $icons[] = ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'];
    $icons[] = ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'];
    $icons[] = ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];
}

$manifest = [
    'name'             => $name,
    'short_name'       => $short,
    'description'      => 'Festival-Begleiter für mehrtägige Events – offline-fähig.',
    'lang'             => 'de',
    'theme_color'      => $themeColor,
    'background_color' => $backgroundColor,
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'start_url'        => '/',
    'scope'            => '/',
    'icons'            => $icons,
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache'); // Branding-Änderungen sollen sofort greifen
echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
