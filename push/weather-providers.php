<?php
// Wetter-Provider (PHP-Portierung der in CrewCare verifizierten Adapter):
// GeoSphere Austria, OpenWeather, WeatherAPI.com, MET Norway.
//
// Vertrag: Jede weather_rows_*-Funktion liefert "unified rows", sortiert:
//   ['ts' => DateTimeImmutable (UTC), 'temp' => ?float (°C),
//    'precip' => float (mm IM Schritt der Zeile, nie kumulativ/negativ),
//    'base' => string (clear|partly|cloudy|overcast|fog|rain|heavy-rain|snow|
//                      heavy-snow|sleet|thunderstorm|unknown),
//    'windKmh' => ?float, 'windDeg' => ?float (meteorologisch, Herkunft)]
// Fehler: Exceptions mit verständlicher Meldung (weather.php fängt sie und
// liefert den letzten Cache bzw. 502). Kaputte Einzelzeilen werden übersprungen.

declare(strict_types=1);

// --- Registry (Dropdown-Labels, Attribution, benötigter Key) -----------------
const WEATHER_PROVIDERS = [
    'geosphere' => [
        'label'       => 'GeoSphere Austria',
        'hint'        => 'Österreich · kostenlos, kein Key (+ optional TAWES-Messwert)',
        'attribution' => 'Wetterdaten: GeoSphere Austria, CC BY 4.0',
        'key'         => null,
    ],
    'openweather' => [
        'label'       => 'OpenWeather',
        'hint'        => 'weltweit · API-Key nötig (Gratis-Tarif verfügbar), 3-h-Schritte',
        'attribution' => 'Wetterdaten: OpenWeather (openweathermap.org)',
        'key'         => 'api_key_openweather',
    ],
    'weatherapi' => [
        'label'       => 'WeatherAPI.com',
        'hint'        => 'weltweit · API-Key nötig (Gratis-Tarif verfügbar)',
        'attribution' => 'Wetterdaten: WeatherAPI.com',
        'key'         => 'api_key_weatherapi',
    ],
    'met_norway' => [
        'label'       => 'MET Norway',
        'hint'        => 'weltweit · kostenlos, kein Key',
        'attribution' => 'Wetterdaten: MET Norway (api.met.no), CC BY 4.0',
        'key'         => null,
    ],
];

function weather_provider_key(array $cfg): string
{
    $p = (string) ($cfg['provider'] ?? 'geosphere');
    return isset(WEATHER_PROVIDERS[$p]) ? $p : 'geosphere';
}

// Vom CMS-Tab „Wetter" geschriebene Einstellungen (per .htaccess NICHT öffentlich,
// enthält ggf. API-Keys). Merge-Reihenfolge: Defaults ← config.php ← diese Datei.
const WEATHER_SETTINGS_FILE = __DIR__ . '/weather-settings.json';

function weather_config(): array
{
    $cfg = [
        'provider'            => 'geosphere',
        'lat'                 => 47.928,
        'lon'                 => 14.083,
        'location'            => 'Inzersdorf im Kremstal',
        'station_id'          => '',
        'api_key_openweather' => '',
        'api_key_weatherapi'  => '',
    ];
    $file = __DIR__ . '/config.php';
    if (is_file($file)) {
        $legacy = require $file;
        if (is_array($legacy) && isset($legacy['weather']) && is_array($legacy['weather'])) {
            $cfg = array_merge($cfg, $legacy['weather']);
        }
    }
    if (is_file(WEATHER_SETTINGS_FILE)) {
        $saved = json_decode((string) @file_get_contents(WEATHER_SETTINGS_FILE), true);
        if (is_array($saved)) {
            $cfg = array_merge($cfg, $saved);
        }
    }
    return $cfg;
}

// --- HTTP (curl mit Timeout, Fallback Streams – Webserver nie lange blockieren) ---
function http_get_json(string $url): ?array
{
    $body = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Festivadget/1.0 (+https://github.com/vibesbuilder/Festivadget)',
        ]);
        $res  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $body = (is_string($res) && $code < 400) ? $res : null;
    } else {
        $ctx = stream_context_create(['http' => [
            'timeout'       => 15,
            'user_agent'    => 'Festivadget/1.0 (+https://github.com/vibesbuilder/Festivadget)',
            'ignore_errors' => true,
        ]]);
        $res    = @file_get_contents($url, false, $ctx);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
        $body = (is_string($res) && $status > 0 && $status < 400) ? $res : null;
    }
    if ($body === null) {
        return null;
    }
    $json = json_decode($body, true);
    return is_array($json) ? $json : null;
}

