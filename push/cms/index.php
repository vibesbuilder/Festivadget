<?php
// Admin-UI (CMS). Tabs: Einstellungen (Start), MEHR-Menü, Infos (CRUD), News, Push, …
// Schreibt server-eigene JSONs unter data/, die die App live einliest.
// UI-Texte laufen durch cms_t() (Deutsch = Quellsprache, en/fr/es via cms/i18n.php).

declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/importer.php';
require_once __DIR__ . '/branding.php';
require_once __DIR__ . '/update.php';

$error    = cms_handle_auth();
$notice   = null;
$uploaded = null; // Pfad der zuletzt hochgeladenen Datei (für Anzeige)
$importReport = null; // Ergebnis von „Jetzt importieren"

// --- CSV-Export: anonymer Abo-Verlauf (nur eingeloggt) ---------------------
if (cms_logged_in() && ($_GET['export'] ?? '') === 'push-stats') {
    try {
        $rows = push_db()->query(
            'SELECT taken_at, total, c_info, c_lineup, c_general FROM push_stats ORDER BY taken_at ASC'
        )->fetchAll();
    } catch (Throwable $e) {
        $rows = [];
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="push-abo-verlauf.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM für Excel
    fputcsv($out, [cms_t('Zeitpunkt'), cms_t('Abos gesamt'), cms_t('Infos'), cms_t('Line-Up'), cms_t('Allgemein')]);
    foreach ($rows as $r) {
        fputcsv($out, [$r['taken_at'], (int) $r['total'], (int) $r['c_info'], (int) $r['c_lineup'], (int) $r['c_general']]);
    }
    fclose($out);
    exit;
}

// --- POST-Aktionen ---------------------------------------------------------
if (cms_logged_in() && ($_POST['do'] ?? '') !== '' && $_POST['do'] !== 'logout' && $_POST['do'] !== 'login') {
    if (!cms_check_csrf()) {
        $error = cms_t('Sicherheits-Token ungültig – bitte erneut speichern.');
    } else {
        switch ($_POST['do']) {
            case 'save_weather':
                // Wetter-Einstellungen → push/weather-settings.json (per .htaccess
                // geschützt, enthält ggf. API-Keys). Überschreibt config.php['weather'].
                require_once __DIR__ . '/../weather-providers.php';
                $prov = (string) ($_POST['wprovider'] ?? 'geosphere');
                if (!isset(WEATHER_PROVIDERS[$prov])) {
                    $prov = 'geosphere';
                }
                $num = static function ($v, float $min, float $max): ?float {
                    $s = str_replace(',', '.', trim((string) $v));
                    if (!is_numeric($s)) {
                        return null; // sonst würde z. B. "abc" still zu 0.0
                    }
                    $f = (float) $s;
                    return ($f >= $min && $f <= $max) ? $f : null;
                };
                $lat = $num($_POST['wlat'] ?? '', -90, 90);
                $lon = $num($_POST['wlon'] ?? '', -180, 180);
                if ($lat === null || $lon === null) {
                    $error = cms_t('Ungültige Koordinaten (Breite -90..90, Länge -180..180).');
                    break;
                }
                $settings = [
                    'provider'            => $prov,
                    'lat'                 => $lat,
                    'lon'                 => $lon,
                    'location'            => trim((string) ($_POST['wlocation'] ?? '')),
                    'station_id'          => trim((string) ($_POST['wstation'] ?? '')),
                    'api_key_openweather' => trim((string) ($_POST['wkey_ow'] ?? '')),
                    'api_key_weatherapi'  => trim((string) ($_POST['wkey_wa'] ?? '')),
                ];
                $tmp = WEATHER_SETTINGS_FILE . '.tmp';
                $ok  = @file_put_contents($tmp, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false
                    && @rename($tmp, WEATHER_SETTINGS_FILE);
                if (!$ok) {
                    $error = cms_t('Speichern fehlgeschlagen – Schreibrechte des push-Ordners prüfen.');
                    break;
                }
                @unlink(__DIR__ . '/../weather-cache.json'); // alter Anbieter-Cache ist wertlos
                require_once __DIR__ . '/../log.php';
                app_log('info', 'weather', 'Einstellungen gespeichert (Anbieter: ' . WEATHER_PROVIDERS[$prov]['label'] . ').');
                $notice = cms_t('Wetter-Einstellungen gespeichert (Cache geleert).');
                // Optional: gleich live testen (zweiter Submit-Button).
                if (!empty($_POST['test'])) {
                    try {
                        $rows = weather_fetch_rows(weather_config());
                        $first = null;
                        foreach ($rows as $r) {
                            if ($r['temp'] !== null) {
                                $first = $r;
                                break;
                            }
                        }
                        $notice .= ' ' . cms_t('Verbindungstest OK: %1$d Vorhersage-Zeilen von %2$s.', count($rows), WEATHER_PROVIDERS[$prov]['label']);
                        if ($first !== null) {
                            $notice .= ' ' . cms_t('Nächste Temperatur: %s °C.', (string) round($first['temp'], 1));
                        }
                    } catch (Throwable $e) {
                        $error = cms_t('Verbindungstest fehlgeschlagen: %s', $e->getMessage());
                    }
                }
                break;

            case 'clear_weather_cache':
                $notice = @unlink(__DIR__ . '/../weather-cache.json')
                    ? cms_t('Wetter-Cache geleert – der nächste App-Abruf holt frische Daten.')
                    : cms_t('Kein Wetter-Cache vorhanden.');
                break;

            case 'reset_stats':
                // Nutzungsstatistik komplett zurücksetzen (z. B. nach der Testphase).
                require_once __DIR__ . '/../stats-db.php';
                require_once __DIR__ . '/../log.php';
                try {
                    $pdo = stats_db();
                    $n = (int) $pdo->query('SELECT COUNT(*) FROM app_stats_events')->fetchColumn();
                    $pdo->exec('DELETE FROM app_stats_events');
                    app_log('info', 'stats', "Statistik im CMS zurückgesetzt ($n Einträge gelöscht).");
                    $notice = cms_t('Statistik zurückgesetzt (%d Einträge gelöscht).', $n);
                } catch (Throwable $e) {
                    $error = cms_t('Zurücksetzen fehlgeschlagen: %s', $e->getMessage());
                }
                break;

            case 'clear_log':
                // Protokoll leeren (unabhängig von der 90-Tage-Bereinigung).
                require_once __DIR__ . '/../log.php';
                try {
                    $pdo = stats_db();
                    app_log_init($pdo);
                    $n = (int) $pdo->query('SELECT COUNT(*) FROM app_log')->fetchColumn();
                    $pdo->exec('DELETE FROM app_log');
                    app_log('info', 'stats', "Protokoll im CMS geleert ($n Einträge gelöscht).");
                    $notice = cms_t('Protokoll geleert (%d Einträge gelöscht).', $n);
                } catch (Throwable $e) {
                    $error = cms_t('Leeren fehlgeschlagen: %s', $e->getMessage());
                }
                break;

            case 'apply_update':
                $file = $_FILES['file'] ?? null;
                if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    $error = cms_t('Keine Datei gewählt.');
                    break;
                }
                if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
                    $error = cms_t('Upload-Fehler (Code %d).', (int) $file['error']);
                    break;
                }
                if (strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'zip') {
                    $error = cms_t('Nur ZIP-Dateien (festivadget-update-v*.zip) erlaubt.');
                    break;
                }
                // PharData-Fallback braucht die .zip-Endung → erst umkopieren.
                $tmpZip = sys_get_temp_dir() . '/festivadget-update-' . bin2hex(random_bytes(6)) . '.zip';
                if (!move_uploaded_file((string) $file['tmp_name'], $tmpZip)) {
                    $error = cms_t('Upload konnte nicht gespeichert werden.');
                    break;
                }
                $result = cms_update_apply($tmpZip);
                @unlink($tmpZip);
                if ($result['ok']) {
                    $notice = cms_t(
                        'Update eingespielt: %1$d Dateien aktualisiert, %2$d geschützte übersprungen. Installierte Version: %3$s.',
                        $result['updated'],
                        $result['skipped'],
                        $result['version'] !== '' ? $result['version'] : '–'
                    );
                } else {
                    $updateErrors = [
                        'zip-invalid'        => cms_t('ZIP-Datei konnte nicht gelesen werden.'),
                        'zip-missing'        => cms_t('Am Server fehlt die PHP-Erweiterung zip (und phar).'),
                        'not-update-package' => cms_t('Das ist kein Festivadget-Update-Paket.'),
                        'full-package'       => cms_t('Das ist das VOLLE Release-Paket (enthält data/) – zum Updaten bitte das Update-Paket (festivadget-update-v*.zip) verwenden.'),
                        'bad-path'           => cms_t('Unsicherer Pfad im Paket – Update abgebrochen.'),
                        'read-failed'        => cms_t('Paket unvollständig lesbar – Update abgebrochen.'),
                        'write-failed'       => cms_t('Schreiben fehlgeschlagen (Datei-Rechte prüfen) – Update unvollständig!'),
                    ];
                    $error = $updateErrors[$result['error']] ?? (string) $result['error'];
                }
                break;

            case 'save_branding':
                // Titel/Kurzname/Schrift/Farben (Logo + Icons haben eigene Aktionen).
                $b = cms_branding();
                $title = trim((string) ($_POST['btitle'] ?? ''));
                if ($title === '') {
                    unset($b['title']);
                } else {
                    $b['title'] = $title;
                }
                $short = trim((string) ($_POST['bshort'] ?? ''));
                if ($short === '') {
                    unset($b['shortName']);
                } else {
                    $b['shortName'] = function_exists('mb_substr') ? mb_substr($short, 0, 12) : substr($short, 0, 12);
                }
                $font = (string) ($_POST['bfont'] ?? 'standard');
                if (!isset(BRANDING_FONTS[$font]) || $font === 'standard') {
                    unset($b['font']);
                } else {
                    $b['font'] = $font;
                }
                if (!empty($_POST['resetColors'])) {
                    unset($b['colors']);
                } else {
                    $b['colors'] = cms_branding_colors_from_post($_POST);
                }
                $notice = cms_branding_write($b)
                    ? cms_t('Branding gespeichert. Übernahme in der App binnen ~2 Minuten.')
                    : cms_t('Speichern fehlgeschlagen – Schreibrechte des data-Ordners prüfen.');
                break;

            case 'upload_branding_logo':
                $file = $_FILES['file'] ?? null;
                if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    $error = cms_t('Keine Datei gewählt.');
                    break;
                }
                if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
                    $error = cms_t('Upload-Fehler (Code %d).', (int) $file['error']);
                    break;
                }
                if (($file['size'] ?? 0) > CMS_UPLOAD_MAXSIZE) {
                    $error = cms_t('Datei zu groß (max. 5 MB).');
                    break;
                }
                $ext = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, CMS_UPLOAD_EXT, true)) {
                    $error = cms_t('Nur %s erlaubt.', implode(' / ', CMS_UPLOAD_EXT));
                    break;
                }
                cms_branding_delete_logo_files();
                if (!move_uploaded_file((string) $file['tmp_name'], cms_uploads_dir() . "/branding-logo.$ext")) {
                    $error = cms_t('Konnte Datei nicht speichern.');
                    break;
                }
                $b = cms_branding();
                $b['logo'] = "/data/uploads/branding-logo.$ext?v=" . bin2hex(random_bytes(4));
                cms_branding_write($b);
                $notice = cms_t('Logo hochgeladen. Übernahme in der App binnen ~2 Minuten.');
                break;

            case 'delete_branding_logo':
                cms_branding_delete_logo_files();
                $b = cms_branding();
                unset($b['logo']);
                cms_branding_write($b);
                $notice = cms_t('Logo entfernt – die App zeigt wieder das mitgelieferte Logo.');
                break;

            case 'upload_branding_icon':
                $file = $_FILES['file'] ?? null;
                if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    $error = cms_t('Keine Datei gewählt.');
                    break;
                }
                if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
                    $error = cms_t('Upload-Fehler (Code %d).', (int) $file['error']);
                    break;
                }
                if (($file['size'] ?? 0) > CMS_UPLOAD_MAXSIZE) {
                    $error = cms_t('Datei zu groß (max. 5 MB).');
                    break;
                }
                if (strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'png') {
                    $error = cms_t('Nur PNG erlaubt.');
                    break;
                }
                $b = cms_branding();
                $maskBg = (string) ($b['colors']['dark']['bg'] ?? BRANDING_DEFAULT_COLORS['dark']['bg']);
                $err = cms_branding_make_icons((string) $file['tmp_name'], $maskBg);
                if ($err !== null) {
                    $error = match ($err) {
                        'gd-missing'    => cms_t('Die PHP-Erweiterung GD fehlt auf dem Server – Icons können nicht erzeugt werden.'),
                        'png-invalid'   => cms_t('PNG konnte nicht gelesen werden.'),
                        'png-too-small' => cms_t('Bild zu klein – mindestens 192×192 Pixel.'),
                        default         => cms_t('Speichern fehlgeschlagen – Schreibrechte des data-Ordners prüfen.'),
                    };
                    break;
                }
                $b['icons'] = bin2hex(random_bytes(4));
                cms_branding_write($b);
                $notice = cms_t('App-Icons erzeugt (192, 512, maskable). Übernahme in der App binnen ~2 Minuten.');
                break;

            case 'delete_branding_icon':
                cms_branding_delete_icon_files();
                $b = cms_branding();
                unset($b['icons']);
                cms_branding_write($b);
                $notice = cms_t('Icons entfernt – es gelten wieder die mitgelieferten App-Icons.');
                break;

            case 'save_home_video':
                // Intro-Video auf Home (Branding-Tab): Link/FTP oder Microsoft-Cloud.
                $url = trim((string) ($_POST['hv_url'] ?? ''));
                $source = ($_POST['hv_source'] ?? 'link') === 'mscloud' ? 'mscloud' : 'link';
                $enabled = !empty($_POST['hv_enabled']);
                if ($url !== '' && !preg_match('#^(https?://|/)#', $url)) {
                    $error = cms_t('Video-URL muss mit https:// (oder /data/uploads/…) beginnen.');
                    break;
                }
                $cfg = cms_read_config();
                if ($url === '') {
                    unset($cfg['homeVideo']);
                } else {
                    $cfg['homeVideo'] = ['url' => $url, 'source' => $source, 'enabled' => $enabled];
                }
                if (!cms_write_config($cfg)) {
                    $error = cms_t('Speichern fehlgeschlagen – Schreibrechte des data-Ordners prüfen.');
                    break;
                }
                $notice = $url === ''
                    ? cms_t('Intro-Video entfernt.')
                    : cms_t('Intro-Video gespeichert (%s).', $enabled ? cms_t('aktiv') : cms_t('deaktiviert'));
                break;

            case 'save_more':
                $checked = array_keys($_POST['more'] ?? []);
                $hidden  = array_values(array_diff(array_keys(CMS_MORE_ITEMS), $checked));
                $cfg = cms_read_config();
                $cfg['moreHidden'] = $hidden;
                $notice = cms_write_config($cfg)
                    ? cms_t('Gespeichert. Die App übernimmt es binnen ~2 Minuten (oder beim Neuladen).')
                    : cms_t('Speichern fehlgeschlagen – Schreibrechte des data-Ordners prüfen.');
                break;

            case 'save_info':
                $out = [];
                foreach (($_POST['items'] ?? []) as $row) {
                    if (!empty($row['delete'])) {
                        continue;
                    }
                    $title = trim((string) ($row['title'] ?? ''));
                    if ($title === '') {
                        continue; // leere/ungenutzte Zeile überspringen
                    }
                    $id = trim((string) ($row['id'] ?? '')) ?: cms_slug($title);
                    $icon = trim((string) ($row['icon'] ?? ''));
                    $item = [
                        'id'    => $id,
                        'title' => $title,
                        'order' => (float) ($row['order'] ?? 0),
                        'body'  => (string) ($row['body'] ?? ''),
                    ];
                    if ($icon !== '') {
                        $item['icon'] = $icon;
                    }
                    if (!empty($row['hidden'])) {
                        $item['hidden'] = true;
                    }
                    if (!empty($row['faq'])) {
                        $item['faq'] = true; // Body als Frage/Antwort-Accordion anzeigen
                    }
                    // Quelle je Eintrag (manual = getippte Werte; joomla/wordpress = Import).
                    $src = (string) ($row['source'] ?? 'manual');
                    if (in_array($src, ['joomla', 'wordpress'], true)) {
                        $item['source'] = $src;
                        $loc = trim((string) ($row['sourceLocator'] ?? ''));
                        if ($loc !== '') {
                            $item['sourceLocator'] = $loc;
                        }
                    }
                    $out[] = $item;
                }
                usort($out, static fn($a, $b) => ((float) $a['order']) <=> ((float) $b['order']));
                $notice = cms_write_json('app-info.json', $out)
                    ? cms_t('Infos gespeichert. Übernahme in der App binnen ~2 Minuten.')
                    : cms_t('Speichern fehlgeschlagen – Schreibrechte des data-Ordners prüfen.');
                break;

            case 'save_settings':
                // CMS-Sprache (cms-settings.json, wirkt sofort auf diese Antwort).
                $cl = (string) ($_POST['cmsLang'] ?? '');
                if ($cl !== '' && $cl !== cms_lang()) {
                    cms_set_lang($cl);
                }
                $cfg = cms_read_config();
                $lim = trim((string) ($_POST['lineupImageLimit'] ?? ''));
                if ($lim === '') {
                    unset($cfg['lineupImageLimit']);
                } else {
                    $cfg['lineupImageLimit'] = max(0, (int) $lim);
                }
                $cfg['background'] = !empty($_POST['background']);
                // Eigenes Hintergrundbild (nur Uploads-Pfade zulassen; leer = Build-Grafik).
                $bgi = trim((string) ($_POST['backgroundImage'] ?? ''));
                if ($bgi !== '' && preg_match('#^/data/uploads/[A-Za-z0-9._-]+$#', $bgi)) {
                    $cfg['backgroundImage'] = $bgi;
                } else {
                    unset($cfg['backgroundImage']);
                }
                // Home-Kopf (Festivalname + Datum) ein-/ausblenden.
                $cfg['homeHeader'] = !empty($_POST['homeHeader']);
                // Ziele der MEHR-Eintraege Kontakt/Impressum (leer = ausgeblendet).
                foreach (['contactUrl', 'impressumUrl'] as $urlKey) {
                    $u = trim((string) ($_POST[$urlKey] ?? ''));
                    if ($u !== '' && preg_match('#^https?://#i', $u)) {
                        $cfg[$urlKey] = $u;
                    } else {
                        unset($cfg[$urlKey]);
                    }
                }
                $td = (string) ($_POST['themeDefault'] ?? '');
                if (in_array($td, ['dark', 'light'], true)) {
                    $cfg['themeDefault'] = $td;
                } else {
                    unset($cfg['themeDefault']);
                }
                // Standard-App-Sprache (solange der Gast nicht selbst wählt).
                $ld = (string) ($_POST['languageDefault'] ?? '');
                if (in_array($ld, ['de', 'en', 'fr', 'es'], true)) {
                    $cfg['languageDefault'] = $ld;
                } else {
                    unset($cfg['languageDefault']);
                }
                // Auto-Push-Schalter (überschreiben config.php; vom Cron gelesen).
                $cfg['autoPushUpcoming'] = !empty($_POST['autoPushUpcoming']);
                $cfg['autoPushNews']     = !empty($_POST['autoPushNews']);
                $win = trim((string) ($_POST['upcomingWindowMin'] ?? ''));
                if ($win === '') {
                    unset($cfg['upcomingWindowMin']);
                } else {
                    $cfg['upcomingWindowMin'] = max(1, (int) $win);
                }
                // Auto-Push-Kategorien (Safety immer implizit, daher nicht gespeichert).
                $pc = array_values(array_intersect(
                    ['info', 'lineup', 'general'],
                    array_keys((array) ($_POST['pushcat'] ?? []))
                ));
                $cfg['pushNewsCategories'] = $pc;
                $notice = cms_write_config($cfg)
                    ? cms_t('Einstellungen gespeichert. Übernahme in der App binnen ~2 Minuten.')
                    : cms_t('Speichern fehlgeschlagen – Schreibrechte des data-Ordners prüfen.');
                break;

            case 'save_news':
                $out = [];
                $pushNow = []; // id => true, wenn „Sofort pushen" angehakt
                foreach (($_POST['news'] ?? []) as $row) {
                    if (!empty($row['delete'])) {
                        continue;
                    }
                    $title = trim((string) ($row['title'] ?? ''));
                    if ($title === '') {
                        continue;
                    }
                    $id = trim((string) ($row['id'] ?? ''));
                    if ($id === '') {
                        $id = 'admin-' . (cms_slug($title) ?: 'news') . '-' . bin2hex(random_bytes(2));
                    }
                    $cat = (string) ($row['category'] ?? 'general');
                    if (!isset(CMS_NEWS_CATEGORIES[$cat])) {
                        $cat = 'general';
                    }
                    $pub = cms_dt_iso((string) ($row['publishAt'] ?? ''))
                        ?? (new DateTimeImmutable('now', new DateTimeZone(cms_tz())))->format('c');
                    $item = ['id' => $id, 'title' => $title, 'body' => (string) ($row['body'] ?? ''), 'category' => $cat, 'publishAt' => $pub];
                    if ($exp = cms_dt_iso((string) ($row['expiresAt'] ?? ''))) {
                        $item['expiresAt'] = $exp;
                    }
                    if (!empty($row['pinned'])) {
                        $item['pinned'] = true;
                    }
                    $lurl = trim((string) ($row['linkUrl'] ?? ''));
                    if ($lurl !== '') {
                        $item['link'] = ['label' => (trim((string) ($row['linkLabel'] ?? '')) ?: 'Mehr'), 'url' => $lurl];
                    }
                    $pushNow[$id] = !empty($row['pushNow']);
                    $out[] = $item;
                }
                usort($out, static fn($a, $b) => strcmp((string) $b['publishAt'], (string) $a['publishAt']));
                $ok = cms_write_json('admin-news.json', $out);
                $notice = $ok
                    ? cms_t('News gespeichert. Übernahme in der App binnen ~2 Minuten.')
                    : cms_t('Speichern fehlgeschlagen – Schreibrechte des data-Ordners prüfen.');

                // „Sofort pushen": markierte, bereits veröffentlichte Einträge einmalig
                // pushen (kategoriebewusst). push_log verhindert Doppelung mit dem Cron.
                $pushIds = array_keys(array_filter($pushNow));
                if ($ok && $pushIds) {
                    try {
                        require_once __DIR__ . '/../sender.php';
                        $pdo  = push_db();
                        $seen = array_flip($pdo->query('SELECT ref FROM push_log')->fetchAll(PDO::FETCH_COLUMN));
                        $ins  = $pdo->prepare('INSERT IGNORE INTO push_log (ref) VALUES (?)');
                        $nowDt = new DateTimeImmutable('now', new DateTimeZone(cms_tz()));
                        $byId = [];
                        foreach ($out as $it) {
                            $byId[$it['id']] = $it;
                        }
                        $sent = 0; $skipped = 0;
                        foreach ($pushIds as $pid) {
                            $it  = $byId[$pid] ?? null;
                            $ref = 'news:' . $pid;
                            if (!$it || isset($seen[$ref])) { $skipped++; continue; }
                            try {
                                $pub = new DateTimeImmutable($it['publishAt']);
                            } catch (Throwable $e) {
                                $skipped++; continue;
                            }
                            if ($pub > $nowDt) { $skipped++; continue; } // erst ab Veröffentlichung
                            $body = (string) ($it['body'] ?? '');
                            if (mb_strlen($body) > 180) {
                                $body = mb_substr($body, 0, 177) . '…';
                            }
                            $r = push_send_news(
                                ['title' => (string) $it['title'], 'body' => $body, 'url' => '/news', 'tag' => 'news'],
                                (string) ($it['category'] ?? 'general')
                            );
                            $ins->execute([$ref]);
                            $sent += (int) ($r['sent'] ?? 0);
                        }
                        $notice .= cms_t(' Sofort gepusht: %d zugestellt', $sent)
                            . ($skipped ? cms_t(' · %d übersprungen (schon gepusht oder noch nicht veröffentlicht)', $skipped) : '') . '.';
                    } catch (Throwable $e) {
                        $notice .= cms_t(' (Sofort-Push fehlgeschlagen: %s)', $e->getMessage());
                    }
                }
                break;

            case 'send_push':
                $ptitle = trim((string) ($_POST['ptitle'] ?? ''));
                $pbody  = trim((string) ($_POST['pbody'] ?? ''));
                $purl   = trim((string) ($_POST['purl'] ?? '')) ?: '/';
                if ($ptitle === '') {
                    $error = cms_t('Push-Titel fehlt.');
                    break;
                }
                try {
                    require_once __DIR__ . '/../sender.php';
                    $r = push_broadcast(['title' => $ptitle, 'body' => $pbody, 'url' => $purl, 'tag' => 'admin']);
                    $notice = cms_t(
                        'Push gesendet: %1$d zugestellt · %2$d abgelaufen entfernt · %3$d Abos gesamt.',
                        (int) ($r['sent'] ?? 0),
                        (int) ($r['removed'] ?? 0),
                        (int) ($r['total'] ?? 0)
                    );
                } catch (Throwable $e) {
                    $error = cms_t('Push fehlgeschlagen: %s', $e->getMessage());
                }
                break;

            case 'save_content':
                $domain = (string) ($_POST['domain'] ?? '');
                if (!isset(CMS_CONTENT_DOMAINS[$domain])) {
                    $error = cms_t('Unbekannte Domäne.');
                    break;
                }
                $data = json_decode((string) ($_POST['json'] ?? ''), true);
                if (!is_array($data)) {
                    $error = cms_t('Ungültiges JSON: %s', json_last_error_msg());
                    break;
                }
                $type   = CMS_CONTENT_DOMAINS[$domain]['type'];
                $isList = array_is_list($data);
                if ($type === 'array' && !$isList && $data !== []) {
                    $error = cms_t('Erwartet wird eine Liste [ … ].');
                    break;
                }
                if ($type === 'object' && $isList && $data !== []) {
                    $error = cms_t('Erwartet wird ein Objekt { … }.');
                    break;
                }
                $notice = cms_write_json("app-$domain.json", $data)
                    ? cms_t('Gespeichert. Übernahme in der App binnen ~2 Minuten.')
                    : cms_t('Speichern fehlgeschlagen – Schreibrechte des data-Ordners prüfen.');
                break;

            case 'delete_content':
                $domain = (string) ($_POST['domain'] ?? '');
                if (!isset(CMS_CONTENT_DOMAINS[$domain])) {
                    $error = cms_t('Unbekannte Domäne.');
                    break;
                }
                $f = cms_data_path("app-$domain.json");
                if (is_file($f)) {
                    @unlink($f);
                }
                $notice = cms_t('Override entfernt – die App nutzt wieder den Build-Stand.');
                break;

            case 'save_records':
                $domain = (string) ($_POST['domain'] ?? '');
                if (!isset(CMS_DOMAIN_FIELDS[$domain])) {
                    $error = cms_t('Unbekannte Domäne.');
                    break;
                }
                $fields = CMS_DOMAIN_FIELDS[$domain];
                $out = [];
                foreach (($_POST['rec'] ?? []) as $row) {
                    if (!empty($row['__delete'])) {
                        continue;
                    }
                    // Identitäts-Feld: i. d. R. „name", für Kategorien „label" bzw. die getippte ID.
                    $primary = trim((string) ($row['name'] ?? $row['label'] ?? $row['id'] ?? ''));
                    if ($primary === '') {
                        continue; // leere/ungenutzte Zeile
                    }
                    $id  = trim((string) ($row['__id'] ?? ''))
                        ?: trim((string) ($row['id'] ?? ''))
                        ?: (cms_slug($primary) ?: 'item-' . bin2hex(random_bytes(2)));
                    $rec = ['id' => $id];
                    foreach ($fields as $f) {
                        $key = $f['key'];
                        $val = $row[$key] ?? null;
                        switch ($f['type']) {
                            case 'number':
                                if (trim((string) $val) !== '') {
                                    $rec[$key] = cms_to_number((string) $val);
                                }
                                break;
                            case 'checkbox':
                                $checked = !empty($val);
                                if (!empty($f['omitWhenFalse'])) {
                                    if ($checked) {
                                        $rec[$key] = true;
                                    }
                                } elseif (($f['default'] ?? null) === true) {
                                    if (!$checked) {
                                        $rec[$key] = false;
                                    }
                                } else {
                                    $rec[$key] = $checked;
                                }
                                break;
                            case 'list':
                                $rec[$key] = array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', (string) $val) ?: [])));
                                break;
                            default:
                                $s = trim((string) $val);
                                if ($s !== '') {
                                    $rec[$key] = $s;
                                }
                        }
                    }
                    if ($domain === 'artists' && empty($rec['slug'])) {
                        $rec['slug'] = $id;
                    }
                    $out[] = $rec;
                }
                $needSort = false;
                foreach ($out as $r) {
                    if (isset($r['order'])) {
                        $needSort = true;
                        break;
                    }
                }
                if ($needSort) {
                    usort($out, static fn($a, $b) => (($a['order'] ?? PHP_FLOAT_MAX) <=> ($b['order'] ?? PHP_FLOAT_MAX)));
                }
                $notice = cms_write_json("app-$domain.json", $out)
                    ? cms_t('Gespeichert. Übernahme in der App binnen ~2 Minuten.')
                    : cms_t('Speichern fehlgeschlagen – Schreibrechte des data-Ordners prüfen.');
                break;

            case 'save_slots':
                $out = [];
                foreach (($_POST['slot'] ?? []) as $row) {
                    if (!empty($row['__delete'])) {
                        continue;
                    }
                    $artistId = trim((string) ($row['artistId'] ?? ''));
                    $stageId  = trim((string) ($row['stageId'] ?? ''));
                    $dayId    = trim((string) ($row['dayId'] ?? ''));
                    if ($artistId === '' || $stageId === '' || $dayId === '') {
                        continue; // unvollständige/leere Zeile
                    }
                    $id  = trim((string) ($row['__id'] ?? '')) ?: ($dayId . '-' . $stageId . '-' . cms_slug($artistId));
                    $rec = ['id' => $id, 'artistId' => $artistId, 'stageId' => $stageId, 'dayId' => $dayId];
                    if ($s = cms_dt_iso((string) ($row['start'] ?? ''))) {
                        $rec['start'] = $s;
                    }
                    if ($e = cms_dt_iso((string) ($row['end'] ?? ''))) {
                        $rec['end'] = $e;
                    }
                    $note = trim((string) ($row['note'] ?? ''));
                    if ($note !== '') {
                        $rec['note'] = $note;
                    }
                    if (!empty($row['cancelled'])) {
                        $rec['cancelled'] = true;
                    }
                    $out[] = $rec;
                }
                $notice = cms_write_json('app-slots.json', $out)
                    ? cms_t('Timetable gespeichert. Übernahme in der App binnen ~2 Minuten.')
                    : cms_t('Speichern fehlgeschlagen – Schreibrechte des data-Ordners prüfen.');
                break;

            case 'upload':
                $file = $_FILES['file'] ?? null;
                if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    $error = cms_t('Keine Datei gewählt.');
                    break;
                }
                if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
                    $error = cms_t('Upload-Fehler (Code %d).', (int) $file['error']);
                    break;
                }
                if (($file['size'] ?? 0) > CMS_UPLOAD_MAXSIZE) {
                    $error = cms_t('Datei zu groß (max. 5 MB).');
                    break;
                }
                $name = cms_safe_filename((string) ($file['name'] ?? ''));
                $ext  = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, CMS_UPLOAD_EXT, true)) {
                    $error = cms_t('Nur %s erlaubt.', implode(' / ', CMS_UPLOAD_EXT));
                    break;
                }
                $custom = cms_slug((string) ($_POST['rename'] ?? ''));
                if ($custom !== '') {
                    $name = $custom . '.' . $ext;
                }
                $dir = cms_uploads_dir();
                if (!is_dir($dir) || !is_writable($dir)) {
                    $error = cms_t('Upload-Ordner (data/uploads) nicht beschreibbar – Schreibrechte prüfen.');
                    break;
                }
                if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
                    $error = cms_t('Konnte Datei nicht speichern.');
                    break;
                }
                $uploaded = '/data/uploads/' . $name;
                $notice   = cms_t('Hochgeladen. Pfad unten kopieren und z. B. als Artist-„image" oder Sponsor-„logo" einsetzen.');
                break;

            case 'save_sources':
                $sc = [];
                foreach (CMS_CONTENT_DOMAINS as $domain => $m) {
                    $prov = (string) ($_POST['provider'][$domain] ?? 'manual');
                    if (!in_array($prov, ['manual', 'joomla', 'wordpress'], true)) {
                        $prov = 'manual';
                    }
                    $loc = trim((string) ($_POST['locator'][$domain] ?? ''));
                    $sc[$domain] = ['provider' => $prov] + ($loc !== '' ? ['locator' => $loc] : []);
                }
                $notice = cms_write_json('source-config.json', $sc)
                    ? cms_t('Quellen gespeichert.')
                    : cms_t('Speichern fehlgeschlagen – Schreibrechte des data-Ordners prüfen.');
                break;

            case 'run_import':
                $importReport = cms_run_import();
                $notice = $importReport ? cms_t('Import ausgeführt.') : cms_t('Keine Domäne auf Joomla/WordPress gesetzt – nichts zu importieren.');
                break;

            case 'import_info':
                $importReport = cms_import_info();
                $notice = $importReport ? cms_t('Info-Import ausgeführt.') : cms_t('Kein Info-Eintrag auf Joomla/WordPress gesetzt – nichts zu importieren.');
                break;
        }
    }
}

