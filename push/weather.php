<?php
// Wetter-Endpoint mit wählbarem Anbieter (GeoSphere Austria, OpenWeather,
// WeatherAPI.com, MET Norway – Adapter in weather-providers.php) und
// Lazy-Datei-Cache – KEIN Cronjob nötig. Der erste Abruf nach Ablauf der TTL
// holt frische Daten, alle anderen bekommen den Cache. Bei Anbieter-Ausfall
// wird der letzte Stand (als "stale") weiter ausgeliefert.
//
// Einstellungen (Priorität von unten nach oben):
// 1. Fallback-Defaults unten,
// 2. push/config.php ['weather' => ...],
// 3. push/weather-settings.json – wird vom CMS-Tab „Wetter" geschrieben
//    (liegt per .htaccess NICHT öffentlich, enthält ggf. API-Keys).

declare(strict_types=1);

require_once __DIR__ . '/weather-providers.php';

header('Content-Type: application/json; charset=utf-8');
// Öffentliche, unkritische Daten – CORS offen (auch für lokale Dev-Server).
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

const WEATHER_TTL_S       = 900;      // 15 min Cache
const WEATHER_STALE_S     = 3 * 3600; // ab 3 h ohne frische Daten: stale-Flag
const WEATHER_FAIL_RETRY_S = 120;     // nach Fehlschlag: 2 min kein neuer Fetch-Versuch
const STATION_URL      = 'https://dataset.api.hub.geosphere.at/v1/station/current/tawes-v1-10min';
// --- Aggregation: unified rows → current + 3 Tage mit Abschnitten -------------

// Priorität fürs dominante Symbol (höher = wichtiger).
const ICON_PRIO = [
    'thunderstorm' => 100, 'heavy-rain' => 90, 'heavy-snow' => 90, 'sleet' => 80,
    'rain' => 70, 'snow' => 70, 'fog' => 50, 'overcast' => 40, 'cloudy' => 35,
    'partly' => 20, 'clear' => 10, 'unknown' => 5,
];

function icon_variant(string $base, bool $night): string
{
    if ($base === 'clear' || $base === 'partly') {
        return $base . ($night ? '-night' : '-day');
    }
    return $base;
}

function wind_direction_text(float $deg): string
{
    $dirs = ['N', 'NO', 'O', 'SO', 'S', 'SW', 'W', 'NW'];
    // PHP-% kann negativ werden (anders als Python) → in [0..7] normalisieren.
    return $dirs[(((int) round($deg / 45.0)) % 8 + 8) % 8];
}

/** Aggregiert Stunden-Zeilen zu einem Tagesabschnitt (leer -> noData). */
function build_section(array $rows): array
{
    if ($rows === []) {
        return ['icon' => 'unknown', 'maxTemp' => null, 'precipitation' => 0.0,
                'windSpeed' => null, 'windDirectionText' => null, 'noData' => true];
    }
    $max = null;
    $precip = 0.0;
    $bestPrio = -1;
    $icon = 'unknown';
    $peakKmh = null;
    $peakDeg = null;
    foreach ($rows as $r) {
        if ($r['temp'] !== null) {
            $max = $max === null ? $r['temp'] : max($max, $r['temp']);
        }
        $precip += $r['precip'];
        $prio = ICON_PRIO[$r['base']] ?? 0;
        if ($prio > $bestPrio) {
            $bestPrio = $prio;
            $icon = $r['icon'];
        }
        if ($r['windKmh'] !== null && ($peakKmh === null || $r['windKmh'] > $peakKmh)) {
            $peakKmh = $r['windKmh'];
            $peakDeg = $r['windDeg'];
        }
    }
    return [
        'icon'              => $icon,
        'maxTemp'           => $max !== null ? round($max, 1) : null,
        'precipitation'     => round($precip, 1),
        'windSpeed'         => $peakKmh !== null ? (int) round($peakKmh) : null,
        'windDirectionText' => $peakDeg !== null ? wind_direction_text($peakDeg) : null,
        'noData'            => false,
    ];
}

/**
 * Unified rows (Provider) → current + 3 Tage (Kalendertag-Kennzahlen +
 * Abschnitte Morgens/Mittags/Abends/Nachts). Wie in CrewCare gilt:
 * „Nachts" (0–6 h) zeigt die FOLGENDE Nacht (aus dem Folgetag gezogen);
 * die Tages-Kennzahlen (min/max/precip/Symbol) bleiben über den Kalendertag.
 */