/** Dispatch: Provider aus der Config → unified rows. Wirft bei Fehlern. */
function weather_fetch_rows(array $cfg): array
{
    $lat = (float) $cfg['lat'];
    $lon = (float) $cfg['lon'];
    return match (weather_provider_key($cfg)) {
        'openweather' => weather_rows_openweather($lat, $lon, $cfg),
        'weatherapi'  => weather_rows_weatherapi($lat, $lon, $cfg),
        'met_norway'  => weather_rows_met_norway($lat, $lon),
        default       => weather_rows_geosphere($lat, $lon),
    };
}

function weather_require_key(array $cfg, string $field, string $label): string
{
    $key = trim((string) ($cfg[$field] ?? ''));
    if ($key === '') {
        throw new RuntimeException("API-Key für $label fehlt (Admin → Wetter).");
    }
    return $key;
}

function weather_num($v): ?float
{
    return (is_int($v) || is_float($v)) ? (float) $v : null;
}

// --- GeoSphere Austria (NWP nwp-v1-1h-2500m) ----------------------------------
const GEOSPHERE_FORECAST_URL = 'https://dataset.api.hub.geosphere.at/v1/timeseries/forecast/nwp-v1-1h-2500m';

// GeoSphere-sy-Code → Icon-Basis.
const GEOSPHERE_SY_BASE = [
    1 => 'clear', 2 => 'partly', 3 => 'partly', 4 => 'cloudy', 5 => 'overcast',
    6 => 'overcast', 7 => 'fog', 8 => 'fog', 9 => 'rain', 10 => 'rain',
    11 => 'heavy-rain', 12 => 'sleet', 13 => 'snow', 14 => 'snow',
    15 => 'heavy-snow', 16 => 'thunderstorm', 17 => 'thunderstorm',
    18 => 'rain', 19 => 'heavy-rain', 20 => 'thunderstorm',
];

function weather_rows_geosphere(float $lat, float $lon): array
{
    $raw = http_get_json(GEOSPHERE_FORECAST_URL . '?lat_lon=' . rawurlencode($lat . ',' . $lon)
        . '&parameters=t2m,rr_acc,sy,u10m,v10m&forecast_offset=0&output_format=geojson');
    if (!is_array($raw)) {
        throw new RuntimeException('GeoSphere nicht erreichbar.');
    }
    $timestamps = $raw['timestamps'] ?? null;
    $params     = $raw['features'][0]['properties']['parameters'] ?? null;
    if (!is_array($timestamps) || !is_array($params)) {
        throw new RuntimeException('GeoSphere: unerwartete Antwort.');
    }
    $t2m = $params['t2m']['data'] ?? [];
    $rr  = $params['rr_acc']['data'] ?? [];
    $sy  = $params['sy']['data'] ?? [];
    $u10 = $params['u10m']['data'] ?? [];
    $v10 = $params['v10m']['data'] ?? [];

    // WICHTIG: erst nach Zeit sortieren, DANN den kumulativen Niederschlag
    // differenzieren – unsortierte Reihen ergäben falsche Deltas (Referenz-
    // verhalten von CrewCare parse_forecast).
    $order = [];
    foreach ($timestamps as $i => $tsRaw) {
        $tsStr = trim((string) $tsRaw);
        if ($tsStr === '') {
            continue; // DateTimeImmutable('') wäre "jetzt", nicht "kaputt"
        }
        try {
            $order[] = [$i, new DateTimeImmutable($tsStr, new DateTimeZone('UTC'))];
        } catch (Throwable) {
            continue;
        }
    }
    usort($order, static fn ($a, $b) => $a[1] <=> $b[1]);

    $rows    = [];
    $prevAcc = 0.0;
    foreach ($order as [$i, $ts]) {
        // Stundenniederschlag aus dem AKKUMULIERTEN Verlauf (Reset-tolerant).
        $acc   = weather_num($rr[$i] ?? null) ?? $prevAcc;
        $delta = $acc - $prevAcc;
        if ($delta < 0) {
            $delta = max(0.0, $acc);
        }
        $prevAcc = $acc;

        $syCode = weather_num($sy[$i] ?? null);
        $u = weather_num($u10[$i] ?? null);
        $v = weather_num($v10[$i] ?? null);
        $windKmh = null;
        $windDeg = null;
        if ($u !== null && $v !== null) {
            $windKmh = hypot($u, $v) * 3.6;
            if ($u != 0.0 || $v != 0.0) {
                $windDeg = fmod(270.0 - rad2deg(atan2($v, $u)) + 360.0, 360.0);
            }
        }
        $rows[] = [
            'ts'      => $ts,
            'temp'    => weather_num($t2m[$i] ?? null),
            'precip'  => max(0.0, $delta),
            'base'    => $syCode !== null ? (GEOSPHERE_SY_BASE[(int) round($syCode)] ?? 'unknown') : 'unknown',
            'windKmh' => $windKmh,
            'windDeg' => $windDeg,
        ];
    }
    return $rows;
}

