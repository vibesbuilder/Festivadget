<?php
// Festivadget Web-Installer (Joomla-Prinzip, Task #92.3): Erst-Einrichtung im
// Browser, ohne Build-Maschine. Prüft Voraussetzungen, fragt CMS-Passwort und
// optional MySQL (Web-Push) ab, erzeugt VAPID-Schlüssel serverseitig und
// schreibt push/config.php. Danach sperrt sich der Installer selbst
// (config.php vorhanden) und kann sich per Knopf selbst löschen.
//
// Sicherheit: Solange keine config.php existiert, ist die Installation offen –
// wie bei Joomla/WordPress gilt: Paket hochladen und SOFORT installieren.

declare(strict_types=1);

session_start();

$root       = dirname(__DIR__);            // Webroot (Release-Paket)
$pushDir    = $root . '/push';
$configFile = $pushDir . '/config.php';
$dataDir    = $root . '/data';

// --- Sprache (de/en) -----------------------------------------------------------

$lang = $_GET['lang'] ?? $_POST['lang'] ?? $_SESSION['lang']
    ?? (str_starts_with((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 'de') ? 'de' : 'en');
$lang = $lang === 'de' ? 'de' : 'en';
$_SESSION['lang'] = $lang;

$STRINGS = [
    'title'          => ['de' => 'Festivadget einrichten', 'en' => 'Set up Festivadget'],
    'intro'          => [
        'de' => 'Dieser Assistent richtet Festivadget auf diesem Webspace ein – ohne Build-Maschine. Die App selbst ist danach sofort nutzbar; Web-Push braucht zusätzlich MySQL und einen Cronjob (siehe docs/PUSH.md).',
        'en' => 'This wizard sets up Festivadget on this webspace – no build machine required. The app itself is ready right after; web push additionally needs MySQL and a cron job (see docs/PUSH.en.md).',
    ],
    'requirements'   => ['de' => 'Voraussetzungen', 'en' => 'Requirements'],
    'required'       => ['de' => 'erforderlich', 'en' => 'required'],
    'optional'       => ['de' => 'optional', 'en' => 'optional'],
    'ok'             => ['de' => 'OK', 'en' => 'OK'],
    'missing'        => ['de' => 'fehlt', 'en' => 'missing'],
    'php_version'    => ['de' => 'PHP 8.1 oder neuer', 'en' => 'PHP 8.1 or newer'],
    'webroot'        => [
        'de' => 'Installation liegt im Webroot einer (Sub-)Domain – Unterordner (z. B. /testapp/) werden nicht unterstützt; ggf. eine Subdomain auf dieses Verzeichnis zeigen lassen',
        'en' => 'Installed in the webroot of a (sub)domain – subfolders (e.g. /testapp/) are not supported; point a subdomain at this directory instead',
    ],
    'ext_openssl'    => ['de' => 'PHP-Erweiterung openssl (Web-Push-Schlüssel)', 'en' => 'PHP extension openssl (web push keys)'],
    'ext_pdo'        => ['de' => 'PHP-Erweiterung pdo_mysql (Web-Push & Statistik)', 'en' => 'PHP extension pdo_mysql (web push & statistics)'],
    'ext_gd'         => ['de' => 'PHP-Erweiterung gd (PWA-Icons im CMS)', 'en' => 'PHP extension gd (PWA icons in the CMS)'],
    'ext_mbstring'   => ['de' => 'PHP-Erweiterung mbstring (empfohlen)', 'en' => 'PHP extension mbstring (recommended)'],
    'ext_curl'       => ['de' => 'PHP-Erweiterung curl (Wetter, Telegram, Push-Versand)', 'en' => 'PHP extension curl (weather, Telegram, push delivery)'],
    'writable_data'  => ['de' => 'Ordner data/ beschreibbar (Inhalte & Uploads)', 'en' => 'Folder data/ writable (content & uploads)'],
    'writable_push'  => ['de' => 'Ordner push/ beschreibbar (config.php)', 'en' => 'Folder push/ writable (config.php)'],
    'req_fail'       => [
        'de' => 'Bitte zuerst die als „erforderlich" markierten Punkte beheben (Hoster-Einstellungen bzw. Datei-Rechte), dann diese Seite neu laden.',
        'en' => 'Please fix the items marked "required" first (hosting settings or file permissions), then reload this page.',
    ],
    'settings'       => ['de' => 'Einstellungen', 'en' => 'Settings'],
    'admin_pw'       => ['de' => 'CMS-Admin-Passwort (mind. 8 Zeichen)', 'en' => 'CMS admin password (min. 8 characters)'],
    'admin_pw2'      => ['de' => 'Passwort wiederholen', 'en' => 'Repeat password'],
    'push_section'   => ['de' => 'Web-Push (optional, braucht MySQL)', 'en' => 'Web push (optional, needs MySQL)'],
    'push_hint'      => [
        'de' => 'Leer lassen, um ohne Push zu installieren – lässt sich später in push/config.php nachtragen. Mit MySQL-Zugang werden die VAPID-Schlüssel automatisch erzeugt.',
        'en' => 'Leave empty to install without push – you can add it later in push/config.php. With MySQL credentials the VAPID keys are generated automatically.',
    ],
    'db_host'        => ['de' => 'MySQL-Host', 'en' => 'MySQL host'],
    'db_name'        => ['de' => 'Datenbank-Name', 'en' => 'Database name'],
    'db_user'        => ['de' => 'Datenbank-User', 'en' => 'Database user'],
    'db_pass'        => ['de' => 'Datenbank-Passwort', 'en' => 'Database password'],
    'contact'        => ['de' => 'Kontakt-E-Mail (VAPID-Subject für Push-Dienste)', 'en' => 'Contact e-mail (VAPID subject for push services)'],
    'install'        => ['de' => 'Jetzt installieren', 'en' => 'Install now'],
    'err_pw_short'   => ['de' => 'Das Passwort braucht mindestens 8 Zeichen.', 'en' => 'The password needs at least 8 characters.'],
    'err_pw_match'   => ['de' => 'Die Passwörter stimmen nicht überein.', 'en' => 'The passwords do not match.'],
    'err_db_fields'  => ['de' => 'Für Web-Push bitte Host, Datenbank-Name und User angeben (oder alles leer lassen).', 'en' => 'For web push please provide host, database name and user (or leave all empty).'],
    'err_db_connect' => ['de' => 'MySQL-Verbindung fehlgeschlagen:', 'en' => 'MySQL connection failed:'],
    'err_db_driver'  => ['de' => 'pdo_mysql fehlt – Web-Push ist ohne diese PHP-Erweiterung nicht möglich.', 'en' => 'pdo_mysql is missing – web push is not possible without this PHP extension.'],
    'err_csrf'       => ['de' => 'Sitzung abgelaufen – bitte erneut absenden.', 'en' => 'Session expired – please submit again.'],
    'err_write'      => ['de' => 'push/config.php konnte nicht geschrieben werden (Datei-Rechte prüfen).', 'en' => 'Could not write push/config.php (check file permissions).'],
    'warn_vapid'     => [
        'de' => 'VAPID-Schlüssel konnten nicht erzeugt werden – Web-Push bleibt aus. Später am PC mit „php push/vapid-keys.php" erzeugen und in push/config.php eintragen.',
        'en' => 'Could not generate VAPID keys – web push stays off. Generate them later on a PC with "php push/vapid-keys.php" and add them to push/config.php.',
    ],
    'done'           => ['de' => 'Installation abgeschlossen!', 'en' => 'Installation complete!'],
    'done_app'       => ['de' => 'Zur App', 'en' => 'Open the app'],
    'done_cms'       => ['de' => 'Zum CMS (Admin)', 'en' => 'Open the CMS (admin)'],
    'done_cron'      => [
        'de' => 'Web-Push ist eingerichtet. Für automatische Pushes noch einen Cronjob anlegen (stündlich oder öfter): push/cron-send.php?key=%s – Details in docs/PUSH.md.',
        'en' => 'Web push is configured. For automatic pushes add a cron job (hourly or more often): push/cron-send.php?key=%s – details in docs/PUSH.en.md.',
    ],
    'done_nopush'    => ['de' => 'Web-Push wurde nicht eingerichtet (kein MySQL angegeben).', 'en' => 'Web push was not configured (no MySQL given).'],
    'delete_now'     => ['de' => 'Installationsordner jetzt löschen', 'en' => 'Delete installer folder now'],
    'delete_hint'    => [
        'de' => 'Aus Sicherheitsgründen den Ordner install/ löschen – hier per Knopf oder per FTP.',
        'en' => 'For security, delete the install/ folder – via this button or via FTP.',
    ],
    'deleted'        => ['de' => 'Installationsordner gelöscht.', 'en' => 'Installer folder deleted.'],
    'delete_failed'  => ['de' => 'Konnte nicht alles löschen – bitte den Ordner install/ per FTP entfernen.', 'en' => 'Could not delete everything – please remove the install/ folder via FTP.'],
    'installed'      => ['de' => 'Bereits installiert', 'en' => 'Already installed'],
    'installed_hint' => [
        'de' => 'push/config.php existiert schon – dieser Installer tut nichts mehr. Einstellungen änderst du im CMS bzw. direkt in push/config.php.',
        'en' => 'push/config.php already exists – this installer is disabled. Change settings in the CMS or directly in push/config.php.',
    ],
];

/** Übersetzung (Schlüssel → aktive Sprache). */
function t(string $key): string
{
    global $STRINGS, $lang;
    return $STRINGS[$key][$lang] ?? $key;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// --- CSRF ----------------------------------------------------------------------

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrfOk = ($_POST['csrf'] ?? '') === $_SESSION['csrf'];

// --- Selbstlöschung (auch nach der Installation erlaubt) -------------------------

if (($_POST['do'] ?? '') === 'selfdelete' && $csrfOk) {
    // Erfolg = alle Dateien gelöscht; das rmdir des eigenen Ordners darf
    // scheitern (Windows hält das laufende Skript bis Request-Ende offen,
    // ein leerer Restordner ist harmlos).
    $ok = true;
    foreach (array_diff(scandir(__DIR__) ?: [], ['.', '..']) as $f) {
        $ok = @unlink(__DIR__ . '/' . $f) && $ok;
    }
    @rmdir(__DIR__);
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;padding:2rem">'
        . '<p>' . e($ok ? t('deleted') : t('delete_failed')) . '</p>'
        . '<p><a href="/">' . e(t('done_app')) . '</a></p>';
    exit;
}

// --- Voraussetzungen -------------------------------------------------------------

@mkdir($dataDir, 0775, true); // best effort – Prüfung unten

$pdoMysql = extension_loaded('pdo') && in_array('mysql', PDO::getAvailableDrivers(), true);
// Der App-Build verwendet absolute Pfade (/assets, /data, Service Worker):
// eine Unterordner-Installation liefert nur eine weiße Seite. SCRIPT_NAME
// muss daher direkt /install/... sein.
$inWebroot = str_starts_with((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/install/');
$checks = [
    // [Label-Key, erfüllt?, erforderlich?]
    ['php_version', PHP_VERSION_ID >= 80100, true],
    ['webroot', $inWebroot, true],
    ['writable_data', is_dir($dataDir) && is_writable($dataDir), true],
    ['writable_push', is_dir($pushDir) && is_writable($pushDir), true],
    ['ext_openssl', extension_loaded('openssl'), false],
    ['ext_pdo', $pdoMysql, false],
    ['ext_curl', extension_loaded('curl'), false],
    ['ext_gd', extension_loaded('gd'), false],
    ['ext_mbstring', extension_loaded('mbstring'), false],
];
$requiredOk = !array_filter($checks, fn($c) => $c[2] && !$c[1]);

// --- Installation -----------------------------------------------------------------

$errors  = [];
$warning = null;
$successCron = null; // cronSecret bei eingerichtetem Push (für den Hinweis)
$installed = is_file($configFile);
$justInstalled = false;

if (!$installed && ($_POST['do'] ?? '') === 'install' && $requiredOk) {
    if (!$csrfOk) {
        $errors[] = t('err_csrf');
    }
    $pw  = (string) ($_POST['admin_pw'] ?? '');
    $pw2 = (string) ($_POST['admin_pw2'] ?? '');
    if (strlen($pw) < 8) {
        $errors[] = t('err_pw_short');
    } elseif ($pw !== $pw2) {
        $errors[] = t('err_pw_match');
    }

    $db = [
        'host' => trim((string) ($_POST['db_host'] ?? '')),
        'name' => trim((string) ($_POST['db_name'] ?? '')),
        'user' => trim((string) ($_POST['db_user'] ?? '')),
        'pass' => (string) ($_POST['db_pass'] ?? ''),
    ];
    $wantsPush = $db['host'] !== '' || $db['name'] !== '' || $db['user'] !== '' || $db['pass'] !== '';
    if ($wantsPush && ($db['host'] === '' || $db['name'] === '' || $db['user'] === '')) {
        $errors[] = t('err_db_fields');
    }
    if ($wantsPush && !$pdoMysql) {
        $errors[] = t('err_db_driver');
    }
    if ($wantsPush && !$errors) {
        try {
            new PDO(
                "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
                $db['user'],
                $db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
        } catch (Throwable $ex) {
            $errors[] = t('err_db_connect') . ' ' . $ex->getMessage();
        }
    }

    $subject = trim((string) ($_POST['contact'] ?? ''));
    if ($subject === '' || !filter_var($subject, FILTER_VALIDATE_EMAIL)) {
        $subject = 'webmaster@' . ($_SERVER['SERVER_NAME'] ?? 'example.org');
    }

    if (!$errors) {
        // VAPID-Schlüssel nur mit Datenbank (ohne DB soll der Push-Schalter in
        // der App verborgen bleiben – vapid.php liefert dann einen leeren Key).
        $vapidPub = $vapidPriv = '';
        if ($wantsPush) {
            try {
                require_once $pushDir . '/vendor/autoload.php';
                $keys = Minishlink\WebPush\VAPID::createVapidKeys();
                $vapidPub  = $keys['publicKey'];
                $vapidPriv = $keys['privateKey'];
            } catch (Throwable $ex) {
                $warning = t('warn_vapid');
            }
        }

        $cronSecret = bin2hex(random_bytes(16));
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $q = static fn(string $s): string => var_export($s, true);

        $config = "<?php\n"
            . "// Von install/ erzeugt (" . date('Y-m-d H:i') . ") – Vorlage: config.example.php.\n"
            . "// Diese Datei enthält Geheimnisse: niemals committen oder veröffentlichen.\n\n"
            . "declare(strict_types=1);\n\n"
            . "return [\n"
            . "    'db' => [\n"
            . "        'host'    => {$q($db['host'])},\n"
            . "        'name'    => {$q($db['name'])},\n"
            . "        'user'    => {$q($db['user'])},\n"
            . "        'pass'    => {$q($db['pass'])},\n"
            . "        'charset' => 'utf8mb4',\n"
            . "    ],\n"
            . "    'vapid' => [\n"
            . "        'subject'    => {$q('mailto:' . $subject)},\n"
            . "        'publicKey'  => {$q($vapidPub)},\n"
            . "        'privateKey' => {$q($vapidPriv)},\n"
            . "    ],\n"
            . "    'adminPasswordHash' => {$q($hash)},\n"
            . "    'cronSecret' => {$q($cronSecret)},\n"
            . "    // Wetter: Fallback - im Normalfall im CMS-Tab Wetter pflegen.\n"
            . "    'weather' => [\n"
            . "        'provider'   => 'met_norway',\n"
            . "        'lat'        => 48.0,\n"
            . "        'lon'        => 14.0,\n"
            . "        'location'   => '',\n"
            . "        'station_id' => '',\n"
            . "    ],\n"
            . "    'dataDir' => __DIR__ . '/../data',\n"
            . "    'autoPushUpcoming' => true,\n"
            . "    'upcomingWindowMin' => 60,\n"
            . "    'autoPushNews'     => true,\n"
            . "    'pushNewsCategories' => ['safety'],\n"
            . "    'telegram' => [\n"
            . "        'botToken'       => '',\n"
            . "        'webhookSecret'  => '',\n"
            . "        'allowedUserIds' => [],\n"
            . "        'allowedChatIds' => [],\n"
            . "        'liveNewsFile'   => __DIR__ . '/../data/live-news.json',\n"
            . "        'tz'             => 'Europe/Vienna',\n"
            . "        'maxItems'       => 200,\n"
            . "        'pushAutoCategories' => ['safety'],\n"
            . "    ],\n"
            . "    'sources' => [\n"
            . "        'joomla'    => ['baseUrl' => '', 'token' => ''],\n"
            . "        'wordpress' => ['baseUrl' => '', 'user' => '', 'appPassword' => ''],\n"
            . "    ],\n"
            . "];\n";

        @mkdir($dataDir . '/uploads', 0775, true);
        if (file_put_contents($configFile, $config, LOCK_EX) === false) {
            $errors[] = t('err_write');
        } else {
            $installed = $justInstalled = true;
            $successCron = ($wantsPush && $vapidPub !== '') ? $cronSecret : null;
        }
    }
}

// --- Ausgabe ---------------------------------------------------------------------

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(t('title')) ?></title>
<style>
  :root { color-scheme: dark; }
  body { font-family: system-ui, sans-serif; background: #121212; color: #fff;
         max-width: 640px; margin: 0 auto; padding: 1.5rem; line-height: 1.5; }
  h1 { color: #ffb300; font-size: 1.6rem; }
  h2 { font-size: 1.1rem; margin-top: 1.5rem; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: .35rem .5rem; border-bottom: 1px solid #2e2e2e; }
  .ok { color: #7bd88f; } .bad { color: #e4572e; } .muted { color: #b3b3b3; font-size: .85rem; }
  input { width: 100%; box-sizing: border-box; background: #1c1c1c; color: #fff;
          border: 1px solid #2e2e2e; border-radius: 8px; padding: .55rem .7rem; margin: .2rem 0 .8rem; }
  button { background: #ffb300; color: #000; font-weight: 600; border: 0;
           border-radius: 999px; padding: .6rem 1.4rem; cursor: pointer; }
  button.secondary { background: transparent; color: #b3b3b3; border: 1px solid #2e2e2e; }
  .error { background: #3a1712; border: 1px solid #e4572e; border-radius: 8px; padding: .6rem .8rem; }
  .warn  { background: #3a2f12; border: 1px solid #ffb300; border-radius: 8px; padding: .6rem .8rem; }
  .card  { background: #1c1c1c; border: 1px solid #2e2e2e; border-radius: 12px; padding: 1rem 1.2rem; margin: 1rem 0; }
  a { color: #ffb300; }
  .lang { float: right; font-size: .85rem; }
</style>
</head>
<body>
<p class="lang"><a href="?lang=de">DE</a> · <a href="?lang=en">EN</a></p>
<h1><?= e(t('title')) ?></h1>

<?php if ($installed && !$justInstalled): ?>
  <div class="card">
    <h2><?= e(t('installed')) ?></h2>
    <p class="muted"><?= e(t('installed_hint')) ?></p>
    <p><a href="../">→ <?= e(t('done_app')) ?></a> · <a href="../push/cms/">→ <?= e(t('done_cms')) ?></a></p>
  </div>
  <div class="card">
    <p class="muted"><?= e(t('delete_hint')) ?></p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="do" value="selfdelete">
      <button type="submit"><?= e(t('delete_now')) ?></button>
    </form>
  </div>
<?php elseif ($justInstalled): ?>
  <div class="card">
    <h2><?= e(t('done')) ?></h2>
    <?php if ($warning): ?><p class="warn"><?= e($warning) ?></p><?php endif; ?>
    <?php if ($successCron): ?>
      <p><?= e(sprintf(t('done_cron'), $successCron)) ?></p>
    <?php else: ?>
      <p class="muted"><?= e(t('done_nopush')) ?></p>
    <?php endif; ?>
    <p><a href="../">→ <?= e(t('done_app')) ?></a> · <a href="../push/cms/">→ <?= e(t('done_cms')) ?></a></p>
  </div>
  <div class="card">
    <p class="muted"><?= e(t('delete_hint')) ?></p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="do" value="selfdelete">
      <button type="submit"><?= e(t('delete_now')) ?></button>
    </form>
  </div>
<?php else: ?>
  <p class="muted"><?= e(t('intro')) ?></p>

  <div class="card">
    <h2><?= e(t('requirements')) ?></h2>
    <table>
      <?php foreach ($checks as [$key, $ok, $required]): ?>
        <tr>
          <td><?= e(t($key)) ?> <span class="muted">(<?= e($required ? t('required') : t('optional')) ?>)</span></td>
          <td style="text-align:right" class="<?= $ok ? 'ok' : 'bad' ?>"><?= e($ok ? t('ok') : t('missing')) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php if (!$requiredOk): ?><p class="error"><?= e(t('req_fail')) ?></p><?php endif; ?>
  </div>

  <?php if ($requiredOk): ?>
    <form method="post" class="card">
      <h2><?= e(t('settings')) ?></h2>
      <?php foreach ($errors as $err): ?><p class="error"><?= e($err) ?></p><?php endforeach; ?>
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="do" value="install">
      <input type="hidden" name="lang" value="<?= e($lang) ?>">

      <label><?= e(t('admin_pw')) ?>
        <input type="password" name="admin_pw" minlength="8" required autocomplete="new-password">
      </label>
      <label><?= e(t('admin_pw2')) ?>
        <input type="password" name="admin_pw2" minlength="8" required autocomplete="new-password">
      </label>
      <label><?= e(t('contact')) ?>
        <input type="email" name="contact" value="<?= e((string) ($_POST['contact'] ?? '')) ?>" placeholder="webmaster@<?= e((string) ($_SERVER['SERVER_NAME'] ?? 'example.org')) ?>">
      </label>

      <h2><?= e(t('push_section')) ?></h2>
      <p class="muted"><?= e(t('push_hint')) ?></p>
      <label><?= e(t('db_host')) ?>
        <input name="db_host" value="<?= e((string) ($_POST['db_host'] ?? '')) ?>" placeholder="localhost">
      </label>
      <label><?= e(t('db_name')) ?>
        <input name="db_name" value="<?= e((string) ($_POST['db_name'] ?? '')) ?>">
      </label>
      <label><?= e(t('db_user')) ?>
        <input name="db_user" value="<?= e((string) ($_POST['db_user'] ?? '')) ?>">
      </label>
      <label><?= e(t('db_pass')) ?>
        <input type="password" name="db_pass" autocomplete="off">
      </label>

      <button type="submit"><?= e(t('install')) ?></button>
    </form>
  <?php endif; ?>
<?php endif; ?>
</body>
</html>