function weather_aggregate(array $unified, DateTimeZone $tz): ?array
{
    $now      = new DateTimeImmutable('now', $tz);
    $todayIso = $now->format('Y-m-d');
    $rows     = [];  // date => list of hour-rows (lokal angereichert)
    $current  = null;

    foreach ($unified as $r) {
        $local = $r['ts']->setTimezone($tz);
        $hour  = (int) $local->format('G');
        $night = $hour >= 20 || $hour < 6;
        $row   = [
            'hour'    => $hour,
            'temp'    => $r['temp'],
            'precip'  => $r['precip'],
            'base'    => $r['base'],
            'icon'    => icon_variant($r['base'], $night),
            'windKmh' => $r['windKmh'],
            'windDeg' => $r['windDeg'],
        ];
        // "Aktuell" = erste Zeile, die jetzt oder in der Zukunft liegt.
        if ($current === null && $local >= $now->modify('-30 minutes')) {
            $current = [
                'temp' => $r['temp'] !== null ? round($r['temp'], 1) : null,
                'icon' => $row['icon'],
            ];
        }
        $rows[$local->format('Y-m-d')][] = $row;
    }

    $today = new DateTimeImmutable($todayIso, $tz);
    $out   = [];
    for ($offset = 0; $offset < 3; $offset++) {
        $date    = $today->modify("+$offset days")->format('Y-m-d');
        $nextDay = $today->modify('+' . ($offset + 1) . ' days')->format('Y-m-d');
        $dayRows = $rows[$date] ?? [];

        // Tages-Kennzahlen über den Kalendertag.
        $min = null;
        $max = null;
        $precip = 0.0;
        $bestPrio = -1;
        $dayIcon = 'unknown';
        foreach ($dayRows as $r) {
            if ($r['temp'] !== null) {
                $min = $min === null ? $r['temp'] : min($min, $r['temp']);
                $max = $max === null ? $r['temp'] : max($max, $r['temp']);
            }
            $precip += $r['precip'];
            $prio = ICON_PRIO[$r['base']] ?? 0;
            if ($prio > $bestPrio) {
                $bestPrio = $prio;
                $dayIcon = icon_variant($r['base'], false); // Tages-Symbol: Tag-Variante
            }
        }

        $inRange = static fn (array $list, int $from, int $to): array =>
            array_values(array_filter($list, static fn ($r) => $r['hour'] >= $from && $r['hour'] < $to));

        $out[] = [
            'date'     => $date,
            'icon'     => $dayIcon,
            'max'      => $max !== null ? round($max, 1) : null,
            'min'      => $min !== null ? round($min, 1) : null,
            'precip'   => round($precip, 1),
            'noData'   => $dayRows === [],
            'sections' => [
                'morning' => build_section($inRange($dayRows, 6, 12)),
                'noon'    => build_section($inRange($dayRows, 12, 18)),
                'evening' => build_section($inRange($dayRows, 18, 24)),
                // Folgende Nacht: 0–6 h des NÄCHSTEN Kalendertags.
                'night'   => build_section($inRange($rows[$nextDay] ?? [], 0, 6)),
            ],
        ];
    }

    if ($out[0]['noData'] && $out[1]['noData'] && $out[2]['noData']) {
        return null;
    }
    return ['current' => $current, 'days' => $out];
}

/** Aktueller TAWES-Messwert (Lufttemperatur) – nur für GeoSphere sinnvoll. */
function fetch_station_temp(string $stationId): ?float
{
    if ($stationId === '') {
        return null;
    }
    $json = http_get_json(STATION_URL . '?parameters=TL&station_ids=' . rawurlencode($stationId)
        . '&output_format=geojson');
    $data = $json['features'][0]['properties']['parameters']['TL']['data'] ?? null;
    if (is_array($data) && $data !== []) {
        $last = end($data);
        return is_numeric($last) ? round((float) $last, 1) : null;
    }
    return null;
}

// --- Cache + Abruf ------------------------------------------------------------
$cfg      = weather_config();
$provider = weather_provider_key($cfg);

$cacheFile = __DIR__ . '/weather-cache.json';
$cached    = null;
if (is_file($cacheFile)) {
    $raw    = file_get_contents($cacheFile);
    $cached = is_string($raw) ? json_decode($raw, true) : null;
    $cached = is_array($cached) ? $cached : null;
    // Anbieter gewechselt → alter Cache ist wertlos (falsche Quelle/Attribution).
    if ($cached !== null && ($cached['provider'] ?? '') !== $provider) {
        $cached = null;
    }
}

