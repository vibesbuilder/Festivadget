<?php
// Admin UI (CMS) - shared helpers: auth (session + adminPasswordHash from
// push/config.php), CSRF, plus reading/writing the server-side JSON files
// under data/ (app-config.json, app-info.json, admin-news.json) which the app
// reads live (see useAppConfig.ts / useInfo.ts / useNewsFeed.ts).

declare(strict_types=1);

require_once __DIR__ . '/../db.php'; // push_config()
require_once __DIR__ . '/i18n.php';  // cms_t()/cms_lang() – CMS-Mehrsprachigkeit

// Harden the session cookie (keys/content hang off this session): no JS access,
// Lax against cross-site POSTs, secure on HTTPS (local/HTTP stays usable).
session_set_cookie_params([
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']),
    'samesite' => 'Lax',
]);
session_start();

// --- Authentication --------------------------------------------------------

function cms_logged_in(): bool
{
    return !empty($_SESSION['cms_admin']);
}

function cms_csrf_token(): string
{
    if (empty($_SESSION['cms_csrf'])) {
        $_SESSION['cms_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['cms_csrf'];
}

function cms_check_csrf(): bool
{
    return is_string($_POST['csrf'] ?? null)
        && hash_equals($_SESSION['cms_csrf'] ?? '', (string) $_POST['csrf']);
}

/** Handle login/logout. Returns an error message or null. */
function cms_handle_auth(): ?string
{
    $do = $_POST['do'] ?? '';
    if ($do === 'login') {
        require_once __DIR__ . '/../log.php';
        $hash = (string) (push_config()['adminPasswordHash'] ?? '');
        if ($hash !== '' && password_verify((string) ($_POST['password'] ?? ''), $hash)) {
            session_regenerate_id(true);
            $_SESSION['cms_admin'] = true;
            app_log('info', 'auth', 'CMS login successful.');
            return null;
        }
        app_log('warn', 'auth', 'CMS login failed (wrong password).');
        return cms_t('Falsches Passwort.');
    }
    if ($do === 'logout') {
        session_destroy();
        header('Location: index.php');
        exit;
    }
    return null;
}

// --- JSON files in the data/ folder ----------------------------------------

function cms_data_path(string $file): string
{
    $dir = (string) (push_config()['dataDir'] ?? (__DIR__ . '/../../data'));
    return rtrim($dir, "/\\") . '/' . $file;
}

/** Reads a JSON file from data/. null when missing or invalid. */
function cms_read_json(string $file): ?array
{
    $f = cms_data_path($file);
    if (!is_file($f)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($f), true);
    return is_array($data) ? $data : null;
}

/** Write atomically (temp + rename). false on error (e.g. write permissions). */
function cms_write_json(string $file, $data): bool
{
    $f = cms_data_path($file);
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($json === false) {
        return false;
    }
    $tmp = $f . '.tmp';
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        return false;
    }
    return rename($tmp, $f);
}

// --- App config (app-config.json) ------------------------------------------

function cms_read_config(): array
{
    return cms_read_json('app-config.json') ?? [];
}

function cms_write_config(array $cfg): bool
{
    return cms_write_json('app-config.json', $cfg);
}

// --- Info pages (app-info.json, seeded from info.json) ----------------------

/** Currently managed info pages: app-info.json, otherwise build state info.json. */
function cms_info_items(): array
{
    $items = cms_read_json('app-info.json');
    if ($items === null) {
        $items = cms_read_json('info.json') ?? [];
    }
    // Safeguard: array entries only (robust against broken override files).
    $items = array_values(array_filter($items, 'is_array'));
    usort($items, static fn($a, $b) => ((float) ($a['order'] ?? 0)) <=> ((float) ($b['order'] ?? 0)));
    return $items;
}

// --- Localized content fields (info/news: string OR {de:…,en:…}) ------------

const CMS_CONTENT_LANGS = ['de', 'en', 'fr', 'es'];

/** Value of a localized field for ONE language ('' when not maintained).
 *  A plain string counts as German (legacy data). */
function cms_loc_get($value, string $lang): string
{
    if (is_array($value)) {
        return trim((string) ($value[$lang] ?? ''));
    }
    return $lang === 'de' ? trim((string) ($value ?? '')) : '';
}

/** Display/slug label of a localized field (de -> en -> first value). */
function cms_loc_label($value): string
{
    if (!is_array($value)) {
        return trim((string) ($value ?? ''));
    }
    foreach (CMS_CONTENT_LANGS as $lang) {
        $text = trim((string) ($value[$lang] ?? ''));
        if ($text !== '') {
            return $text;
        }
    }
    return '';
}

/** POST field (array per language or legacy string) -> stored value:
 *  only German filled -> plain string (compatible with the legacy format),
 *  multiple languages -> language map, nothing filled -> ''. */
function cms_loc_from_post($raw)
{
    if (!is_array($raw)) {
        return trim((string) $raw);
    }
    $map = [];
    foreach (CMS_CONTENT_LANGS as $lang) {
        $text = trim((string) ($raw[$lang] ?? ''));
        if ($text !== '') {
            $map[$lang] = $text;
        }
    }
    if ($map === []) {
        return '';
    }
    if (array_keys($map) === ['de']) {
        return $map['de'];
    }
    return $map;
}

/** Simple, ASCII-oriented slug building for info IDs. */
function cms_slug(string $s): string
{
    $s = strtolower(trim($s));
    $map = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'á' => 'a', 'à' => 'a'];
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

// --- News (admin-news.json) -------------------------------------------------

function cms_tz(): string
{
    return (string) (push_config()['telegram']['tz'] ?? 'Europe/Vienna');
}

/** News (admin-news.json), descending by publishAt. Seeded from the build
 *  state (news.json) on first open; once saved, admin-news.json is the
 *  authoritative source and replaces the build state. */
function cms_news_items(): array
{
    $items = cms_read_json('admin-news.json');
    if ($items === null) {
        $items = cms_read_json('news.json') ?? []; // seed on first open
    }
    $items = array_values(array_filter($items, 'is_array'));
    usort($items, static fn($a, $b) => strcmp((string) ($b['publishAt'] ?? ''), (string) ($a['publishAt'] ?? '')));
    return $items;
}

/** ISO date -> value for <input type="datetime-local"> (Vienna time). */
function cms_dt_local(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    try {
        return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone(cms_tz()))->format('Y-m-d\TH:i');
    } catch (Throwable $e) {
        return '';
    }
}