// Einstellungen sind der Start-Tab (Direktaufruf der URL ohne ?tab=…).
$tab  = $_GET['tab'] ?? 'settings';
$csrf = cms_csrf_token();
?>
<!doctype html>
<?php
// CMS-Titel datengetrieben: Festivalname aus den Daten (Override vor Build-Stand),
// damit auch Kunden-Installationen ihren eigenen Namen sehen.
// Guard: ohne push/config.php (vor der Installation) würde cms_read_json über
// push_config() mitten in der Seite abbrechen.
$cmsFest  = is_file(__DIR__ . '/../config.php')
    ? (cms_read_json('app-festival.json') ?: cms_read_json('festival.json'))
    : [];
$cmsTitle = trim((string) ($cmsFest['name'] ?? '')) ?: 'Festivadget';
?>
<html lang="<?= cms_h(cms_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= cms_h($cmsTitle) ?> · Admin</title>
<style>
  :root { --bg:#121212; --surface:#1c1c1c; --surface2:#262626; --text:#fff; --muted:#b3b3b3; --accent:#ffb300; --border:#2e2e2e; }
  * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  body { margin:0; background:var(--bg); color:var(--text); font-family: system-ui, -apple-system, sans-serif; }
  .wrap { max-width: 720px; margin: 0 auto; padding: 1.25rem 1rem 4rem; }
  h1 { font-size: 1.4rem; margin: .2rem 0 1rem; }
  h2 { font-size: 1.05rem; margin: 1.4rem 0 .6rem; }
  a { color: var(--accent); }
  .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1rem; margin-bottom:1rem; }
  label.row { display:flex; align-items:center; gap:.7rem; padding:.55rem .2rem; border-bottom:1px solid var(--border); cursor:pointer; }
  label.row:last-child { border-bottom:0; }
  .fld { display:block; margin:.5rem 0; }
  .fld span { display:block; font-size:.78rem; color:var(--muted); margin-bottom:.2rem; }
  input[type=text], input[type=number], input[type=password], textarea, select {
    width:100%; padding:.55rem .7rem; border-radius:10px; border:1px solid var(--border); background:var(--surface2); color:var(--text); font-size:1rem; font-family:inherit; }
  textarea { min-height:7rem; resize:vertical; }
  input[type=checkbox]{ width:1.15rem; height:1.15rem; accent-color: var(--accent); }
  button { background:var(--accent); color:#000; border:0; border-radius:999px; padding:.6rem 1.1rem; font-weight:700; font-size:.95rem; cursor:pointer; }
  button.ghost { background:var(--surface2); color:var(--text); border:1px solid var(--border); }
  .bar { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
  /* Aktionsleiste (Speichern etc.) – auch oben, klebrig beim Scrollen langer Listen. */
  .actions { display:flex; gap:.6rem; flex-wrap:wrap; margin-top:.5rem; }
  .actions.top { position:sticky; top:0; z-index:5; margin:-.4rem -1rem .8rem; padding:.6rem 1rem; background:var(--surface); border-bottom:1px solid var(--border); }
  .msg { padding:.7rem .9rem; border-radius:10px; margin-bottom:1rem; }
  .msg.err { background:#3a1f1a; border:1px solid #e4572e; }
  .msg.ok  { background:#1f2a1f; border:1px solid #4caf50; }
  .muted { color:var(--muted); font-size:.85rem; }
  .item { border:1px solid var(--border); border-radius:12px; padding:.8rem; margin-bottom:.8rem; background:var(--surface2); }
  .item .head { display:flex; align-items:center; gap:.8rem; justify-content:space-between; }
  .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; }
  nav.tabs { display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:1rem; }
  nav.tabs a { padding:.35rem .7rem; border-radius:999px; background:var(--surface2); color:var(--muted); font-size:.85rem; border:1px solid var(--border); text-decoration:none; }
  nav.tabs a.active { background:var(--accent); color:#000; border-color:var(--accent); font-weight:700; }
</style>
</head>
<body>
<div class="wrap">

<?php if ($error): ?><div class="msg err"><?= cms_h($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="msg ok"><?= cms_h($notice) ?></div><?php endif; ?>

<?php if (!cms_logged_in()): ?>

  <h1><?= cms_h($cmsTitle) ?> · Admin</h1>
  <form method="post" class="card" autocomplete="off">
    <input type="hidden" name="do" value="login">
    <h2 style="margin-top:0"><?= cms_h(cms_t('Anmelden')) ?></h2>
    <input type="password" name="password" placeholder="<?= cms_h(cms_t('Passwort')) ?>" autofocus required>
    <div style="margin-top:.9rem"><button type="submit"><?= cms_h(cms_t('Anmelden')) ?></button></div>
    <p class="muted" style="margin-bottom:0"><?= cms_t('Passwort = Admin-Passwort aus <code>push/config.php</code>.') ?></p>
  </form>

<?php else: ?>

  <div class="bar">
    <h1 style="margin:0"><?= cms_h($cmsTitle) ?> · Admin</h1>
    <form method="post"><input type="hidden" name="do" value="logout"><button class="ghost" type="submit"><?= cms_h(cms_t('Abmelden')) ?></button></form>
  </div>

  <nav class="tabs">
    <?php
    $tabs = ['settings' => 'Einstellungen', 'branding' => 'Branding', 'more' => 'MEHR-Menü', 'info' => 'Infos', 'content' => 'Inhalte', 'upload' => 'Bilder', 'sources' => 'Quellen', 'news' => 'News', 'push' => 'Push', 'weather' => 'Wetter', 'stats' => 'Statistik', 'log' => 'Protokoll', 'update' => 'Update', 'help' => 'Hilfe'];
    foreach ($tabs as $k => $label):
    ?>
      <a class="<?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= cms_h($k) ?>"><?= cms_h(cms_t($label)) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($tab === 'more'):
    $hidden = (array) (cms_read_config()['moreHidden'] ?? []); ?>
    <form method="post" class="card">
      <input type="hidden" name="do" value="save_more">
      <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Sichtbare Punkte im MEHR-Menü')) ?></h2>
      <p class="muted"><?= cms_h(cms_t('Angehakt = sichtbar. Abgehakte Punkte werden in der App ausgeblendet.')) ?></p>
      <?php foreach (CMS_MORE_ITEMS as $key => $label): ?>
        <label class="row">
          <input type="checkbox" name="more[<?= cms_h($key) ?>]" value="1" <?= in_array($key, $hidden, true) ? '' : 'checked' ?>>
          <span><?= cms_h(cms_t($label)) ?></span>
        </label>
      <?php endforeach; ?>
      <div style="margin-top:1rem"><button type="submit"><?= cms_h(cms_t('Speichern')) ?></button></div>
    </form>

  <?php elseif ($tab === 'info'):
    $items = cms_info_items();
    // Eine leere „neue Zeile" anhängen.
    $items[] = ['id' => '', 'title' => '', 'icon' => '', 'order' => '', 'body' => '', 'hidden' => false, '__new' => true];
    ?>
    <?php if ($importReport !== null): ?>
      <div class="card">
        <h2 style="margin-top:0"><?= cms_h(cms_t('Import-Ergebnis')) ?></h2>
        <?php if (!$importReport): ?>
          <p class="muted"><?= cms_h(cms_t('Kein Info-Eintrag auf Joomla/WordPress gesetzt.')) ?></p>
        <?php else: foreach ($importReport as $d => $st): ?>
          <div class="row" style="justify-content:space-between"><span><?= cms_h((string) $d) ?></span><span><?= cms_h((string) $st) ?></span></div>
        <?php endforeach; endif; ?>
      </div>
    <?php endif; ?>
    <form method="post" class="card">
      <input type="hidden" name="do" value="save_info">
      <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Infos verwalten')) ?></h2>
      <p class="muted"><?= cms_h(cms_t('Ein-/ausblenden (Häkchen „Sichtbar"), umbenennen, Text und Reihenfolge ändern, neue Einträge unten hinzufügen, „Löschen" entfernt beim Speichern. Reihenfolge darf Dezimal sein (z. B. 1.5). Text = Markdown; Leerzeile = neuer Absatz.')) ?></p>
      <p class="muted"><?= cms_t('<b>Quelle je Eintrag:</b> <code>manual</code> = die hier getippten Werte. <code>joomla</code>/<code>wordpress</code> = Titel/Text werden beim Import aus dem Artikel (Locator: Joomla-Artikel-ID bzw. WP-Slug/ID) gezogen; Reihenfolge/Icon/Sichtbarkeit bleiben hier. Erst <b>Speichern</b>, dann <b>Importieren</b>.') ?></p>
      <datalist id="icons"><?php foreach (CMS_INFO_ICONS as $ic): ?><option value="<?= cms_h($ic) ?>"></option><?php endforeach; ?></datalist>
      <?php $infoBar = '<button type="submit">' . cms_h(cms_t('Speichern')) . '</button>'
        . '<button type="submit" form="impinfo" class="ghost" onclick="return confirm(\'' . cms_j(cms_t('Titel/Text aller auf Joomla/WordPress gesetzten Einträge jetzt importieren? (zuvor gespeicherte Quellen)')) . '\')">' . cms_h(cms_t('Aus Joomla/WordPress importieren')) . '</button>'; ?>
      <div class="actions top"><?= $infoBar ?></div>

      <?php foreach ($items as $i => $it):
        $isNew = !empty($it['__new']); ?>
        <div class="item">
          <div class="head">
            <strong><?= $isNew ? cms_h(cms_t('Neuer Eintrag')) : cms_h((string) $it['title']) ?></strong>
            <?php if (!$isNew): ?>
              <label class="muted" style="display:flex;align-items:center;gap:.4rem">
                <input type="checkbox" name="items[<?= $i ?>][delete]" value="1"> <?= cms_h(cms_t('Löschen')) ?>
              </label>
            <?php endif; ?>
          </div>
          <?php if (!$isNew): ?>
            <input type="hidden" name="items[<?= $i ?>][id]" value="<?= cms_h((string) ($it['id'] ?? '')) ?>">
          <?php endif; ?>
          <div class="grid2">
            <label class="fld"><span><?= cms_h(cms_t('Titel')) ?></span>
              <input type="text" name="items[<?= $i ?>][title]" value="<?= cms_h((string) ($it['title'] ?? '')) ?>"></label>
            <label class="fld"><span><?= cms_h(cms_t('Reihenfolge')) ?></span>
              <input type="number" step="0.1" name="items[<?= $i ?>][order]" value="<?= cms_h((string) ($it['order'] ?? '')) ?>"></label>
          </div>
          <div class="grid2">
            <label class="fld"><span><?= cms_h(cms_t('Icon (optional)')) ?></span>
              <input type="text" list="icons" name="items[<?= $i ?>][icon]" value="<?= cms_h((string) ($it['icon'] ?? '')) ?>"></label>
            <label class="fld" style="align-self:end">
              <span>&nbsp;</span>
              <label style="display:flex;align-items:center;gap:.5rem;padding:.55rem 0">
                <input type="checkbox" name="items[<?= $i ?>][hidden]" value="1" <?= !empty($it['hidden']) ? 'checked' : '' ?>>
                <?= cms_h(cms_t('Versteckt (nicht im Menü/Suche)')) ?>
              </label>
            </label>
          </div>
          <label class="fld">
            <label style="display:flex;align-items:center;gap:.5rem;padding:.3rem 0">
              <input type="checkbox" name="items[<?= $i ?>][faq]" value="1" <?= !empty($it['faq']) ? 'checked' : '' ?>>
              <?= cms_h(cms_t('Als Frage/Antwort-Accordion anzeigen (jede „## Frage“ wird aufklappbar; Text davor = Intro)')) ?>
            </label>
          </label>
          <?php $src = (string) ($it['source'] ?? 'manual'); ?>
          <div class="grid2">
            <label class="fld"><span><?= cms_h(cms_t('Quelle')) ?></span>
              <select name="items[<?= $i ?>][source]">
                <?php foreach (['manual' => cms_t('manual (getippt)'), 'joomla' => 'Joomla', 'wordpress' => 'WordPress'] as $sk => $sl): ?>
                  <option value="<?= cms_h($sk) ?>" <?= $src === $sk ? 'selected' : '' ?>><?= cms_h($sl) ?></option>
                <?php endforeach; ?>
              </select></label>
            <label class="fld"><span><?= cms_h(cms_t('Locator (Joomla-Artikel-ID / WP-Slug)')) ?></span>
              <input type="text" name="items[<?= $i ?>][sourceLocator]" value="<?= cms_h((string) ($it['sourceLocator'] ?? '')) ?>" placeholder="<?= cms_h(cms_t('z. B. 123')) ?>"></label>
          </div>
          <label class="fld"><span><?= cms_h(cms_t('Text (Markdown)')) ?></span>
            <textarea name="items[<?= $i ?>][body]"><?= cms_h((string) ($it['body'] ?? '')) ?></textarea></label>
        </div>
      <?php endforeach; ?>
      <div class="actions"><?= $infoBar ?></div>
    </form>
    <form id="impinfo" method="post" style="display:none">
      <input type="hidden" name="do" value="import_info">
      <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
    </form>

  <?php elseif ($tab === 'content'):
    $domain = (string) ($_GET['domain'] ?? 'sponsors');
    if (!isset(CMS_CONTENT_DOMAINS[$domain])) {
        $domain = 'sponsors';
    }
    $hasForm = ($domain === 'slots') || isset(CMS_DOMAIN_FIELDS[$domain]);
    $mode = (!$hasForm || ($_GET['mode'] ?? '') === 'json') ? 'json' : 'form';
    $hasOverride = cms_content_override_exists($domain);
    $delBtn = $hasOverride
        ? '<button type="submit" form="delovr" class="ghost" onclick="return confirm(\'' . cms_j(cms_t('Override entfernen und zum Build-Stand zurück?')) . '\')">' . cms_h(cms_t('Override entfernen')) . '</button>'
        : '';
    $saveBar = '<button type="submit">' . cms_h(cms_t('Speichern')) . '</button>' . $delBtn; ?>
    <div class="card">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Inhalte')) ?></h2>
      <p class="muted"><?= cms_t('Jede Datei aus <code>/content</code> bearbeitbar → live wirksam (überschreibt den Build-Stand). „Override entfernen" stellt den Build-Stand wieder her.') ?></p>
      <form method="get">
        <input type="hidden" name="tab" value="content">
        <?php if ($mode === 'json' && $hasForm): ?><input type="hidden" name="mode" value="json"><?php endif; ?>
        <label class="fld"><span><?= cms_h(cms_t('Domäne')) ?></span>
          <select name="domain" onchange="this.form.submit()">
            <?php foreach (CMS_CONTENT_DOMAINS as $k => $d): ?>
              <option value="<?= cms_h($k) ?>" <?= $domain === $k ? 'selected' : '' ?>><?= cms_h(cms_t($d['label'])) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </form>
      <p class="muted">
        <?= $hasOverride ? cms_h(cms_t('🟡 Override aktiv.')) : cms_h(cms_t('⚪ Kein Override – Build-Stand.')) ?>
        <?php if ($hasForm): ?> ·
          <?php if ($mode === 'form'): ?><a href="?tab=content&amp;domain=<?= cms_h($domain) ?>&amp;mode=json"><?= cms_h(cms_t('Als JSON bearbeiten')) ?></a>
          <?php else: ?><a href="?tab=content&amp;domain=<?= cms_h($domain) ?>"><?= cms_h(cms_t('Formular-Ansicht')) ?></a><?php endif; ?>
        <?php endif; ?>
      </p>

      <?php if ($mode === 'json'):
        $raw = cms_content_raw($domain); ?>
        <form method="post">
          <input type="hidden" name="do" value="save_content">
          <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
          <input type="hidden" name="domain" value="<?= cms_h($domain) ?>">
          <div class="actions top"><?= $saveBar ?></div>
          <textarea name="json" spellcheck="false" style="min-height:24rem;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.82rem"><?= cms_h($raw) ?></textarea>
          <div class="actions"><?= $saveBar ?></div>
        </form>

      <?php elseif ($domain === 'slots'):
        $artists = cms_domain_records('artists');
        $stages  = cms_domain_records('stages');
        $festival = json_decode(cms_content_raw('festival'), true) ?: [];
        $days = is_array($festival['days'] ?? null) ? $festival['days'] : [];
        $slots = cms_domain_records('slots');
        $slots[] = ['__new' => true];
        $opt = static function (array $items, string $vk, string $lk, string $sel): string {
            $h = '';
            foreach ($items as $it) {
                $v = (string) ($it[$vk] ?? '');
                $h .= '<option value="' . cms_h($v) . '"' . ($sel === $v ? ' selected' : '') . '>' . cms_h((string) ($it[$lk] ?? $v)) . '</option>';
            }
            return $h;
        }; ?>
        <p class="muted"><?= cms_h(cms_t('Pro Slot: Act, Bühne, Tag, Beginn/Ende. Neue Zeile unten. „Löschen" entfernt beim Speichern. (Acts/Bühnen/Tage kommen aus den jeweiligen Inhalten.)')) ?></p>
        <form method="post">
          <input type="hidden" name="do" value="save_slots">
          <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
          <div class="actions top"><?= $saveBar ?></div>
          <?php foreach ($slots as $i => $s): $isNew = !empty($s['__new']); ?>
            <div class="item">
              <div class="head">
                <strong><?= $isNew ? cms_h(cms_t('Neuer Slot')) : cms_h((string) ($s['artistId'] ?? '')) ?></strong>
                <?php if (!$isNew): ?><label class="muted" style="display:flex;align-items:center;gap:.4rem"><input type="checkbox" name="slot[<?= $i ?>][__delete]" value="1"> <?= cms_h(cms_t('Löschen')) ?></label><?php endif; ?>
              </div>
              <?php if (!$isNew): ?><input type="hidden" name="slot[<?= $i ?>][__id]" value="<?= cms_h((string) ($s['id'] ?? '')) ?>"><?php endif; ?>
              <div class="grid2">
                <label class="fld"><span><?= cms_h(cms_t('Act')) ?></span><select name="slot[<?= $i ?>][artistId]"><option value=""></option><?= $opt($artists, 'id', 'name', (string) ($s['artistId'] ?? '')) ?></select></label>
                <label class="fld"><span><?= cms_h(cms_t('Bühne')) ?></span><select name="slot[<?= $i ?>][stageId]"><option value=""></option><?= $opt($stages, 'id', 'name', (string) ($s['stageId'] ?? '')) ?></select></label>
                <label class="fld"><span><?= cms_h(cms_t('Tag')) ?></span><select name="slot[<?= $i ?>][dayId]"><option value=""></option><?= $opt($days, 'id', 'label', (string) ($s['dayId'] ?? '')) ?></select></label>
                <label class="fld" style="align-self:end"><label style="display:flex;align-items:center;gap:.5rem;padding:.55rem 0"><input type="checkbox" name="slot[<?= $i ?>][cancelled]" value="1" <?= !empty($s['cancelled']) ? 'checked' : '' ?>> <?= cms_h(cms_t('abgesagt')) ?></label></label>
                <label class="fld"><span><?= cms_h(cms_t('Beginn')) ?></span><input type="datetime-local" name="slot[<?= $i ?>][start]" value="<?= cms_h(cms_dt_local($s['start'] ?? null)) ?>"></label>
                <label class="fld"><span><?= cms_h(cms_t('Ende')) ?></span><input type="datetime-local" name="slot[<?= $i ?>][end]" value="<?= cms_h(cms_dt_local($s['end'] ?? null)) ?>"></label>
              </div>
              <label class="fld"><span><?= cms_h(cms_t('Notiz (optional)')) ?></span><input type="text" name="slot[<?= $i ?>][note]" value="<?= cms_h((string) ($s['note'] ?? '')) ?>"></label>
            </div>
          <?php endforeach; ?>
          <div class="actions"><?= $saveBar ?></div>
        </form>

      <?php else:
        $fields  = CMS_DOMAIN_FIELDS[$domain];
        // POI-Kategorie-Dropdown dynamisch aus den vorhandenen Kategorien füllen.
        if ($domain === 'pois') {
            $catIds = array_values(array_filter(array_map(
                static fn($c) => is_array($c) ? (string) ($c['id'] ?? '') : '',
                cms_domain_records('poi-categories')
            )));
            if ($catIds) {
                foreach ($fields as &$cf) {
                    if (($cf['key'] ?? '') === 'type') {
                        $cf['options'] = $catIds;
                    }
                }
                unset($cf);
            }
        }
        $records = cms_domain_records($domain);
        $records[] = ['__new' => true]; ?>
        <form method="post">
          <input type="hidden" name="do" value="save_records">
          <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
          <input type="hidden" name="domain" value="<?= cms_h($domain) ?>">
          <div class="actions top"><?= $saveBar ?></div>
          <?php foreach ($records as $i => $rec): $isNew = !empty($rec['__new']); ?>
            <div class="item">
              <div class="head">
                <strong><?= $isNew ? cms_h(cms_t('Neuer Eintrag')) : cms_h((string) ($rec['name'] ?? $rec['label'] ?? $rec['id'] ?? '')) ?></strong>
                <?php if (!$isNew): ?><label class="muted" style="display:flex;align-items:center;gap:.4rem"><input type="checkbox" name="rec[<?= $i ?>][__delete]" value="1"> <?= cms_h(cms_t('Löschen')) ?></label><?php endif; ?>
              </div>
              <?php if (!$isNew): ?><input type="hidden" name="rec[<?= $i ?>][__id]" value="<?= cms_h((string) ($rec['id'] ?? '')) ?>"><?php endif; ?>
              <?php foreach ($fields as $f): ?>
                <label class="fld"><span><?= cms_h(cms_t($f['label'])) ?></span><?= cms_field_input("rec[$i][{$f['key']}]", $f, $rec[$f['key']] ?? null) ?></label>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
          <div class="actions"><?= $saveBar ?></div>
        </form>
      <?php endif; ?>

      <?php if ($hasOverride): ?>
        <form id="delovr" method="post" style="display:none">
          <input type="hidden" name="do" value="delete_content">
          <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
          <input type="hidden" name="domain" value="<?= cms_h($domain) ?>">
        </form>
      <?php endif; ?>
    </div>

  <?php elseif ($tab === 'upload'):
    $uploads = cms_list_uploads(); ?>
    <form method="post" class="card" enctype="multipart/form-data">
      <input type="hidden" name="do" value="upload">
      <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Bild hochladen')) ?></h2>
      <p class="muted"><?= cms_t('Erlaubt: %s · max. 5 MB. Wird unter <code>/data/uploads/</code> gespeichert; den angezeigten Pfad kopierst du in „Inhalte" (z. B. Artist-<code>image</code> oder Sponsor-<code>logo</code>).', cms_h(implode(', ', CMS_UPLOAD_EXT))) ?></p>
      <label class="fld"><span><?= cms_h(cms_t('Datei')) ?></span><input type="file" name="file" accept=".webp,.png,.jpg,.jpeg,.svg" required></label>
      <label class="fld"><span><?= cms_h(cms_t('Dateiname überschreiben (optional, ohne Endung)')) ?></span><input type="text" name="rename" placeholder="<?= cms_h(cms_t('z. B. logo-firma')) ?>"></label>
      <div style="margin-top:.5rem"><button type="submit"><?= cms_h(cms_t('Hochladen')) ?></button></div>
      <?php if ($uploaded): ?>
        <p style="margin-top:1rem"><?= cms_h(cms_t('Pfad:')) ?> <code><?= cms_h($uploaded) ?></code></p>
      <?php endif; ?>
    </form>
    <?php if ($uploads): ?>
      <div class="card">
        <h2 style="margin-top:0"><?= cms_h(cms_t('Vorhandene Uploads')) ?></h2>
        <?php foreach ($uploads as $u): ?>
          <div class="row" style="justify-content:space-between">
            <code style="font-size:.8rem"><?= cms_h($u['path']) ?></code>
            <img src="<?= cms_h($u['path']) ?>" alt="" style="height:34px;width:auto;max-width:90px;object-fit:contain">
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($tab === 'sources'):
    $sc = cms_source_config(); ?>
    <?php if ($importReport !== null): ?>
      <div class="card">
        <h2 style="margin-top:0"><?= cms_h(cms_t('Import-Ergebnis')) ?></h2>
        <?php if (!$importReport): ?>
          <p class="muted"><?= cms_h(cms_t('Keine Domäne auf Joomla/WordPress gesetzt.')) ?></p>
        <?php else: foreach ($importReport as $d => $st): ?>
          <div class="row" style="justify-content:space-between"><span><?= cms_h($d) ?></span><span><?= cms_h($st) ?></span></div>
        <?php endforeach; endif; ?>
      </div>
    <?php endif; ?>
    <form method="post" class="card">
      <input type="hidden" name="do" value="save_sources">
      <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Datenquelle je Domäne')) ?></h2>
      <p class="muted"><?= cms_t('Pro Domäne wählen, woher die Daten kommen. <b>manual</b> = der „Inhalte"-Editor bzw. Build-Stand. <b>joomla</b>/<b>wordpress</b> = Server-Import. Locator: Joomla = Kategorie-ID, WordPress = Kategorie-Slug. Verbindung/Token in <code>push/config.php</code> → <code>sources</code>. <i>Generisches Mapping (Titel/Text); strukturierte Domänen ggf. im „Inhalte"-Tab nachbearbeiten.</i>') ?></p>
      <?php foreach (CMS_CONTENT_DOMAINS as $domain => $m):
        $prov = (string) ($sc[$domain]['provider'] ?? 'manual');
        $loc  = (string) ($sc[$domain]['locator'] ?? ''); ?>
        <div class="grid2" style="align-items:end;border-bottom:1px solid var(--border);padding:.5rem 0">
          <label class="fld" style="margin:0"><span><?= cms_h(cms_t($m['label'])) ?> (<?= cms_h($domain) ?>)</span>
            <select name="provider[<?= cms_h($domain) ?>]">
              <?php foreach (['manual' => 'manual', 'joomla' => 'Joomla', 'wordpress' => 'WordPress'] as $pk => $pl): ?>
                <option value="<?= cms_h($pk) ?>" <?= $prov === $pk ? 'selected' : '' ?>><?= cms_h($pl) ?></option>
              <?php endforeach; ?>
            </select></label>
          <label class="fld" style="margin:0"><span><?= cms_h(cms_t('Locator (Kategorie-ID / -Slug)')) ?></span>
            <input type="text" name="locator[<?= cms_h($domain) ?>]" value="<?= cms_h($loc) ?>"></label>
        </div>
      <?php endforeach; ?>
      <div style="margin-top:1rem;display:flex;gap:.6rem;flex-wrap:wrap">
        <button type="submit"><?= cms_h(cms_t('Quellen speichern')) ?></button>
        <button type="submit" form="runimp" class="ghost" onclick="return confirm('<?= cms_j(cms_t('Jetzt aus den konfigurierten Quellen importieren?')) ?>')"><?= cms_h(cms_t('Jetzt importieren')) ?></button>
      </div>
    </form>
    <form id="runimp" method="post" style="display:none">
      <input type="hidden" name="do" value="run_import">
      <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
    </form>

  <?php elseif ($tab === 'settings'):
    $cfg = cms_read_config();
    $lim = $cfg['lineupImageLimit'] ?? '';
    $bg  = ($cfg['background'] ?? true) !== false;
    $hh  = ($cfg['homeHeader'] ?? true) !== false;
    $cUrl = (string) ($cfg['contactUrl'] ?? '');
    $iUrl = (string) ($cfg['impressumUrl'] ?? '');
    $td  = (string) ($cfg['themeDefault'] ?? '');
    $pushCats = (array) ($cfg['pushNewsCategories'] ?? []);
    $auUpcoming = ($cfg['autoPushUpcoming'] ?? true) !== false;
    $auNews     = ($cfg['autoPushNews'] ?? true) !== false;
    $winMin     = $cfg['upcomingWindowMin'] ?? '';
    $bgImage    = (string) ($cfg['backgroundImage'] ?? '');
    $bgUploads  = cms_list_uploads(); ?>
    <form method="post" class="card">
      <input type="hidden" name="do" value="save_settings">
      <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Globale Einstellungen')) ?></h2>

      <label class="fld"><span><?= cms_h(cms_t('CMS-Sprache')) ?></span>
        <select name="cmsLang">
          <?php foreach (CMS_LANGS as $lk => $ll): ?>
            <option value="<?= cms_h($lk) ?>" <?= cms_lang() === $lk ? 'selected' : '' ?>><?= cms_h($ll) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p class="muted" style="margin-top:0"><?= cms_h(cms_t('Sprache dieser Admin-Oberfläche. Die App-Sprache wählt jeder Gast selbst in der App.')) ?></p>

      <label class="fld"><span><?= cms_h(cms_t('Line-Up: Anzahl Acts mit Bild')) ?></span>
        <input type="number" min="0" step="1" name="lineupImageLimit" value="<?= cms_h((string) $lim) ?>" placeholder="<?= cms_h(cms_t('Standard (20)')) ?>">
      </label>
      <p class="muted" style="margin-top:0"><?= cms_h(cms_t('Leer = Standardwert aus dem App-Code (20). Alle weiteren Acts ohne Bild.')) ?></p>

      <label class="fld"><span><?= cms_h(cms_t('Standard-Theme (solange der Gast nicht selbst wählt)')) ?></span>
        <select name="themeDefault">
          <option value="" <?= $td === '' ? 'selected' : '' ?>><?= cms_h(cms_t('App-Standard (Dark)')) ?></option>
          <option value="dark" <?= $td === 'dark' ? 'selected' : '' ?>><?= cms_h(cms_t('Dark')) ?></option>
          <option value="light" <?= $td === 'light' ? 'selected' : '' ?>><?= cms_h(cms_t('Light')) ?></option>
        </select>
      </label>

      <label class="fld"><span><?= cms_h(cms_t('Standard-Sprache der App (solange der Gast nicht selbst wählt)')) ?></span>
        <?php $ld = (string) ($cfg['languageDefault'] ?? ''); ?>
        <select name="languageDefault">
          <option value="" <?= $ld === '' ? 'selected' : '' ?>><?= cms_h(cms_t('Build-Standard')) ?></option>
          <option value="de" <?= $ld === 'de' ? 'selected' : '' ?>>Deutsch</option>
          <option value="en" <?= $ld === 'en' ? 'selected' : '' ?>>English</option>
          <option value="fr" <?= $ld === 'fr' ? 'selected' : '' ?>>Français</option>
          <option value="es" <?= $ld === 'es' ? 'selected' : '' ?>>Español</option>
        </select>
      </label>

      <label class="row" style="margin-top:.6rem">
        <input type="checkbox" name="background" value="1" <?= $bg ? 'checked' : '' ?>>
        <span><?= cms_h(cms_t('Hintergrundgrafik anzeigen')) ?></span>
      </label>

      <label class="fld" style="margin-top:.6rem"><span><?= cms_h(cms_t('Hintergrundbild')) ?></span>
        <select name="backgroundImage">
          <option value="" <?= $bgImage === '' ? 'selected' : '' ?>><?= cms_h(cms_t('Standard (mitgelieferte Grafik)')) ?></option>
          <?php foreach ($bgUploads as $u): ?>
            <option value="<?= cms_h($u['path']) ?>" <?= $bgImage === $u['path'] ? 'selected' : '' ?>><?= cms_h($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p class="muted" style="margin-top:0"><?= cms_h(cms_t('Eigenes Bild zuerst im Tab „Bilder" hochladen (Querformat empfohlen). Wirkt nur, solange „Hintergrundgrafik anzeigen" aktiv ist.')) ?></p>

      <label class="row">
        <input type="checkbox" name="homeHeader" value="1" <?= $hh ? 'checked' : '' ?>>
        <span><?= cms_h(cms_t('Home: Festivalname und Datum anzeigen')) ?></span>
      </label>

      <label class="fld"><span><?= cms_h(cms_t('Kontakt-Link (MEHR-Menü)')) ?></span>
        <input type="url" name="contactUrl" value="<?= cms_h($cUrl) ?>" placeholder="https://example.com/kontakt">
      </label>
      <label class="fld"><span><?= cms_h(cms_t('Impressum-Link (MEHR-Menü)')) ?></span>
        <input type="url" name="impressumUrl" value="<?= cms_h($iUrl) ?>" placeholder="https://example.com/impressum">
      </label>
      <p class="muted" style="margin-top:0"><?= cms_h(cms_t('Beide Punkte erscheinen im MEHR-Menü nur, wenn hier eine Adresse steht – jede Instanz verlinkt ihr eigenes Impressum.')) ?></p>

      <h2><?= cms_h(cms_t('Push-Automatik')) ?></h2>
      <p class="muted" style="margin-top:0"><?= cms_t('Steuert die automatischen Pushes des Cron-Jobs (läuft je nach Server z. B. stündlich). Greift nur, wenn der Cron eingerichtet ist (siehe <code>docs/PUSH.md</code>).') ?></p>
      <label class="row">
        <input type="checkbox" name="autoPushUpcoming" value="1" <?= $auUpcoming ? 'checked' : '' ?>>
        <span><?= cms_h(cms_t('Konzert-Digest „Gleich live" (Timetable: bald startende Acts)')) ?></span>
      </label>
      <label class="fld"><span><?= cms_h(cms_t('Digest-Vorlaufzeit (Minuten) – an die Cron-Frequenz anpassen')) ?></span>
        <input type="number" min="1" step="1" name="upcomingWindowMin" value="<?= cms_h((string) $winMin) ?>" placeholder="<?= cms_h(cms_t('Standard (60)')) ?>">
      </label>
      <p class="muted" style="margin-top:0"><?= cms_h(cms_t('Acts, die innerhalb dieser Zeit starten, werden (einmalig) gepusht. Bei häufigem Cron kleiner wählen (z. B. 15–20), sonst kommt der Push zu früh. Leer = 60.')) ?></p>
      <label class="row">
        <input type="checkbox" name="autoPushNews" value="1" <?= $auNews ? 'checked' : '' ?>>
        <span><?= cms_h(cms_t('Neue News automatisch pushen')) ?></span>
      </label>

      <h2><?= cms_h(cms_t('Auto-Push: Kategorien')) ?></h2>
      <p class="muted" style="margin-top:0"><?= cms_t('Welche News-Kategorien automatisch als Push gehen (sofern „Neue News automatisch pushen" aktiv ist). <b>Sicherheit</b> wird <b>immer</b> gepusht. Welche dieser Kategorien jeder Gast tatsächlich erhält, wählt er zusätzlich selbst in der App.') ?></p>
      <?php foreach (['info' => 'Infos', 'lineup' => 'Line-Up', 'general' => 'Allgemein'] as $ck => $cl): ?>
        <label class="row">
          <input type="checkbox" name="pushcat[<?= cms_h($ck) ?>]" value="1" <?= in_array($ck, $pushCats, true) ? 'checked' : '' ?>>
          <span><?= cms_h(cms_t($cl)) ?></span>
        </label>
      <?php endforeach; ?>
      <label class="row">
        <input type="checkbox" checked disabled>
        <span><?= cms_h(cms_t('Sicherheit')) ?> <span class="muted"><?= cms_h(cms_t('(immer aktiv)')) ?></span></span>
      </label>

      <div style="margin-top:1rem"><button type="submit"><?= cms_h(cms_t('Speichern')) ?></button></div>
    </form>

  <?php elseif ($tab === 'news'):
    $items = cms_news_items();
    $items[] = ['id' => '', 'title' => '', 'body' => '', 'category' => 'general', 'publishAt' => '', 'expiresAt' => '', 'pinned' => false, 'link' => null, '__new' => true]; ?>
    <form method="post" class="card">
      <input type="hidden" name="do" value="save_news">
      <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
      <h2 style="margin-top:0"><?= cms_h(cms_t('News verwalten')) ?></h2>
      <p class="muted"><?= cms_h(cms_t('Diese News erscheinen im Newsfeed (zusätzlich zu Telegram-Live-News). Sichtbar ab „Veröffentlichen am", optional bis „Ablauf am". „Angepinnt" und „Sicherheit" stehen oben. Text = Markdown.')) ?></p>
      <div class="actions top"><button type="submit"><?= cms_h(cms_t('Speichern')) ?></button></div>

      <?php foreach ($items as $i => $it):
        $isNew = !empty($it['__new']); ?>
        <div class="item">
          <div class="head">
            <strong><?= $isNew ? cms_h(cms_t('Neue News')) : cms_h((string) $it['title']) ?></strong>
            <?php if (!$isNew): ?>
              <label class="muted" style="display:flex;align-items:center;gap:.4rem">
                <input type="checkbox" name="news[<?= $i ?>][delete]" value="1"> <?= cms_h(cms_t('Löschen')) ?>
              </label>
            <?php endif; ?>
          </div>
          <?php if (!$isNew): ?>
            <input type="hidden" name="news[<?= $i ?>][id]" value="<?= cms_h((string) ($it['id'] ?? '')) ?>">
          <?php endif; ?>
          <label class="fld"><span><?= cms_h(cms_t('Titel')) ?></span>
            <input type="text" name="news[<?= $i ?>][title]" value="<?= cms_h((string) ($it['title'] ?? '')) ?>"></label>
          <label class="fld"><span><?= cms_h(cms_t('Text (Markdown)')) ?></span>
            <textarea name="news[<?= $i ?>][body]"><?= cms_h((string) ($it['body'] ?? '')) ?></textarea></label>
          <div class="grid2">
            <label class="fld"><span><?= cms_h(cms_t('Kategorie')) ?></span>
              <select name="news[<?= $i ?>][category]">
                <?php foreach (CMS_NEWS_CATEGORIES as $ck => $cl): ?>
                  <option value="<?= cms_h($ck) ?>" <?= ($it['category'] ?? 'general') === $ck ? 'selected' : '' ?>><?= cms_h(cms_t($cl)) ?></option>
                <?php endforeach; ?>
              </select></label>
            <label class="fld" style="align-self:end">
              <label style="display:flex;align-items:center;gap:.5rem;padding:.55rem 0">
                <input type="checkbox" name="news[<?= $i ?>][pinned]" value="1" <?= !empty($it['pinned']) ? 'checked' : '' ?>> <?= cms_h(cms_t('Angepinnt')) ?>
              </label>
            </label>
          </div>
          <div class="grid2">
            <label class="fld"><span><?= cms_h(cms_t('Veröffentlichen am')) ?></span>
              <input type="datetime-local" name="news[<?= $i ?>][publishAt]" value="<?= cms_h(cms_dt_local($it['publishAt'] ?? null)) ?>"></label>
            <label class="fld"><span><?= cms_h(cms_t('Ablauf am (optional)')) ?></span>
              <input type="datetime-local" name="news[<?= $i ?>][expiresAt]" value="<?= cms_h(cms_dt_local($it['expiresAt'] ?? null)) ?>"></label>
          </div>
          <div class="grid2">
            <label class="fld"><span><?= cms_h(cms_t('Link-Text (optional)')) ?></span>
              <input type="text" name="news[<?= $i ?>][linkLabel]" value="<?= cms_h((string) ($it['link']['label'] ?? '')) ?>"></label>
            <label class="fld"><span><?= cms_h(cms_t('Link-URL (optional)')) ?></span>
              <input type="text" name="news[<?= $i ?>][linkUrl]" value="<?= cms_h((string) ($it['link']['url'] ?? '')) ?>"></label>
          </div>
          <label class="row">
            <input type="checkbox" name="news[<?= $i ?>][pushNow]" value="1">
            <span><?= cms_t('Beim Speichern <b>sofort pushen</b> <span class="muted">(einmalig; nur wenn bereits veröffentlicht; Web-Push muss eingerichtet sein)</span>') ?></span>
          </label>
        </div>
      <?php endforeach; ?>
      <div class="actions"><button type="submit"><?= cms_h(cms_t('Speichern')) ?></button></div>
    </form>

  <?php elseif ($tab === 'push'): ?>
    <form method="post" class="card">
      <input type="hidden" name="do" value="send_push">
      <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Push-Nachricht senden')) ?></h2>
      <p class="muted"><?= cms_t('Geht sofort an alle Push-Abos (Web-Push muss eingerichtet sein, siehe <code>docs/PUSH.md</code>). Für getimte/automatische Pushes siehe News &amp; Cron.') ?></p>
      <label class="fld"><span><?= cms_h(cms_t('Titel')) ?></span><input type="text" name="ptitle" required></label>
      <label class="fld"><span><?= cms_h(cms_t('Text')) ?></span><textarea name="pbody"></textarea></label>
      <label class="fld"><span><?= cms_h(cms_t('Ziel-URL (optional)')) ?></span><input type="text" name="purl" placeholder="/"></label>
      <div style="margin-top:.5rem"><button type="submit" onclick="return confirm('<?= cms_j(cms_t('Push jetzt an alle Abos senden?')) ?>')"><?= cms_h(cms_t('Senden')) ?></button></div>
    </form>

    <?php try {
        $stat = push_stats_current();
        $hist = push_stats_recent(24); ?>
      <div class="card">
        <h2 style="margin-top:0"><?= cms_h(cms_t('Abo-Statistik')) ?> <span class="muted" style="font-weight:400"><?= cms_h(cms_t('(anonym)')) ?></span></h2>
        <p class="muted" style="margin-top:0"><?= cms_t('Aktuelle Push-Abos und gewählte Kategorien. Es werden ausschließlich <b>Zähler</b> gespeichert – keine personenbezogenen Daten. Der Verlauf wird vom Cron (~stündlich) fortgeschrieben.') ?></p>
        <div class="grid2">
          <div class="item"><div class="muted"><?= cms_h(cms_t('Abos gesamt')) ?></div><strong style="font-size:1.4rem"><?= (int) $stat['total'] ?></strong></div>
          <div class="item"><div class="muted"><?= cms_h(cms_t('Sicherheit')) ?></div><strong style="font-size:1.4rem"><?= (int) $stat['total'] ?></strong><div class="muted"><?= cms_h(cms_t('immer aktiv')) ?></div></div>
          <div class="item"><div class="muted"><?= cms_h(cms_t('Infos')) ?></div><strong style="font-size:1.4rem"><?= (int) $stat['c_info'] ?></strong></div>
          <div class="item"><div class="muted"><?= cms_h(cms_t('Line-Up')) ?></div><strong style="font-size:1.4rem"><?= (int) $stat['c_lineup'] ?></strong></div>
          <div class="item"><div class="muted"><?= cms_h(cms_t('Allgemein')) ?></div><strong style="font-size:1.4rem"><?= (int) $stat['c_general'] ?></strong></div>
        </div>
        <?php if ($hist): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
            <h2><?= cms_h(cms_t('Verlauf')) ?></h2>
            <a href="?export=push-stats" class="ghost" style="text-decoration:none;padding:.4rem .9rem;border-radius:999px;background:var(--surface2);border:1px solid var(--border);font-size:.85rem"><?= cms_h(cms_t('Als CSV exportieren')) ?></a>
          </div>
          <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.85rem">
              <tr style="text-align:left;color:var(--muted)"><th><?= cms_h(cms_t('Zeit')) ?></th><th><?= cms_h(cms_t('Gesamt')) ?></th><th><?= cms_h(cms_t('Infos')) ?></th><th><?= cms_h(cms_t('Line-Up')) ?></th><th><?= cms_h(cms_t('Allgemein')) ?></th></tr>
              <?php foreach ($hist as $r): ?>
                <tr style="border-top:1px solid var(--border)">
                  <td><?= cms_h((string) ($r['taken_at'] ?? '')) ?></td>
                  <td><?= (int) $r['total'] ?></td>
                  <td><?= (int) $r['c_info'] ?></td>
                  <td><?= (int) $r['c_lineup'] ?></td>
                  <td><?= (int) $r['c_general'] ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        <?php else: ?>
          <p class="muted"><?= cms_h(cms_t('Noch keine Verlaufsdaten – der erste Snapshot entsteht beim nächsten Cron-Lauf.')) ?></p>
        <?php endif; ?>
      </div>
    <?php } catch (Throwable $e) {
        echo '<div class="card"><p class="muted">' . cms_h(cms_t('Abo-Statistik nicht verfügbar (DB/Push nicht eingerichtet).')) . '</p></div>';
    } ?>

  <?php elseif ($tab === 'weather'):
    // Wetter-Anbieter + Standort (wie CrewCare). Gespeichert wird in
    // push/weather-settings.json (geschützt, überschreibt config.php['weather']).
    require_once __DIR__ . '/../weather-providers.php';
    $wcfg  = weather_config();
    $wprov = weather_provider_key($wcfg);
    $wcacheFile = __DIR__ . '/../weather-cache.json';
    $wcache = is_file($wcacheFile) ? json_decode((string) @file_get_contents($wcacheFile), true) : null;
  ?>
    <form method="post" action="?tab=weather" class="card">
      <input type="hidden" name="do" value="save_weather">
      <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Wetter-Anbieter')) ?></h2>
      <label class="fld"><span><?= cms_h(cms_t('Anbieter (Vorhersage fürs Home-Widget + Wetterseite)')) ?></span>
        <select name="wprovider">
          <?php foreach (WEATHER_PROVIDERS as $k => $meta): ?>
            <option value="<?= cms_h($k) ?>" <?= $wprov === $k ? 'selected' : '' ?>><?= cms_h($meta['label']) ?> – <?= cms_h($meta['hint']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="grid2">
        <label class="fld"><span><?= cms_h(cms_t('Breite (Latitude)')) ?></span>
          <input type="text" name="wlat" value="<?= cms_h((string) $wcfg['lat']) ?>" required></label>
        <label class="fld"><span><?= cms_h(cms_t('Länge (Longitude)')) ?></span>
          <input type="text" name="wlon" value="<?= cms_h((string) $wcfg['lon']) ?>" required></label>
      </div>
      <label class="fld"><span><?= cms_h(cms_t('Standortname (Anzeige in der App)')) ?></span>
        <input type="text" name="wlocation" value="<?= cms_h((string) $wcfg['location']) ?>"></label>
      <label class="fld"><span><?= cms_h(cms_t('TAWES-Station-ID (optional, NUR GeoSphere – Messwert „aktuell")')) ?></span>
        <input type="text" name="wstation" value="<?= cms_h((string) $wcfg['station_id']) ?>"></label>
      <label class="fld"><span><?= cms_h(cms_t('API-Key OpenWeather (nur bei Anbieter OpenWeather nötig)')) ?></span>
        <input type="password" name="wkey_ow" value="<?= cms_h((string) $wcfg['api_key_openweather']) ?>" autocomplete="off"></label>
      <label class="fld"><span><?= cms_h(cms_t('API-Key WeatherAPI.com (nur bei Anbieter WeatherAPI.com nötig)')) ?></span>
        <input type="password" name="wkey_wa" value="<?= cms_h((string) $wcfg['api_key_weatherapi']) ?>" autocomplete="off"></label>
      <div class="actions">
        <button type="submit"><?= cms_h(cms_t('Speichern')) ?></button>
        <button type="submit" name="test" value="1" class="ghost"><?= cms_t('Speichern &amp; Verbindung testen') ?></button>
      </div>
      <p class="muted" style="margin-bottom:0"><?= cms_t('Attribution in der App: „%s". GeoSphere/MET Norway sind ohne Key nutzbar; die Keys landen in <code>push/weather-settings.json</code> (per .htaccess gesperrt, nie im Repo).', cms_h(WEATHER_PROVIDERS[$wprov]['attribution'])) ?></p>
    </form>

    <div class="card">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Status')) ?></h2>
      <?php if (is_array($wcache)): ?>
        <p style="margin:0"><?= cms_t('Cache vom %s', cms_h((string) ($wcache['fetchedAt'] ?? '?'))) ?>
          <span class="muted"><?= cms_t('(Anbieter: %1$s, aktuell %2$s °C)', cms_h((string) ($wcache['provider'] ?? '?')), cms_h((string) ($wcache['current']['temp'] ?? '–'))) ?></span></p>
      <?php else: ?>
        <p class="muted" style="margin:0"><?= cms_h(cms_t('Kein Cache vorhanden – der nächste App-Abruf holt frische Daten (TTL 15 min).')) ?></p>
      <?php endif; ?>
      <form method="post" action="?tab=weather" style="margin-top:.8rem">
        <input type="hidden" name="do" value="clear_weather_cache">
        <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
        <button type="submit" class="ghost"><?= cms_h(cms_t('Wetter-Cache leeren')) ?></button>
      </form>
    </div>

  <?php elseif ($tab === 'stats'):
    // App-Nutzungsstatistik (anonym, aus app_stats_events via push/track.php).
    require_once __DIR__ . '/../stats-db.php';
    try {
        $pdo   = stats_db();
        $vTz   = new DateTimeZone('Europe/Vienna');
        $today = (new DateTimeImmutable('now', $vTz))->format('Y-m-d');
        $week  = (new DateTimeImmutable('now', $vTz))->modify('-6 days')->format('Y-m-d');
        // Meta-Events (_install/_standalone) zählen nicht als Seitenaufruf.
        $noMeta = "SUBSTR(page,1,1) <> '_'";

        $scope = static function (string $where, array $args) use ($pdo, $noMeta): array {
            $st = $pdo->prepare(
                "SELECT COUNT(*) views, COUNT(DISTINCT anon) users, COUNT(DISTINCT session) sessions
                 FROM app_stats_events WHERE $noMeta $where"
            );
            $st->execute($args);
            return $st->fetch() ?: ['views' => 0, 'users' => 0, 'sessions' => 0];
        };
        $kpi = [
            'Gesamt'        => $scope('', []),
            'Letzte 7 Tage' => $scope('AND day >= ?', [$week]),
            'Heute'         => $scope('AND day = ?', [$today]),
        ];

        $pages = $pdo->query(
            "SELECT page, COUNT(*) views, COUNT(DISTINCT anon) users
             FROM app_stats_events WHERE $noMeta GROUP BY page ORDER BY views DESC LIMIT 20"
        )->fetchAll();

        $daily = $pdo->query(
            "SELECT day, COUNT(*) views, COUNT(DISTINCT anon) users, COUNT(DISTINCT session) sessions
             FROM app_stats_events WHERE $noMeta GROUP BY day ORDER BY day DESC LIMIT 14"
        )->fetchAll();

        // PWA: appinstalled-Events + Geräte, die als installierte App starten.
        $installs   = (int) $pdo->query("SELECT COUNT(*) FROM app_stats_events WHERE page = '_install'")->fetchColumn();
        $standalone = (int) $pdo->query("SELECT COUNT(DISTINCT anon) FROM app_stats_events WHERE page = '_standalone'")->fetchColumn();
        $devices    = (int) $pdo->query('SELECT COUNT(DISTINCT anon) FROM app_stats_events')->fetchColumn();

        // Sprache/Theme: letzter bekannter Stand je Gerät (Window-Funktion, MySQL 8/SQLite).
        $distribution = static function (string $col) use ($pdo): array {
            return $pdo->query(
                "SELECT $col val, COUNT(*) n FROM (
                    SELECT anon, $col, ROW_NUMBER() OVER (PARTITION BY anon ORDER BY ts DESC) rn
                    FROM app_stats_events WHERE $col <> ''
                 ) t WHERE rn = 1 GROUP BY $col ORDER BY n DESC"
            )->fetchAll();
        };
        $langs  = $distribution('lang');
        $themes = $distribution('theme');

        // Stunden-Verteilung: Festivaltage (festival.json), sonst letzte 7 Tage.
        $festDays = [];
        foreach ((array) (cms_read_json('festival.json')['days'] ?? []) as $d) {
            if (!empty($d['dayStart'])) {
                $festDays[] = substr((string) $d['dayStart'], 0, 10);
            }
        }
        $hourly = [];
        $hourlyLabel = '';
        if ($festDays !== []) {
            $in = implode(',', array_fill(0, count($festDays), '?'));
            $st = $pdo->prepare(
                "SELECT SUBSTR(ts,12,2) h, COUNT(*) n FROM app_stats_events
                 WHERE $noMeta AND day IN ($in) GROUP BY SUBSTR(ts,12,2) ORDER BY h"
            );
            $st->execute($festDays);
            $hourly = $st->fetchAll();
            $hourlyLabel = cms_t('Festivaltage (%s)', implode(', ', $festDays));
        }
        if ($hourly === []) {
            $st = $pdo->prepare(
                "SELECT SUBSTR(ts,12,2) h, COUNT(*) n FROM app_stats_events
                 WHERE $noMeta AND day >= ? GROUP BY SUBSTR(ts,12,2) ORDER BY h"
            );
            $st->execute([$week]);
            $hourly = $st->fetchAll();
            $hourlyLabel = cms_t('letzte 7 Tage') . ($festDays !== [] ? cms_t(' – an den Festivaltagen noch keine Daten') : '');
        }
        $hourlyMax = max(1, ...array_map(static fn ($r) => (int) $r['n'], $hourly ?: [['n' => 1]]));

        $fmtN = static fn ($v): string => number_format((int) $v, 0, ',', '.');
    ?>
    <?php foreach ($kpi as $label => $k): ?>
      <div class="card">
        <h2 style="margin-top:0"><?= cms_h(cms_t((string) $label)) ?></h2>
        <div class="grid2">
          <div class="item"><div class="muted"><?= cms_h(cms_t('Eindeutige Nutzer')) ?></div><strong style="font-size:1.4rem"><?= $fmtN($k['users']) ?></strong></div>
          <div class="item"><div class="muted"><?= cms_h(cms_t('Sitzungen')) ?></div><strong style="font-size:1.4rem"><?= $fmtN($k['sessions']) ?></strong></div>
          <div class="item"><div class="muted"><?= cms_h(cms_t('Seitenaufrufe')) ?></div><strong style="font-size:1.4rem"><?= $fmtN($k['views']) ?></strong></div>
          <div class="item"><div class="muted"><?= cms_h(cms_t('Sitzungen je Nutzer')) ?></div><strong style="font-size:1.4rem"><?= (int) $k['users'] > 0 ? number_format((int) $k['sessions'] / (int) $k['users'], 1, ',', '.') : '–' ?></strong></div>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="card">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Meistgenutzte Bereiche')) ?></h2>
      <?php if ($pages === []): ?><p class="muted"><?= cms_h(cms_t('Noch keine Daten.')) ?></p><?php else: ?>
        <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:.85rem">
          <tr style="text-align:left;color:var(--muted)"><th><?= cms_h(cms_t('Seite')) ?></th><th style="text-align:right"><?= cms_h(cms_t('Aufrufe')) ?></th><th style="text-align:right"><?= cms_h(cms_t('Nutzer')) ?></th></tr>
          <?php foreach ($pages as $p): ?>
            <tr style="border-top:1px solid var(--border)">
              <td><?= cms_h((string) $p['page']) ?></td>
              <td style="text-align:right"><?= $fmtN($p['views']) ?></td>
              <td style="text-align:right"><?= $fmtN($p['users']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Stunden-Verteilung')) ?> <span class="muted" style="font-weight:400">(<?= cms_h($hourlyLabel) ?>)</span></h2>
      <?php if ($hourly === []): ?><p class="muted"><?= cms_h(cms_t('Noch keine Daten.')) ?></p><?php else: ?>
        <?php foreach ($hourly as $r): ?>
          <div style="display:flex;align-items:center;gap:.6rem;margin:.15rem 0">
            <span class="muted" style="width:3.2rem;font-variant-numeric:tabular-nums"><?= cms_h(cms_t('%s Uhr', (string) $r['h'])) ?></span>
            <div style="flex:1;background:var(--surface2);border-radius:6px;overflow:hidden">
              <div style="height:.9rem;width:<?= max(2, (int) round(100 * (int) $r['n'] / $hourlyMax)) ?>%;background:var(--accent)"></div>
            </div>
            <span style="width:3.5rem;text-align:right;font-variant-numeric:tabular-nums"><?= $fmtN($r['n']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="margin-top:0"><?= cms_h(cms_t('PWA-Installationen')) ?></h2>
      <div class="grid2">
        <div class="item"><div class="muted"><?= cms_h(cms_t('„Installiert"-Events (Android/Chrome)')) ?></div><strong style="font-size:1.4rem"><?= $fmtN($installs) ?></strong></div>
        <div class="item"><div class="muted"><?= cms_h(cms_t('Geräte mit App-Start (standalone)')) ?></div><strong style="font-size:1.4rem"><?= $fmtN($standalone) ?></strong><div class="muted"><?= cms_h(cms_t('von %s Geräten', $fmtN($devices))) ?><?= $devices > 0 ? ' (' . number_format(100 * $standalone / $devices, 0) . ' %)' : '' ?></div></div>
      </div>
      <p class="muted" style="margin-bottom:0"><?= cms_h(cms_t('iOS meldet kein Installations-Event – dort zählt nur der App-Start vom Home-Bildschirm (standalone).')) ?></p>
    </div>

    <div class="grid2">
      <div class="card" style="margin-bottom:0">
        <h2 style="margin-top:0"><?= cms_h(cms_t('Sprache')) ?></h2>
        <?php if ($langs === []): ?><p class="muted"><?= cms_h(cms_t('Noch keine Daten.')) ?></p><?php else: foreach ($langs as $r): ?>
          <div style="display:flex;justify-content:space-between;border-top:1px solid var(--border);padding:.3rem 0">
            <span><?= cms_h(strtoupper((string) $r['val'])) ?></span><strong><?= $fmtN($r['n']) ?></strong>
          </div>
        <?php endforeach; endif; ?>
        <p class="muted" style="margin-bottom:0"><?= cms_h(cms_t('je Gerät, letzter Stand')) ?></p>
      </div>
      <div class="card" style="margin-bottom:0">
        <h2 style="margin-top:0"><?= cms_h(cms_t('Theme')) ?></h2>
        <?php if ($themes === []): ?><p class="muted"><?= cms_h(cms_t('Noch keine Daten.')) ?></p><?php else: foreach ($themes as $r): ?>
          <div style="display:flex;justify-content:space-between;border-top:1px solid var(--border);padding:.3rem 0">
            <span><?= cms_h((string) $r['val'] === 'dark' ? cms_t('Dunkel') : ((string) $r['val'] === 'light' ? cms_t('Hell') : (string) $r['val'])) ?></span><strong><?= $fmtN($r['n']) ?></strong>
          </div>
        <?php endforeach; endif; ?>
        <p class="muted" style="margin-bottom:0"><?= cms_h(cms_t('je Gerät, letzter Stand')) ?></p>
      </div>
    </div>

    <div class="card" style="margin-top:1rem">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Push-Abo-Verlauf')) ?></h2>
      <?php try {
          $pStat = push_stats_current();
          $pHist = push_stats_recent(24); ?>
        <p style="margin-top:0"><?= cms_t('Aktuell %s Abos', '<strong>' . (int) $pStat['total'] . '</strong>') ?>
          <span class="muted"><?= cms_t('(Infos %1$d · Line-Up %2$d · Allgemein %3$d)', (int) $pStat['c_info'], (int) $pStat['c_lineup'], (int) $pStat['c_general']) ?></span>
          · <a href="?export=push-stats"><?= cms_h(cms_t('Als CSV exportieren')) ?></a></p>
        <?php if ($pHist): ?>
          <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:.85rem">
            <tr style="text-align:left;color:var(--muted)"><th><?= cms_h(cms_t('Zeit')) ?></th><th><?= cms_h(cms_t('Gesamt')) ?></th><th><?= cms_h(cms_t('Infos')) ?></th><th><?= cms_h(cms_t('Line-Up')) ?></th><th><?= cms_h(cms_t('Allgemein')) ?></th></tr>
            <?php foreach ($pHist as $r): ?>
              <tr style="border-top:1px solid var(--border)">
                <td><?= cms_h((string) ($r['taken_at'] ?? '')) ?></td>
                <td><?= (int) $r['total'] ?></td><td><?= (int) $r['c_info'] ?></td>
                <td><?= (int) $r['c_lineup'] ?></td><td><?= (int) $r['c_general'] ?></td>
              </tr>
            <?php endforeach; ?>
          </table></div>
        <?php else: ?>
          <p class="muted" style="margin-bottom:0"><?= cms_h(cms_t('Noch keine Verlaufsdaten – der erste Snapshot entsteht beim nächsten Cron-Lauf.')) ?></p>
        <?php endif;
      } catch (Throwable $e) {
          echo '<p class="muted" style="margin-bottom:0">' . cms_h(cms_t('Push-Abo-Verlauf nicht verfügbar (Push nicht eingerichtet).')) . '</p>';
      } ?>
    </div>

    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
      <p class="muted" style="margin:0;flex:1;min-width:14rem"><?= cms_h(cms_t('Anonym erhoben: zufällige Geräte-/Sitzungskennung, Seitenname, Sprache/Theme, Zeitpunkt. Keine IP-Adressen, keine User-Agents, keine personenbezogenen Daten.')) ?></p>
      <form method="post" action="?tab=stats" style="margin:0">
        <input type="hidden" name="do" value="reset_stats">
        <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
        <button type="submit" class="ghost" style="border-color:#e4572e;color:#e4572e"
          onclick="return confirm('<?= cms_j(cms_t('Wirklich ALLE Statistik-Daten unwiderruflich löschen? (Push-Abo-Verlauf bleibt erhalten)')) ?>')"><?= cms_h(cms_t('Statistik zurücksetzen')) ?></button>
      </form>
    </div>
    <?php } catch (Throwable $e) {
        echo '<div class="card"><p class="muted">' . cms_t('Statistik nicht verfügbar: Datenbank nicht erreichbar (MySQL-Zugang in <code>push/config.php</code> prüfen).') . '</p></div>';
    } ?>

  <?php elseif ($tab === 'log'):
    // Server-Protokoll (app_log): Push-Versand, Logins, Wetter-/Client-Fehler.
    require_once __DIR__ . '/../log.php';
    $fLevel  = (string) ($_GET['level'] ?? '');
    $fSource = (string) ($_GET['src'] ?? '');
    $entries = app_log_recent($fLevel ?: null, $fSource ?: null, 200);
    $sources = app_log_sources();
    $badge = static function (string $level): string {
        $bg = ['error' => '#e4572e', 'warn' => '#b58900'][$level] ?? 'var(--surface2)';
        $fg = isset(['error' => 1, 'warn' => 1][$level]) ? '#000' : 'var(--muted)';
        return '<span style="background:' . $bg . ';color:' . $fg . ';border-radius:6px;padding:.05rem .45rem;font-size:.75rem;font-weight:700">' . cms_h($level) . '</span>';
    };
  ?>
    <form method="get" class="card" style="display:flex;gap:.6rem;align-items:end;flex-wrap:wrap">
      <input type="hidden" name="tab" value="log">
      <label class="fld" style="flex:1;min-width:9rem;margin:0"><span><?= cms_h(cms_t('Stufe')) ?></span>
        <select name="level">
          <option value=""><?= cms_h(cms_t('alle')) ?></option>
          <?php foreach (['info', 'warn', 'error'] as $lv): ?>
            <option value="<?= $lv ?>" <?= $fLevel === $lv ? 'selected' : '' ?>><?= $lv ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="fld" style="flex:1;min-width:9rem;margin:0"><span><?= cms_h(cms_t('Quelle')) ?></span>
        <select name="src">
          <option value=""><?= cms_h(cms_t('alle')) ?></option>
          <?php foreach ($sources as $s): ?>
            <option value="<?= cms_h($s) ?>" <?= $fSource === $s ? 'selected' : '' ?>><?= cms_h($s) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit"><?= cms_h(cms_t('Filtern')) ?></button>
    </form>

    <div class="card">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Protokoll')) ?> <span class="muted" style="font-weight:400"><?= cms_h(cms_t('(neueste zuerst, max. 200)')) ?></span></h2>
      <?php if ($entries === []): ?>
        <p class="muted" style="margin-bottom:0"><?= cms_h(cms_t('Keine Einträge') . (($fLevel || $fSource) ? cms_t(' für diesen Filter') : cms_t(' – das Protokoll füllt sich mit Push-Versand, Logins, Wetter- und App-Fehlern'))) ?>.</p>
      <?php else: ?>
        <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:.85rem">
          <tr style="text-align:left;color:var(--muted)"><th><?= cms_h(cms_t('Zeit')) ?></th><th><?= cms_h(cms_t('Stufe')) ?></th><th><?= cms_h(cms_t('Quelle')) ?></th><th><?= cms_h(cms_t('Meldung')) ?></th></tr>
          <?php foreach ($entries as $e): ?>
            <tr style="border-top:1px solid var(--border);vertical-align:top">
              <td style="white-space:nowrap"><?= cms_h(substr((string) $e['ts'], 0, 19)) ?></td>
              <td><?= $badge((string) $e['level']) ?></td>
              <td><?= cms_h((string) $e['source']) ?></td>
              <td><?= cms_h((string) $e['message']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table></div>
      <?php endif; ?>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:.6rem">
        <p class="muted" style="margin:0;flex:1;min-width:14rem"><?= cms_h(cms_t('Aufbewahrung ~90 Tage (ältere Einträge werden automatisch bereinigt). Keine IPs, keine personenbezogenen Daten.')) ?></p>
        <form method="post" action="?tab=log" style="margin:0">
          <input type="hidden" name="do" value="clear_log">
          <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
          <button type="submit" class="ghost" style="border-color:#e4572e;color:#e4572e"
            onclick="return confirm('<?= cms_j(cms_t('Protokoll wirklich komplett leeren?')) ?>')"><?= cms_h(cms_t('Protokoll leeren')) ?></button>
        </form>
      </div>
    </div>

  <?php elseif ($tab === 'branding'):
    // Kunden-Branding (Paket Y): Farben/Logo/Titel/Schrift/PWA-Icons, alles
    // vorausgefüllt mit den Build-Standardwerten; wirkt ohne Neu-Build.
    $b = cms_branding();
    $bTitle = (string) ($b['title'] ?? '');
    $bShort = (string) ($b['shortName'] ?? '');
    $bFont  = isset(BRANDING_FONTS[(string) ($b['font'] ?? '')]) ? (string) $b['font'] : 'standard';
    $bLogo  = (string) ($b['logo'] ?? '');
    $bIcons = (string) ($b['icons'] ?? '');
    $bc = is_array($b['colors'] ?? null) ? $b['colors'] : [];
    $cAccent  = cms_branding_hex($bc['accent'] ?? '') ?? BRANDING_DEFAULT_COLORS['accent'];
    $cAccent2 = cms_branding_hex($bc['accent2'] ?? '') ?? BRANDING_DEFAULT_COLORS['accent2'];
    $cVal = static function (string $group, string $key) use ($bc): string {
        return cms_branding_hex($bc[$group][$key] ?? '') ?? BRANDING_DEFAULT_COLORS[$group][$key];
    };
    $colorField = static function (string $name, string $label, string $value): string {
        return '<label class="fld"><span>' . cms_h($label) . '</span>'
            . '<input type="color" name="' . cms_h($name) . '" value="' . cms_h($value) . '" style="height:2.4rem;padding:.2rem">'
            . '</label>';
    };
    $tokenLabels = [
        'bg'       => cms_t('Hintergrund'),
        'surface'  => cms_t('Fläche'),
        'surface2' => cms_t('Fläche 2'),
        'text'     => cms_t('Text'),
        'muted'    => cms_t('Gedämpfter Text'),
        'border'   => cms_t('Rahmen'),
    ]; ?>
    <form method="post" class="card">
      <input type="hidden" name="do" value="save_branding">
      <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Branding')) ?></h2>
      <p class="muted"><?= cms_h(cms_t('Farben, Logo, Titel, Schrift und App-Icons der Besucher-App – vorausgefüllt mit den Standardwerten. Änderungen wirken ohne Neu-Build binnen ~2 Minuten.')) ?></p>

      <h2><?= cms_h(cms_t('Titel & App-Name')) ?></h2>
      <label class="fld"><span><?= cms_h(cms_t('Browser-Titel / App-Name (leer = Festivalname)')) ?></span>
        <input type="text" name="btitle" value="<?= cms_h($bTitle) ?>"></label>
      <label class="fld"><span><?= cms_h(cms_t('Kurzname (Home-Bildschirm, max. 12 Zeichen; leer = Festival-Kurzname)')) ?></span>
        <input type="text" name="bshort" maxlength="12" value="<?= cms_h($bShort) ?>"></label>

      <h2><?= cms_h(cms_t('Schrift')) ?></h2>
      <label class="fld"><span><?= cms_h(cms_t('Schrift-Set (systemweite Schriften, keine Downloads nötig)')) ?></span>
        <select name="bfont">
          <?php foreach (BRANDING_FONTS as $fk => $fl): ?>
            <option value="<?= cms_h($fk) ?>" <?= $bFont === $fk ? 'selected' : '' ?>><?= cms_h(cms_t($fl)) ?></option>
          <?php endforeach; ?>
        </select></label>

      <h2><?= cms_h(cms_t('Farben')) ?></h2>
      <div class="grid2">
        <?= $colorField('c_accent', cms_t('Akzentfarbe'), $cAccent) ?>
        <?= $colorField('c_accent2', cms_t('Sekundärfarbe'), $cAccent2) ?>
      </div>
      <h2 style="font-size:.95rem"><?= cms_h(cms_t('Dunkles Theme')) ?></h2>
      <div class="grid2">
        <?php foreach ($tokenLabels as $tk => $tl): ?>
          <?= $colorField("c_d_$tk", $tl, $cVal('dark', $tk)) ?>
        <?php endforeach; ?>
      </div>
      <h2 style="font-size:.95rem"><?= cms_h(cms_t('Helles Theme')) ?></h2>
      <div class="grid2">
        <?php foreach ($tokenLabels as $tk => $tl): ?>
          <?= $colorField("c_l_$tk", $tl, $cVal('light', $tk)) ?>
        <?php endforeach; ?>
      </div>
      <label class="row" style="margin-top:.4rem">
        <input type="checkbox" name="resetColors" value="1">
        <span><?= cms_h(cms_t('Beim Speichern alle Farben auf die Standardwerte zurücksetzen')) ?></span>
      </label>

      <div style="margin-top:1rem"><button type="submit"><?= cms_h(cms_t('Speichern')) ?></button></div>
    </form>

    <div class="card">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Logo')) ?></h2>
      <p class="muted"><?= cms_h(cms_t('Ersetzt das Kopfzeilen-Logo der App (Querformat empfohlen, wird 36 px hoch angezeigt). Leer = mitgeliefertes Logo.')) ?></p>
      <?php if ($bLogo !== ''): ?>
        <p><img src="<?= cms_h($bLogo) ?>" alt="" style="height:36px;width:auto;max-width:300px;object-fit:contain;background:#000;padding:.2rem;border-radius:6px"></p>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="do" value="upload_branding_logo">
        <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
        <input type="file" name="file" accept=".webp,.png,.jpg,.jpeg,.svg" required>
        <button type="submit"><?= cms_h(cms_t('Hochladen')) ?></button>
      </form>
      <?php if ($bLogo !== ''): ?>
        <form method="post" style="margin-top:.6rem">
          <input type="hidden" name="do" value="delete_branding_logo">
          <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
          <button type="submit" class="ghost"><?= cms_h(cms_t('Logo entfernen')) ?></button>
        </form>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="margin-top:0"><?= cms_h(cms_t('App-Icon (PWA)')) ?></h2>
      <p class="muted"><?= cms_h(cms_t('Quadratisches PNG hochladen (mindestens 192×192, empfohlen 512×512; transparenter Hintergrund möglich). Daraus werden die Install-Icons erzeugt (192, 512 und „maskable" mit der dunklen Hintergrundfarbe). Bereits installierte Apps übernehmen neue Icons erst verzögert.')) ?></p>
      <?php if ($bIcons !== ''): ?>
        <p style="display:flex;gap:.8rem;align-items:center">
          <img src="/data/uploads/pwa-icon-192.png?v=<?= cms_h($bIcons) ?>" alt="" style="height:48px;width:48px;border-radius:10px">
          <img src="/data/uploads/pwa-icon-maskable.png?v=<?= cms_h($bIcons) ?>" alt="" style="height:48px;width:48px;border-radius:24px">
          <span class="muted"><?= cms_h(cms_t('Vorschau')) ?> (192 / maskable)</span>
        </p>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="do" value="upload_branding_icon">
        <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
        <input type="file" name="file" accept=".png" required>
        <button type="submit"><?= cms_h(cms_t('Hochladen')) ?></button>
      </form>
      <?php if ($bIcons !== ''): ?>
        <form method="post" style="margin-top:.6rem">
          <input type="hidden" name="do" value="delete_branding_icon">
          <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
          <button type="submit" class="ghost"><?= cms_h(cms_t('Icons entfernen')) ?></button>
        </form>
      <?php endif; ?>
    </div>

    <?php
    // Intro-Video auf Home (volle Breite oberhalb des Newsfeeds).
    $hv = cms_read_config()['homeVideo'] ?? [];
    $hvUrl = (string) ($hv['url'] ?? '');
    $hvSource = ($hv['source'] ?? 'link') === 'mscloud' ? 'mscloud' : 'link';
    $hvEnabled = !empty($hv['enabled']); ?>
    <div class="card">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Intro-Video (Home)')) ?></h2>
      <p class="muted"><?= cms_h(cms_t('Wird in voller Breite oberhalb des Newsfeeds angezeigt. Quelle „Link/Datei": direkte Videodatei (per FTP hochgeladen oder https-Link; YouTube/Vimeo werden automatisch als Player eingebettet). Quelle „Microsoft-Cloud": in OneDrive/SharePoint „Einbetten" wählen und die iframe-URL eintragen.')) ?></p>
      <form method="post">
        <input type="hidden" name="do" value="save_home_video">
        <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
        <label><?= cms_h(cms_t('Quelle')) ?>
          <select name="hv_source">
            <option value="link" <?= $hvSource === 'link' ? 'selected' : '' ?>><?= cms_h(cms_t('Link/Datei (FTP, YouTube, Vimeo)')) ?></option>
            <option value="mscloud" <?= $hvSource === 'mscloud' ? 'selected' : '' ?>><?= cms_h(cms_t('Microsoft-Cloud (OneDrive/SharePoint-Einbetten-Link)')) ?></option>
          </select>
        </label>
        <label><?= cms_h(cms_t('Video-URL (leer = Video entfernen)')) ?>
          <input type="text" name="hv_url" value="<?= cms_h($hvUrl) ?>" placeholder="/data/uploads/intro.mp4">
        </label>
        <label style="display:flex;gap:.5rem;align-items:center">
          <input type="checkbox" name="hv_enabled" value="1" <?= $hvEnabled ? 'checked' : '' ?> style="width:auto">
          <?= cms_h(cms_t('Video auf der Home-Seite anzeigen')) ?>
        </label>
        <button type="submit"><?= cms_h(cms_t('Speichern')) ?></button>
      </form>
    </div>

  <?php elseif ($tab === 'update'):
    // 1-Klick-Updater (Komfort): Update-Paket hochladen und einspielen.
    $curVersion = cms_update_version();
    $zipAvailable = class_exists('ZipArchive') || extension_loaded('phar'); ?>
    <div class="card">
      <h2 style="margin-top:0"><?= cms_h(cms_t('App-Update einspielen')) ?></h2>
      <p class="muted"><?= cms_h(cms_t('Installierte Version: %s', $curVersion !== '' ? $curVersion : cms_t('unbekannt (keine VERSION-Datei – Installation stammt nicht aus einem Release-Paket)'))) ?></p>
      <p><?= cms_h(cms_t('Hier das Update-Paket (festivadget-update-v*.zip) hochladen. Deine Inhalte bleiben unangetastet: data/ (Inhalte, Uploads, Branding), push/config.php sowie CMS-/Wetter-Einstellungen werden nie überschrieben.')) ?></p>
      <?php if (!$zipAvailable): ?>
        <p class="error"><?= cms_h(cms_t('Am Server fehlt die PHP-Erweiterung zip (und phar).')) ?></p>
      <?php else: ?>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= cms_h($csrf) ?>">
          <input type="hidden" name="do" value="apply_update">
          <input type="file" name="file" accept=".zip" required>
          <button type="submit" onclick="return confirm(<?= cms_j(cms_t('Update jetzt einspielen? Die App ist währenddessen kurz inkonsistent.')) ?>)"><?= cms_h(cms_t('Update einspielen')) ?></button>
        </form>
        <p class="muted" style="margin-top:.6rem"><?= cms_h(cms_t('Maximale Upload-Größe (PHP): %s', (string) ini_get('upload_max_filesize'))) ?></p>
        <p class="muted"><?= cms_h(cms_t('Alternative ohne CMS (Minimal): Update-Paket entpacken und per FTP über die Installation kopieren – data/ und push/config.php sind im Update-Paket nicht enthalten. Details: Hilfe → Installation.')) ?></p>
      <?php endif; ?>
    </div>

  <?php elseif ($tab === 'help'):
    // Handbücher: Markdown-Dateien, die mit der App unter /docs/ ausgeliefert
    // werden (Build kopiert sie nach dist/docs; Upload via deploy-data.bat full).
    // Später sollen hier PDF-Varianten verlinkt werden – die Liste bleibt datengetrieben.
    $docsDir = dirname(dirname(cms_data_path('version.json'))) . '/docs';
    $manuals = [
        ['file' => 'INSTALL',        'title' => cms_t('Installation (Web-Installer)'), 'desc' => cms_t('Release-Paket hochladen und im Browser einrichten – ohne Build-Maschine.')],
        ['file' => 'ADMIN',          'title' => cms_t('Admin-UI (CMS)'),        'desc' => cms_t('Bedienung dieser Admin-Oberfläche (Tabs, Overrides, Importer).')],
        ['file' => 'DATEN',          'title' => cms_t('Daten pflegen & anbinden'), 'desc' => cms_t('Inhalte ersetzen, Joomla/WordPress-Anbindung, Felder & Icons.')],
        ['file' => 'PUSH',           'title' => cms_t('Web-Push einrichten'),   'desc' => cms_t('VAPID, MySQL, Cron, Push-Kategorien, „Mein Plan"-Erinnerungen.')],
        ['file' => 'TELEGRAM',       'title' => cms_t('Telegram-Live-News'),    'desc' => cms_t('Bot einrichten, Tags, Befehle, Gruppen.')],
        ['file' => 'IMPLEMENTATION', 'title' => cms_t('Technisches Konzept'),   'desc' => cms_t('Architektur, Datenmodell, Caching, Roadmap.')],
    ];
    // Englisch ist die Basis-Datei (GitHub-Standard), Deutsch traegt .de.
    $docLangs = ['de' => '.de', 'en' => '', 'fr' => '.fr', 'es' => '.es'];
    $anyDoc = false; ?>
    <div class="card">
      <h2 style="margin-top:0"><?= cms_h(cms_t('Handbücher')) ?></h2>
      <p class="muted"><?= cms_h(cms_t('Alle Handbücher als Markdown-Dateien, jeweils in Deutsch, Englisch, Französisch und Spanisch. Sie werden mit der App ausgeliefert (Ordner /docs).')) ?></p>
      <?php foreach ($manuals as $m): ?>
        <div class="item">
          <div class="head"><strong><?= cms_h($m['title']) ?></strong></div>
          <p class="muted" style="margin:.3rem 0 .5rem"><?= cms_h($m['desc']) ?></p>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <?php foreach ($docLangs as $dl => $suffix):
                $fname = $m['file'] . $suffix . '.md';
                if (is_file($docsDir . '/' . $fname)):
                    $anyDoc = true; ?>
                  <a href="/docs/<?= cms_h($fname) ?>" target="_blank" rel="noopener"
                     style="padding:.25rem .7rem;border-radius:999px;background:var(--surface2);border:1px solid var(--border);font-size:.85rem;text-decoration:none<?= cms_lang() === $dl ? ';border-color:var(--accent)' : '' ?>"><?= cms_h(strtoupper($dl)) ?></a>
            <?php endif; endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$anyDoc): ?>
        <p class="muted" style="margin-bottom:0"><?= cms_h(cms_t('Noch keine Handbücher auf dem Server – sie kommen mit dem nächsten App-Deployment („deploy-data.bat full", Ordner /docs).')) ?></p>
      <?php endif; ?>
    </div>

  <?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>