// --- OpenWeather (kostenloser "5 day / 3 hour forecast") -----------------------
// Doku: https://openweathermap.org/forecast5 + /weather-conditions.
const OPENWEATHER_URL = 'https://api.openweathermap.org/data/2.5/forecast';

// Condition-Code (weather[0].id) → Icon-Basis (vollständige Tabelle, verifiziert).
const OPENWEATHER_ID_BASE = [
    200 => 'thunderstorm', 201 => 'thunderstorm', 202 => 'thunderstorm',
    210 => 'thunderstorm', 211 => 'thunderstorm', 212 => 'thunderstorm',
    221 => 'thunderstorm', 230 => 'thunderstorm', 231 => 'thunderstorm', 232 => 'thunderstorm',
    300 => 'rain', 301 => 'rain', 302 => 'rain', 310 => 'rain', 311 => 'rain',
    312 => 'rain', 313 => 'rain', 314 => 'rain', 321 => 'rain',
    500 => 'rain', 501 => 'rain', 502 => 'heavy-rain', 503 => 'heavy-rain',
    504 => 'heavy-rain', 511 => 'sleet', 520 => 'rain', 521 => 'rain',
    522 => 'heavy-rain', 531 => 'rain',
    600 => 'snow', 601 => 'snow', 602 => 'heavy-snow', 611 => 'sleet',
    612 => 'sleet', 613 => 'sleet', 615 => 'sleet', 616 => 'sleet',
    620 => 'snow', 621 => 'snow', 622 => 'heavy-snow',
    701 => 'fog', 711 => 'fog', 721 => 'fog', 731 => 'fog', 741 => 'fog',
    751 => 'fog', 761 => 'fog', 762 => 'fog', 771 => 'fog', 781 => 'thunderstorm',
    800 => 'clear', 801 => 'partly', 802 => 'partly', 803 => 'cloudy', 804 => 'overcast',
];

// Fallback für künftige Codes: Hunderter-Gruppe.
const OPENWEATHER_GROUP_BASE = [2 => 'thunderstorm', 3 => 'rain', 5 => 'rain',
                                6 => 'snow', 7 => 'fog', 8 => 'cloudy'];