/** datetime-local input -> zoned ISO (e.g. 2026-07-31T18:00:00+02:00). */
function cms_dt_iso(string $local): ?string
{
    $local = trim($local);
    if ($local === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($local, new DateTimeZone(cms_tz())))->format('c');
    } catch (Throwable $e) {
        return null;
    }
}

const CMS_NEWS_CATEGORIES = [
    'general' => 'Allgemein',
    'info'    => 'Info',
    'safety'  => 'Sicherheit',
    'lineup'  => 'Line-Up',
];

// --- Content (generic JSON editor per domain) -------------------------------

// Editable content domains. type = expected JSON shape (protection against typos).
const CMS_CONTENT_DOMAINS = [
    'festival' => ['label' => 'Festival-Eckdaten', 'type' => 'object'],
    'stages'   => ['label' => 'Bühnen',            'type' => 'array'],
    'artists'  => ['label' => 'Artists',           'type' => 'array'],
    'slots'    => ['label' => 'Timetable (Slots)', 'type' => 'array'],
    'pois'     => ['label' => 'Karten-Punkte (POIs)', 'type' => 'array'],
    'poi-categories' => ['label' => 'POI-Kategorien', 'type' => 'array'],
    'map'      => ['label' => 'Karte',             'type' => 'object'],
    'sponsors' => ['label' => 'Sponsoren',         'type' => 'array'],
    'tickets'  => ['label' => 'Tickets',           'type' => 'object'],
    'weather'  => ['label' => 'Wetter',            'type' => 'object'],
    'info'     => ['label' => 'Infos (auch eigener Tab)', 'type' => 'array'],
    // 'news' deliberately NOT generically editable - managed exclusively via the
    // convenient "News" tab (admin-news.json), which replaces the build state.
];

/** Current content of a domain as raw JSON: override (app-<d>.json) preferred, else build state. */
function cms_content_raw(string $domain): string
{
    $app = cms_data_path("app-$domain.json");
    if (is_file($app)) {
        return (string) file_get_contents($app);
    }
    $base = cms_data_path("$domain.json");
    return is_file($base) ? (string) file_get_contents($base) : '';
}

function cms_content_override_exists(string $domain): bool
{
    return is_file(cms_data_path("app-$domain.json"));
}

/** Current records of a domain (override preferred, else build state), decoded. */
function cms_domain_records(string $domain): array
{
    $app  = cms_data_path("app-$domain.json");
    $file = is_file($app) ? $app : cms_data_path("$domain.json");
    if (!is_file($file)) {
        return [];
    }
    $d = json_decode((string) file_get_contents($file), true);
    return is_array($d) ? $d : [];
}