$hasData  = $cached !== null && !empty($cached['ok']);
$cacheAge = $cached !== null ? time() - (int) ($cached['_cachedAt'] ?? 0) : PHP_INT_MAX;
if ($hasData && $cacheAge < WEATHER_TTL_S) {
    unset($cached['_cachedAt'], $cached['_retryAt']);
    echo json_encode($cached, JSON_UNESCAPED_UNICODE);
    exit;
}
// Retry-Sperre nach Fehlschlag: kurz NICHT erneut fetchen (Lastschutz).
if ($cached !== null && (int) ($cached['_retryAt'] ?? 0) > time()) {
    if ($hasData) {
        unset($cached['_cachedAt'], $cached['_retryAt']);
        $cached['stale'] = $cacheAge > WEATHER_STALE_S;
        echo json_encode($cached, JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Wetterdienst derzeit nicht erreichbar.']);
    }
    exit;
}
// Reine Fehl-Marker (ok=false) sind ab hier wertlos.
if (!$hasData) {
    $cached = null;
    $cacheAge = PHP_INT_MAX;
}

$tz = new DateTimeZone('Europe/Vienna');
$normalized = null;
$fetchError = null;
try {
    $normalized = weather_aggregate(weather_fetch_rows($cfg), $tz);
} catch (Throwable $e) {
    $fetchError = $e->getMessage();
}

if ($normalized === null) {
    // Fehlschlag protokollieren (nur wenn config.php + DB vorhanden; fail-silent).
    if (is_file(__DIR__ . '/config.php')) {
        require_once __DIR__ . '/log.php';
        $label = WEATHER_PROVIDERS[$provider]['label'];
        app_log('warn', 'weather', ($fetchError ?: "$label: leere Antwort.")
            . ($cached !== null ? ' – letzter Cache wird ausgeliefert.' : ' – kein Cache vorhanden (502).'));
    }
    // Negativ-Cache: bei Anbieter-Ausfall nicht mit JEDEM Request erneut einen
    // bis zu 20-s-Fetch anstoßen (öffentlich triggerbare Lastspitze).
    $marker = ($cached ?? ['ok' => false, 'provider' => $provider]) + [];
    $marker['_cachedAt']  = (int) ($cached['_cachedAt'] ?? 0); // Nutz-TTL NICHT verlängern
    $marker['_retryAt']   = time() + WEATHER_FAIL_RETRY_S;
    $mTmp = $cacheFile . '.tmp' . uniqid('', true);
    if (@file_put_contents($mTmp, json_encode($marker, JSON_UNESCAPED_UNICODE)) !== false) {
        @rename($mTmp, $cacheFile);
    }
    // Abruf fehlgeschlagen: letzten Cache (als stale) weiterreichen, sonst Fehler.
    if ($cached !== null) {
        unset($cached['_cachedAt'], $cached['_retryAt']);
        $cached['stale'] = $cacheAge > WEATHER_STALE_S;
        echo json_encode($cached, JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(502);
        // Öffentlich nur generisch – die konkrete Ursache steht im Protokoll
        // bzw. im CMS-Verbindungstest (keine internen Details an Anonyme).
        echo json_encode(['ok' => false, 'error' => 'Wetterdienst derzeit nicht erreichbar.']);
    }
    exit;
}

// TAWES-Messwert (optional, nur GeoSphere) überschreibt die "aktuelle" Temperatur.
if ($provider === 'geosphere') {
    $stationTemp = fetch_station_temp((string) $cfg['station_id']);
    if ($stationTemp !== null && is_array($normalized['current'])) {
        $normalized['current']['temp'] = $stationTemp;
    }
}

$payload = [
    'ok'          => true,
    'provider'    => $provider,
    'location'    => (string) $cfg['location'],
    'fetchedAt'   => gmdate('c'),
    'stale'       => false,
    'current'     => $normalized['current'],
    'days'        => $normalized['days'],
    'attribution' => WEATHER_PROVIDERS[$provider]['attribution'],
];

// Atomar schreiben (tmp + rename, eindeutiger Name gegen parallele Schreiber).
$tmp = $cacheFile . '.tmp' . uniqid('', true);
if (@file_put_contents($tmp, json_encode($payload + ['_cachedAt' => time()], JSON_UNESCAPED_UNICODE)) !== false) {
    @rename($tmp, $cacheFile);
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