function weather_rows_openweather(float $lat, float $lon, array $cfg): array
{
    $key = weather_require_key($cfg, 'api_key_openweather', 'OpenWeather');
    $raw = http_get_json(OPENWEATHER_URL . '?' . http_build_query([
        'lat' => $lat, 'lon' => $lon, 'appid' => $key, 'units' => 'metric', 'lang' => 'de',
    ]));
    if (!is_array($raw) || !isset($raw['list']) || !is_array($raw['list'])) {
        throw new RuntimeException('OpenWeather nicht erreichbar oder API-Key ungültig.');
    }
    $rows = [];
    foreach ($raw['list'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $dt = weather_num($entry['dt'] ?? null);
        if ($dt === null || $dt < 0 || $dt > 4102444800) {
            continue; // fehlend oder absurd (nach 2100) → Zeile wertlos
        }
        // precip = rain["3h"] + snow["3h"] – mm IM 3-h-Schritt (laut Doku bereits
        // Schrittmenge, nicht kumulativ); fehlende Blöcke → 0. Erst auf Array
        // prüfen: ['3h'] auf einem String wäre eine "Illegal offset"-Warnung.
        $precip = 0.0;
        foreach (['rain', 'snow'] as $k) {
            $block = $entry[$k] ?? null;
            $val = is_array($block) ? weather_num($block['3h'] ?? null) : null;
            if ($val !== null) {
                $precip += max(0.0, $val);
            }
        }
        $code = $entry['weather'][0]['id'] ?? null;
        $base = 'unknown';
        if (is_numeric($code)) {
            $base = OPENWEATHER_ID_BASE[(int) $code]
                ?? (OPENWEATHER_GROUP_BASE[intdiv((int) $code, 100)] ?? 'unknown');
        }
        $speed = weather_num($entry['wind']['speed'] ?? null); // m/s → km/h
        $rows[] = [
            'ts'      => (new DateTimeImmutable('@' . (int) $dt)),
            'temp'    => weather_num($entry['main']['temp'] ?? null),
            'precip'  => $precip,
            'base'    => $base,
            'windKmh' => $speed !== null ? $speed * 3.6 : null,
            'windDeg' => weather_num($entry['wind']['deg'] ?? null),
        ];
    }
    usort($rows, static fn ($a, $b) => $a['ts'] <=> $b['ts']);
    return $rows;
}

// --- WeatherAPI.com (forecast.json, Free-Plan: 3 Tage) --------------------------
// Doku: https://www.weatherapi.com/docs/ (+ conditions.json, 60 Codes).
const WEATHERAPI_URL = 'https://api.weatherapi.com/v1/forecast.json';

// condition.code → Icon-Basis (vollständige 60er-Tabelle, verifiziert).
const WEATHERAPI_CODE_BASE = [
    1000 => 'clear', 1003 => 'partly', 1006 => 'cloudy', 1009 => 'overcast',
    1012 => 'fog', 1015 => 'fog', 1018 => 'fog', 1021 => 'fog', 1024 => 'fog',
    1027 => 'fog', 1030 => 'fog', 1033 => 'fog', 1036 => 'fog', 1039 => 'fog',
    1042 => 'fog', 1045 => 'fog', 1048 => 'fog', 1135 => 'fog', 1147 => 'fog',
    1063 => 'rain', 1150 => 'rain', 1153 => 'rain', 1180 => 'rain', 1183 => 'rain',
    1186 => 'rain', 1189 => 'rain', 1240 => 'rain',
    1192 => 'heavy-rain', 1195 => 'heavy-rain', 1243 => 'heavy-rain', 1246 => 'heavy-rain',
    1069 => 'sleet', 1072 => 'sleet', 1168 => 'sleet', 1171 => 'sleet',
    1198 => 'sleet', 1201 => 'sleet', 1204 => 'sleet', 1207 => 'sleet',
    1237 => 'sleet', 1249 => 'sleet', 1252 => 'sleet', 1261 => 'sleet', 1264 => 'sleet',
    1066 => 'snow', 1114 => 'snow', 1210 => 'snow', 1213 => 'snow', 1216 => 'snow',
    1219 => 'snow', 1255 => 'snow',
    1117 => 'heavy-snow', 1222 => 'heavy-snow', 1225 => 'heavy-snow', 1258 => 'heavy-snow',
    1087 => 'thunderstorm', 1273 => 'thunderstorm', 1276 => 'thunderstorm',
    1279 => 'thunderstorm', 1282 => 'thunderstorm',
];

function weather_rows_weatherapi(float $lat, float $lon, array $cfg): array
{
    $key = weather_require_key($cfg, 'api_key_weatherapi', 'WeatherAPI.com');
    $raw = http_get_json(WEATHERAPI_URL . '?' . http_build_query([
        'key' => $key, 'q' => $lat . ',' . $lon, 'days' => 3, 'alerts' => 'no',
        'aqi' => 'no', 'lang' => 'de',
    ]));
    $days = is_array($raw) ? ($raw['forecast']['forecastday'] ?? null) : null;
    if (!is_array($days)) {
        throw new RuntimeException('WeatherAPI.com nicht erreichbar oder API-Key ungültig.');
    }
    $rows = [];
    foreach ($days as $day) {
        $hours = is_array($day) && is_array($day['hour'] ?? null) ? $day['hour'] : [];
        foreach ($hours as $h) {
            if (!is_array($h)) {
                continue;
            }
            // time_epoch = Unix-SEKUNDEN (UTC); der lokale "time"-String wäre eine Falle.
            $epoch = weather_num($h['time_epoch'] ?? null);
            if ($epoch === null) {
                continue;
            }
            $precip = weather_num($h['precip_mm'] ?? null); // bereits mm in DIESER Stunde
            $code   = $h['condition']['code'] ?? null;
            $deg    = weather_num($h['wind_degree'] ?? null);
            $rows[] = [
                'ts'      => (new DateTimeImmutable('@' . (int) $epoch)),
                'temp'    => weather_num($h['temp_c'] ?? null),
                'precip'  => $precip !== null ? max(0.0, $precip) : 0.0,
                'base'    => is_numeric($code) ? (WEATHERAPI_CODE_BASE[(int) $code] ?? 'unknown') : 'unknown',
                'windKmh' => weather_num($h['wind_kph'] ?? null), // bereits km/h
                'windDeg' => $deg !== null ? fmod(fmod($deg, 360.0) + 360.0, 360.0) : null,
            ];
        }
    }
    usort($rows, static fn ($a, $b) => $a['ts'] <=> $b['ts']);
    return $rows;
}

// --- MET Norway (Locationforecast 2.0 compact) ----------------------------------
// Doku: https://api.met.no/weatherapi/locationforecast/2.0/documentation
// TOS: identifizierender User-Agent (setzt http_get_json immer) und Koordinaten
// auf max. 4 Dezimalstellen ("Do not use more than 4 decimals to avoid blocking").
const MET_NORWAY_URL = 'https://api.met.no/weatherapi/locationforecast/2.0/compact';

// Basis-Codes ohne Niederschlagsanteil; Rest per Muster (Gewitter hat Vorrang).
const MET_PLAIN_BASE = ['clearsky' => 'clear', 'fair' => 'partly',
                        'partlycloudy' => 'partly', 'cloudy' => 'cloudy', 'fog' => 'fog'];

function weather_met_symbol_base($symbolCode): string
{
    if (!is_string($symbolCode) || trim($symbolCode) === '') {
        return 'unknown';
    }
    $base = strtolower(trim($symbolCode));
    foreach (['_day', '_night', '_polartwilight'] as $suffix) {
        if (str_ends_with($base, $suffix)) {
            $base = substr($base, 0, -strlen($suffix));
            break;
        }
    }
    if (str_contains($base, 'thunder')) {
        return 'thunderstorm';
    }
    if (str_starts_with($base, 'heavyrain')) {
        return 'heavy-rain';
    }
    if (str_contains($base, 'rain')) {
        return 'rain';
    }
    if (str_starts_with($base, 'heavysnow')) {
        return 'heavy-snow';
    }
    if (str_contains($base, 'snow')) {
        return 'snow';
    }
    if (str_contains($base, 'sleet')) {
        return 'sleet';
    }
    return MET_PLAIN_BASE[$base] ?? 'unknown';
}

function weather_rows_met_norway(float $lat, float $lon): array
{
    $raw = http_get_json(MET_NORWAY_URL . '?' . http_build_query([
        'lat' => round($lat, 4), 'lon' => round($lon, 4),
    ]));
    $series = $raw['properties']['timeseries'] ?? null;
    if (!is_array($raw) || !is_array($series)) {
        throw new RuntimeException('MET Norway nicht erreichbar.');
    }
    $rows = [];
    foreach ($series as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $tsStr = trim((string) ($entry['time'] ?? ''));
        if ($tsStr === '') {
            continue; // DateTimeImmutable('') wäre "jetzt", nicht "kaputt"
        }
        try {
            $ts = new DateTimeImmutable($tsStr, new DateTimeZone('UTC'));
        } catch (Throwable) {
            continue;
        }
        $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];
        // next_1_hours bevorzugt (feiner), sonst next_6_hours – ein LEERES
        // next_1_hours-Objekt fällt wie in der Referenz auf next_6_hours zurück.
        // Zeilen ganz ohne Fenster (Reihen-Ende, nur "instant") überspringen.
        $w1 = $data['next_1_hours'] ?? null;
        $window = (is_array($w1) && $w1 !== []) ? $w1 : ($data['next_6_hours'] ?? null);
        if (!is_array($window) || $window === []) {
            continue;
        }
        $instant = is_array($data['instant']['details'] ?? null) ? $data['instant']['details'] : [];
        $speed   = weather_num($instant['wind_speed'] ?? null); // m/s → km/h
        // precipitation_amount = Menge IM Fenster der Zeile → bereits Schrittmenge.
        $precip  = weather_num($window['details']['precipitation_amount'] ?? null);
        $rows[]  = [
            'ts'      => $ts,
            'temp'    => weather_num($instant['air_temperature'] ?? null),
            'precip'  => $precip !== null ? max(0.0, $precip) : 0.0,
            'base'    => weather_met_symbol_base($window['summary']['symbol_code'] ?? null),
            'windKmh' => $speed !== null ? $speed * 3.6 : null,
            'windDeg' => weather_num($instant['wind_from_direction'] ?? null),
        ];
    }
    usort($rows, static fn ($a, $b) => $a['ts'] <=> $b['ts']);
    return $rows;
}