/** String -> int (when integral) or float. */
function cms_to_number(string $v)
{
    $v = trim($v);
    if ($v === '') {
        return 0;
    }
    $f = (float) $v;
    return ($f == floor($f) && abs($f) < 1e15) ? (int) $f : $f;
}

const CMS_POI_TYPES     = ['stage', 'wc', 'food', 'drink', 'firstaid', 'atm', 'info', 'entrance', 'exit', 'camping', 'caravan', 'cashless', 'shuttle', 'merch', 'parking'];
const CMS_SPONSOR_TIERS = ['main', 'premium', 'partner', 'supporter'];

// Field schemas for the convenience forms. type: text|number|checkbox|select|textarea|list|image
const CMS_DOMAIN_FIELDS = [
    'stages' => [
        ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
        ['key' => 'shortName', 'label' => 'Kurzname', 'type' => 'text'],
        ['key' => 'color', 'label' => 'Farbe (Hex)', 'type' => 'text'],
        ['key' => 'order', 'label' => 'Reihenfolge', 'type' => 'number'],
    ],
    'sponsors' => [
        ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
        ['key' => 'logo', 'label' => 'Logo-Pfad', 'type' => 'image'],
        ['key' => 'tier', 'label' => 'Stufe', 'type' => 'select', 'options' => CMS_SPONSOR_TIERS],
        ['key' => 'url', 'label' => 'Website', 'type' => 'text'],
        ['key' => 'order', 'label' => 'Reihenfolge', 'type' => 'number'],
    ],
    'pois' => [
        ['key' => 'type', 'label' => 'Kategorie', 'type' => 'select', 'options' => CMS_POI_TYPES],
        ['key' => 'name', 'label' => 'Name', 'type' => 'loctext'],
        ['key' => 'icon', 'label' => 'Icon (Emoji ODER Bildpfad /data/uploads/…; leer = Kategorie-Icon)', 'type' => 'text'],
        ['key' => 'x', 'label' => 'X', 'type' => 'number'],
        ['key' => 'y', 'label' => 'Y', 'type' => 'number'],
    ],
    'poi-categories' => [
        ['key' => 'id', 'label' => 'ID (Schlüssel, z. B. parking)', 'type' => 'text'],
        ['key' => 'label', 'label' => 'Bezeichnung', 'type' => 'loctext'],
        ['key' => 'icon', 'label' => 'Icon (Emoji ODER Bildpfad /data/uploads/…)', 'type' => 'text'],
        ['key' => 'color', 'label' => 'Farbe (Hex, z. B. #9aa0a6)', 'type' => 'text'],
        ['key' => 'order', 'label' => 'Reihenfolge', 'type' => 'number'],
        ['key' => 'hidden', 'label' => 'Ausblenden (komplett von Karte + Filter)', 'type' => 'checkbox', 'omitWhenFalse' => true],
    ],
    'artists' => [
        ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
        ['key' => 'slug', 'label' => 'Slug (auto, falls leer)', 'type' => 'text'],
        ['key' => 'genres', 'label' => 'Genres (Komma-getrennt)', 'type' => 'list'],
        ['key' => 'country', 'label' => 'Land', 'type' => 'text'],
        ['key' => 'order', 'label' => 'Reihenfolge', 'type' => 'number'],
        ['key' => 'isHeadliner', 'label' => 'Headliner', 'type' => 'checkbox', 'omitWhenFalse' => true],
        ['key' => 'isDj', 'label' => 'DJ', 'type' => 'checkbox', 'omitWhenFalse' => true],
        ['key' => 'lineup', 'label' => 'Im Line-Up zeigen', 'type' => 'checkbox', 'default' => true],
        ['key' => 'image', 'label' => 'Bild-Pfad', 'type' => 'image'],
        ['key' => 'spotify', 'label' => 'Spotify', 'type' => 'text'],
        ['key' => 'youtube', 'label' => 'YouTube', 'type' => 'text'],
        ['key' => 'bio', 'label' => 'Bio (Markdown)', 'type' => 'loctextarea'],
    ],
];

/** Renders a single form field (HTML) depending on its type. */
function cms_field_input(string $iname, array $f, $value): string
{
    $n = cms_h($iname);
    switch ($f['type']) {
        case 'loctext':
        case 'loctextarea':
            // Localizable field: German prominent, en/fr/es in a collapsible block.
            // Stored as a plain string (only de) or a language map (see cms_loc_from_post).
            $area  = $f['type'] === 'loctextarea';
            $mk    = static function (string $lang) use ($n, $area, $value): string {
                $v = cms_h(cms_loc_get($value, $lang));
                return $area
                    ? '<textarea name="' . $n . '[' . $lang . ']">' . $v . '</textarea>'
                    : '<input type="text" name="' . $n . '[' . $lang . ']" value="' . $v . '">';
            };
            $hasTr = false;
            foreach (['en', 'fr', 'es'] as $tl) {
                if (cms_loc_get($value, $tl) !== '') {
                    $hasTr = true;
                }
            }
            $h = $mk('de');
            $h .= '<details' . ($hasTr ? ' open' : '') . '><summary class="muted">'
                . cms_h(cms_t('Übersetzungen (en/fr/es) – leer = englischer bzw. deutscher Fallback'))
                . '</summary>';
            foreach (['en', 'fr', 'es'] as $tl) {
                $h .= '<label class="fld" style="margin-top:.35rem"><span>' . $tl . '</span>' . $mk($tl) . '</label>';
            }
            return $h . '</details>';
        case 'number':
            $v = is_numeric($value) ? (string) $value : '';
            return '<input type="number" step="any" name="' . $n . '" value="' . cms_h($v) . '">';
        case 'textarea':
            return '<textarea name="' . $n . '">' . cms_h((string) $value) . '</textarea>';
        case 'checkbox':
            $checked = (($f['default'] ?? null) === true) ? (($value ?? true) !== false) : !empty($value);
            return '<input type="checkbox" name="' . $n . '" value="1"' . ($checked ? ' checked' : '') . '>';
        case 'select':
            $h = '<select name="' . $n . '">';
            foreach (($f['options'] ?? []) as $o) {
                $h .= '<option value="' . cms_h((string) $o) . '"' . ((string) $value === (string) $o ? ' selected' : '') . '>' . cms_h((string) $o) . '</option>';
            }
            return $h . '</select>';
        case 'list':
            $v = is_array($value) ? implode(', ', $value) : '';
            return '<input type="text" name="' . $n . '" value="' . cms_h($v) . '">';
        case 'image':
            return '<input type="text" name="' . $n . '" value="' . cms_h((string) $value) . '" placeholder="' . cms_h(cms_t('/data/uploads/… (Tab Bilder)')) . '">';
        default:
            return '<input type="text" name="' . $n . '" value="' . cms_h((string) $value) . '">';
    }
}

// --- Image upload (data/uploads, served under /data/uploads) ----------------

const CMS_UPLOAD_EXT     = ['webp', 'png', 'jpg', 'jpeg', 'svg'];
const CMS_UPLOAD_MAXSIZE = 5242880; // 5 MB

function cms_uploads_dir(): string
{
    $d = cms_data_path('uploads');
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

/** Clean the file name (no path traversal, ASCII), keep the extension. */
function cms_safe_filename(string $name): string
{
    $ext  = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    $ext  = preg_replace('/[^a-z0-9]+/', '', $ext) ?? '';
    $base = strtolower((string) pathinfo($name, PATHINFO_FILENAME));
    $base = strtr($base, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    $base = preg_replace('/[^a-z0-9._-]+/', '-', $base) ?? '';
    $base = trim($base, '-._');
    if ($base === '') {
        $base = 'bild';
    }
    return $base . ($ext !== '' ? '.' . $ext : '');
}

/** Existing uploads (newest first), as ['name'=>..., 'path'=>'/data/uploads/...']. */
function cms_list_uploads(): array
{
    $d = cms_data_path('uploads');
    if (!is_dir($d)) {
        return [];
    }
    $files = array_filter(
        scandir($d) ?: [],
        static fn($f) => $f !== '.' && $f !== '..' && is_file($d . '/' . $f)
    );
    rsort($files);
    return array_map(static fn($f) => ['name' => $f, 'path' => '/data/uploads/' . $f], $files);
}

// --- Menu definitions --------------------------------------------------------

// MORE menu entries. The keys MUST match src/routes/More.tsx.
const CMS_MORE_ITEMS = [
    'news'      => 'Newsfeed',
    'map'       => 'Karte',
    'info'      => 'Infos',
    'sponsors'  => 'Sponsoren',
    'tickets'   => 'Tickets',
    'contact'   => 'Kontakt',
    'impressum' => 'Impressum',
    'theme'     => 'Dark / Light',
    'language'  => 'Sprache',
];

// Known info icons (must be mapped in src/components/InfoIcon.tsx).
const CMS_INFO_ICONS = [
    'car', 'tent', 'credit-card', 'help-circle', 'gelaende', 'food',
    'kulinarik', 'drink', 'getraenke', 'shuttle', 'bus', 'platzordnung',
    'parken', 'parking',
];

function cms_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
