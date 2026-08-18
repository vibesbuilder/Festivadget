# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden hier dokumentiert.
Format nach [Keep a Changelog](https://keepachangelog.com/de/1.0.0/), Versionierung nach [SemVer](https://semver.org/lang/de/).

## [Unreleased]

## [1.3.2] – 2026-08-18

### Security

- **Abhängigkeits-Updates nach Dependabot-Meldungen:** react-router-dom auf
  6.30.4 (behebt Open-Redirect/XSS- und SSR-Advisories in der ausgelieferten
  App); transitive Pakete nanoid, postcss, brace-expansion und fast-uri auf
  gepatchte Versionen. Verbleibende Meldungen betreffen nur den lokalen
  Dev-Server (vite/esbuild, Fix erfordert Vite-Major-Upgrade – geplant) bzw.
  eine Router-Advisory, deren Patch erst in react-router v7 existiert.

### Changed

- README: Installations-Sektion prominent („unter 5 Minuten"), Hinweis auf
  die Schwester-App CrewCare (SaaS auf Anfrage, inkl. Festivadget).

## [1.3.1] – 2026-08-18

### Fixed

- **Letzte hart kodierte deutsche UI-Texte übersetzt:** Karten-Titel,
  „Alle"-Filter-Chip auf der Karte, Suche-Titel und das Such-aria-Label
  laufen jetzt über i18n (de/en/fr/es) – aufgefallen beim Erstellen der
  englischen Screenshots.

## [1.3.0] – 2026-08-18

### Fixed

- **Release-Build: Instanz-Werte wurden nicht zuverlässig geleert.**
  PowerShells `$env:X = ""` löscht die Variable statt sie zu leeren – dadurch
  gewann die lokale `.env` und der (öffentliche) RID-VAPID-Key steckte in den
  v1.2.x-Paketen (Kunden-Push-Abos wären mit falschem Key erstellt worden und
  hätten nie zugestellt). Jetzt baut `build-release.ps1` mit
  `vite build --mode release` und der neuen Datei `.env.release` (leere
  Overrides); zusätzlich UTF-8-sauberes Ersetzen der Build-Strings.
  Außerdem: CMS-Titel-Ermittlung bricht vor der Installation (ohne
  `push/config.php`) nicht mehr die Seite; Home-Datum lokalisiert
  (`formatDateRange` mit optionalem Locale-Parameter in `@rid/core`,
  Default unverändert `de`); „Alle"-Link auf Home übersetzt; neutraler
  Home-Titel-Fallback.

### Changed

- **Standard-Sprache des Projekts ist jetzt Englisch.** App: neue
  Default-Kette `app-config.json → languageDefault` (CMS → Einstellungen,
  neues Dropdown) → Instanz-Env `VITE_DEFAULT_LANGUAGE` (RID: `de`) →
  Englisch; greift nur, solange der Gast nicht selbst gewählt hat
  (`languageExplicit` im UI-Store). CMS: ohne gespeicherte Sprachwahl
  startet die Oberfläche auf Englisch. Manuals: Englisch ist die
  Basis-Datei (`ADMIN.md`, `PUSH.md`, …), Deutsch trägt jetzt das Suffix
  `.de.md` – alle Querverweise, READMEs, der CMS-Hilfe-Tab und der
  Installer sind umgestellt. Beispieldaten („Gadget Festival") komplett
  auf Englisch übersetzt. Release-Paket: `lang`-Attribut und
  Beschreibung in `index.html`/`manifest.webmanifest` auf Englisch;
  der Installer war bereits EN-first (Deutsch nur bei deutschem Browser).

## [1.2.1] – 2026-08-18

### Fixed

- **CMS: „Sicherheits-Token ungültig" direkt nach dem Login** – der
  POST-Dispatcher lief fälschlich auch für `do=login` durch (MySQL-Test des
  Release-Pakets). Login ist jetzt ausgenommen.
- **CMS-Kopfzeile/Titel datengetrieben** – statt hart kodiertem
  „ROCK IM DORF · Admin" zeigt das CMS den Festivalnamen aus
  `app-festival.json`/`festival.json` (Fallback „Festivadget").
- **Installer: Pflicht-Check „Webroot"** – Unterordner-Installationen
  (z. B. `/testapp/`) führen zu einer weißen Seite, weil der App-Build
  absolute Pfade nutzt. Der Installer blockiert das jetzt mit klarem
  Hinweis (Subdomain verwenden); Doku entsprechend geschärft.

## [1.2.0] – 2026-08-18

### Added

- **Intro-Video auf Home (CMS-Tab „Branding").** Volle Breite oberhalb des
  Newsfeeds, per Checkbox aktivierbar. Quellen wie bei CrewCare: „Link/Datei"
  (direkte Videodatei per FTP/https als `<video>`, YouTube/Vimeo automatisch
  als Player-iframe) oder „Microsoft-Cloud" (OneDrive/SharePoint-„Einbetten"-
  URL als iframe). Gespeichert in `app-config.json` → `homeVideo`
  (`{url, source, enabled}`); ohne Eintrag ändert sich nichts.
  Neue Komponente `src/components/HomeVideo.tsx`.

- **App-Updates ohne Build-Maschine (Minimal + Komfort).**
  `tools/build-release.ps1` erzeugt zusätzlich das Update-Paket
  `release/festivadget-update-v<version>.zip` (wie das Release, aber ohne
  `data/` und `install/`) sowie eine `VERSION`-Datei im Paket. **Minimal:**
  Update-Paket per FTP über die Installation kopieren – Kundeninhalte sind
  gar nicht erst enthalten. **Komfort:** neuer CMS-Tab „Update"
  (`push/cms/update.php`, viersprachig) spielt das hochgeladene Paket per
  1-Klick ein: ZipArchive mit PharData-Fallback, Plausibilitäts- und
  Pfad-Härtung (Traversal/absolute Pfade), volle Release-Pakete (mit `data/`)
  werden abgelehnt, Schutzliste (`data/`, `install/`, `push/config.php`,
  CMS-/Wetter-Einstellungen) wird nie überschrieben; zeigt installierte
  Version und PHP-Upload-Limit. Doku: `docs/INSTALL*.md` (Updates-Abschnitt).

- **Release-Paket + Web-Installer (Joomla-Prinzip).** `tools/build-release.ps1`
  schnürt `release/festivadget-v<version>.zip`: neutraler App-Build (ohne
  Instanz-Werte), `push/` inkl. `cms/` und `vendor/` (ohne `config.php`!) und
  der neue Web-Installer `install/index.php`. Kunden laden das Paket per FTP
  in den Webroot und öffnen `/install/` (DE/EN): Voraussetzungs-Check
  (PHP ≥ 8.1, Extensions, Schreibrechte), CMS-Admin-Passwort, optional
  MySQL für Web-Push – die VAPID-Schlüssel werden dabei **serverseitig**
  erzeugt (gebündeltes `push/vendor`). Der Installer schreibt
  `push/config.php`, legt `data/uploads/` an, sperrt sich danach selbst
  (config vorhanden) und kann sich per Knopf selbst löschen. **Keine
  Build-Maschine beim Kunden nötig.** Doku: `docs/INSTALL*.md`
  (4 Sprachen, auch im CMS-Hilfe-Tab verlinkt), READMEs.

### Changed

- **VAPID-Public-Key kommt zur Laufzeit vom Server.** Neuer Endpoint
  `push/vapid.php` liefert den öffentlichen Key aus `push/config.php`; der
  Client (`src/lib/push.ts`) holt ihn beim Start einmalig und merkt ihn sich in
  `localStorage` (offline-fähig). `VITE_VAPID_PUBLIC_KEY` in der Build-Env ist
  nur noch optionaler Fallback – ein Kundenbuild braucht damit keinen
  Push-Key mehr zur Build-Zeit (Schritt Richtung „ohne Build-Maschine").
  Doku (`docs/PUSH*.md`, READMEs, `.env.example`) entsprechend angepasst.

### Added

- **Kunden-Branding im CMS (Tab „Branding").** Farben (Akzente + komplette
  Dunkel-/Hell-Paletten), Schrift-Set (Standard/System/Serifen/Plakat als reine
  CSS-Stacks), eigenes Header-Logo, App-Titel + Kurzname sowie PWA-Icons –
  alles zur Laufzeit ohne neuen Build, gespeichert in `app-config.json` →
  `branding`. Die App setzt die `--rid-*`-Design-Tokens je aktivem Theme
  (`src/lib/branding.ts`); entfernte Werte fallen auf den Build-Stand zurück.
  Aus einem hochgeladenen quadratischen PNG erzeugt der Server per GD die
  Icons 192/512 px + maskable (dunkle Hintergrundfarbe, 80 % Safe-Zone). Neu:
  `push/manifest.php` liefert ein dynamisches PWA-Manifest (Name, Farben,
  Icons aus dem CMS); die App tauscht den Manifest-Link zur Laufzeit, sobald
  Branding-Titel/-Icons gesetzt sind – ohne PHP-Backend gilt weiter das
  statische `manifest.webmanifest`. Doku: `docs/ADMIN*.md`.

- **Einstellungen: Hintergrundbild wählbar.** Im CMS unter Einstellungen gibt es
  jetzt ein Dropdown „Hintergrundbild" mit allen Uploads aus dem Tab „Bilder"
  (`app-config.json` → `backgroundImage`, nur `/data/uploads/…`-Pfade). Die App
  überschreibt damit zur Laufzeit die Build-Grafik (`--rid-bg-image`); „Standard"
  stellt die mitgelieferte Grafik wieder her. Wirkt nur, solange
  „Hintergrundgrafik anzeigen" aktiv ist.
- **CMS-Tab „Hilfe".** Verlinkt alle Handbücher (ADMIN, DATEN, PUSH, TELEGRAM,
  IMPLEMENTATION) als Markdown in allen vier Sprachen. Der App-Build kopiert die
  Dateien nach `dist/docs/` (Vite-Plugin, bewusst nicht im SW-Precache); auf dem
  Server liegen sie dann unter `/docs/<Name>.md`. Fehlende Dateien blendet der
  Tab aus und zeigt einen Deploy-Hinweis. (PDF-Varianten später geplant – die
  Liste ist datengetrieben.)

### Fixed

- **Protokoll: Fremd-Rauschen gefiltert.** Fehler aus Browser-Erweiterungen und
  In-App-Browser-Bridges („Script error", `webkit.messageHandlers`,
  `runtime.sendMessage`, „Java object is gone", `…-extension://`) werden nicht
  mehr als Client-Fehler ins Server-Protokoll gemeldet – es sind keine
  App-Fehler.
- **IndexedDB-Ausfälle abgefangen** (Safari-Privatmodus/Lockdown, „Connection to
  Indexed Database server lost"): Favoriten-Store und Versions-Cache werfen
  keine Unhandled Rejections mehr; Favoriten gelten dann nur für die Sitzung,
  der Versionsabruf bleibt erfolgreich.

### Added

- **App-Sprachen Französisch & Spanisch:** Die App ist jetzt viersprachig
  (Deutsch/Englisch/Französisch/Spanisch). Die Sprachwahl unter **Mehr** ist vom
  De/En-Umschalter auf eine Chip-Auswahl mit allen vier Sprachen umgebaut; die
  gewählte Sprache wird beim App-Start wieder angewendet (vorher ging die
  Auswahl beim Neuladen verloren). Wetter-Datumsformate folgen der Sprache;
  der Install-Hinweis ist ebenfalls übersetzt.
- **CMS mehrsprachig (de/en/fr/es):** Die komplette Admin-Oberfläche
  (`push/cms/`) läuft über eine Übersetzungsschicht (`push/cms/i18n.php`,
  Deutsch = Quellsprache). Die CMS-Sprache wird im Tab **Einstellungen** →
  „CMS-Sprache" umgestellt (serverseitig in `push/cms-settings.json`, per
  `.htaccess` gesperrt, gitignored). **Einstellungen ist jetzt der Start-Tab**
  beim Öffnen der CMS-URL.
- **Doku in Englisch, Französisch und Spanisch:** `PUSH`, `ADMIN`, `DATEN`,
  `TELEGRAM` (unter `docs/`) und `IMPLEMENTATION` liegen jetzt zusätzlich als
  `.en`/`.fr`/`.es`-Varianten vor, mit Sprach-Kreuzlinks in allen Dateien; die
  englische README verlinkt die englischen Varianten.

### Changed

- **Lizenz: GNU AGPLv3** statt MIT (LICENSE, READMEs, `package.json`).
  Inhalte/Logos/Karten des Referenzprojekts bleiben ausgenommen.

### Added

- **Home-Kopf ausblendbar:** Neue Checkbox „Home: Festivalname und Datum anzeigen" im Admin unter **Einstellungen** (`app-config.json` → `homeHeader`). Abgewählt verschwindet der Kopf (z. B. „ROCK IM DORF Festival 2026 · 31. Juli – 2. August 2026") ganz oben auf Home; Standard bleibt sichtbar. Übernahme in der App wie üblich binnen ~2 Minuten.

- **Festival-Kurzname (`shortName`) in den Eckdaten** (`festival.json`, z. B. „ROCK IM DORF"). Steuert das **Home-Bildschirm-Label** beim iOS-„Zum Home-Bildschirm" (Meta `apple-mobile-web-app-title` wird zur Laufzeit datengetrieben gesetzt; `index.html` bleibt statischer Fallback) und den **App-Namen im iOS-Install-Popup** (vorher fix „Festivadget").
- **Karte: Anfangs-Zoom (`startZoom`) getrennt vom Heraus-Zoom-Limit.** Neues optionales Feld in `map.json`: `minZoom` ist jetzt nur noch das **weiteste Heraus-Zoomen** (maximum zoom-out), `startZoom` der **Anfangs-Zoom**. So kann die Karte z. B. bei `-1` starten, sich aber bis `-2` herauszoomen lassen. Ohne `startZoom` wird wie bisher das Bild eingepasst.

### Changed

- **Benachrichtigungs-Schalter: Text & „Mehr Infos".** Beschreibung jetzt „Konzertstarts & wichtige Infos auf den Sperrbildschirm." Auf iPhone/iPad folgt in derselben Zeile ein **„Mehr Infos"-Link**, der das Installations-Popup erneut öffnet.
- **iOS-Install-Popup: Titel & Anleitung überarbeitet.** Titel jetzt „Benachrichtigungen aktivieren"; klarere Beschreibung (Push am Sperrbildschirm, Kategorien selbst wählbar) und ergänzter **Schritt 4** „In der App bei Benachrichtigungen auf Aktivieren klicken".

### Fixed

- **Offline: Artist-Fotos & Geländeplan (venue.jpg) werden jetzt precacht** – vorher nur Runtime-Cache (erst nach Online-Aufruf verfügbar), daher offline oft leer. Jetzt im Precache (`img/artists/**`, `map/**/*.jpg`; Per-Datei-Limit auf 4 MiB für venue.jpg). Erst-Download dadurch ~3 MB größer; dafür volle Offline-Nutzung. (Eingebettete iframes – YouTube/Spotify/Maps – sind extern und bleiben netzabhängig.)

### Changed

- **Monorepo-Migration (Phase 0):** Festivadget liegt jetzt im Unterordner `Festivadget/` einer pnpm-Workspace-Wurzel (`C:\Festivadget`, ein Git-Repo). Geteilte Teile herausgelöst: **`@rid/tokens`** (Tailwind-Preset: Farben/Fonts/Layout-Tokens) und **`@rid/core`** (Domänen-Typen, Zeit-/Tagesgrenzen-Logik, Clash-Finder, ICS). Festivadget nutzt beide; bestehende Importe bleiben via dünner Re-Export-Shims (`@/types`, `@/lib/time`, `@/lib/ics`) unverändert. Tooling: pnpm (statt npm), `node-linker=hoisted`. **Keine Funktionsänderung** an der App. (`@rid/ui` bewusst aufgeschoben bis CrewCare startet.) Anleitung: `MONOREPO_MIGRATION.md`.
- **Größere Seiten-Überschriften:** Tailwind-Token `text-2xl` auf 2.25 rem / 2.75 rem angehoben (Standard war 1.5 rem / 2 rem). Betrifft alle Seitentitel (`h1.text-2xl`); das Wetter-Emoji in `WeatherStrip` (einzige Nicht-Überschrift mit `text-2xl`) wächst entsprechend mit.
- **Größere Überschriften im Markdown-Renderer** (Info/FAQ/Bio/News): H1 2 rem (32 px, lh 2.5 rem), H2 1.75 rem (28 px, lh 2.25 rem), H3 1.5 rem (24 px), H4 1.25 rem (20 px), H5 1.125 rem (18 px); H6 unverändert. Klarere Hierarchie für längere Info-Texte.

### Changed

- **News-Verwaltung vereinheitlicht:** Der komfortable Tab „News" (`admin-news.json`) ist jetzt die **einzige** Stelle für News – beim ersten Öffnen aus dem Build-Stand (`news.json`) vorbefüllt, danach **ersetzt** er ihn im Feed (statt zusätzlich gemischt). Der redundante generische „News (Build-Stand)"-Editor unter „Inhalte" entfällt. Der **Cron pusht jetzt auch aus `admin-news.json`** – im News-Tab angelegte News werden also automatisch gepusht (gemäß Kategorie-Filter). (Telegram-Live-News bleiben separat zusätzlich.)
- **Admin-UI: Aktionsleiste auch oben (sticky):** In den Tabs „Infos", „Inhalte" und „News" stehen „Speichern" (und ggf. „Override entfernen" / „Aus Joomla/WordPress importieren") jetzt **zusätzlich oben** und bleiben beim Scrollen langer Listen am oberen Rand angeheftet – kein weites Scrollen mehr bis zum Button.

### Added

- **„Mein Plan" als Push-Abo:** Im Benachrichtigungs-Popover (Header-Glocke) aktivierbar – erinnert **vor Konzertbeginn** an die favorisierten Acts. Die Favoriten-Slots werden (anonym, nur IDs) ans Abo synchronisiert (`push_subscriptions.plan`) und bei Favoriten-Änderung automatisch aktualisiert. Der Cron schickt **eine Erinnerung pro Act**, jeden Slot pro Gerät **nur einmal** (Dedup via `push_log`). „Mein Plan"-Abonnenten erhalten **nicht** zusätzlich den allgemeinen „Gleich live"-Digest (keine Doppel-Meldung).
- **Push-Benachrichtigungen im Header:** Sobald Push aktiviert ist, verschwindet der „Benachrichtigungen"-Aktivieren-Block auf der Startseite; stattdessen erscheint eine **Glocke im Header** (links neben der Suche) mit Popover zur **Kategorie-Wahl** (Safety immer an) und zum **Ausschalten**. Der „Benachrichtigungen"-Block steht auf der Startseite jetzt **über** der Programmübersicht.
- **CSV-Export des Abo-Verlaufs:** Im CMS-Tab „Push" exportiert „Als CSV exportieren" die anonyme `push_stats`-Zeitreihe (Zeitpunkt, Gesamt, je Kategorie) – mit BOM für Excel.
- **Anonyme Abo-Statistik:** Neue Tabelle `push_stats` schreibt (vom Cron, ~stündlich) eine Zeitreihe der Push-Abo-Zahlen fort – **nur Zähler**, keine personenbezogenen Daten: gesamt sowie je Kategorie (Infos / Line-Up / Allgemein; Sicherheit = alle). Im CMS-Tab „Push" werden aktuelle Zahlen **und** der Verlauf angezeigt.
- **News „Sofort pushen":** Häkchen je News-Eintrag im CMS – beim Speichern wird der (bereits veröffentlichte) Eintrag **sofort** kategoriebewusst gepusht, ohne auf den nächsten Cron-Lauf zu warten. Einmalig (über `push_log` gegen Doppelung mit dem Cron abgesichert).
- **Cron-Wrapper für mehrere Jobs:** `push/cron-send-1.php` … `cron-send-5.php` – dünne Wrapper, die `cron-send.php` einbinden (Inhalt bleibt an einer Stelle). Erlauben mehrere Cron-Einträge bei Hostern, die denselben Dateipfad nicht doppelt zulassen; gestaffelt (z. B. alle 10 Min) für geringere Push-Latenz. Mehrfachläufe sind dank `push_log` idempotent.
- **Auto-Push im Admin schaltbar:** Unter „Einstellungen → Push-Automatik" lassen sich der **Konzert-Digest** „Gleich live" (`autoPushUpcoming`) und das **automatische Pushen neuer News** (`autoPushNews`) je ein/aus schalten – live über `app-config.json`, vom Cron mit Vorrang vor `config.php` gelesen. Zusätzlich ist die **Digest-Vorlaufzeit** (`upcomingWindowMin`, Standard 60) einstellbar: bei häufigerem Cron (z. B. alle 10–15 Min für geringere News-Latenz) kleiner wählen, damit „Gleich live" nicht zu früh kommt.
- **Push-Kategorien wählbar – pro Nutzer & im Admin:** Jedes Gerät kann unter „Benachrichtigungen" selbst wählen, für welche Kategorien (**Infos / Line-Up / Allgemein**) es Push erhält; **Sicherheit kommt immer an**. Die Auswahl wird im Abo gespeichert (neue Spalte `categories` in `push_subscriptions`, abwärtskompatibel) und beim Versand respektiert. Im Admin (**Einstellungen → „Auto-Push: Kategorien"**) legt man fest, welche Kategorien überhaupt automatisch pushen (`pushNewsCategories`, live über `app-config.json`, vom Cron gelesen). Sender ist jetzt kategoriebewusst (`push_send_news`).
- **POI-Kategorien sind jetzt datengetrieben & im Admin pflegbar:** neue Domäne `poi-categories.json` (Tab „Inhalte" → „POI-Kategorien") mit `id`, `label`, `icon` (Emoji), `color`, `order`, `hidden`. Damit lassen sich **eigene Kategorien anlegen** (z. B. „Parken"), umbenennen und **komplett ein-/ausblenden** (`hidden` = Master-Schalter: weg von Karte UND Filter, für alle). Bisher waren die Kategorien im Code festverdrahtet (`POI_META`/`PoiType`-Union); diese dienen nur noch als Fallback. POI-Formular: `type` ist jetzt ein **Dropdown aus den vorhandenen Kategorien**.
- **Icon je POI wählbar:** optionales Icon-Feld pro POI (`icon`) – überschreibt das Kategorie-Icon auf Marker und Detail-Sheet; leer = Kategorie-Icon.
- **Eigene Bild-Icons für POIs/Kategorien:** Das `icon`-Feld (Kategorie **und** POI) akzeptiert neben Emojis auch einen **Bildpfad/URL** (z. B. via Tab „Bilder" hochgeladen, `/data/uploads/…`). Werte mit `/`, `http(s):`, `data:` oder Bild-Endung werden als `<img>` gerendert (Marker, Filter-Chip, Detail-Sheet), sonst als Emoji – beides mischbar.
- **Lucide-Icons für POIs/Kategorien per Name:** Das `icon`-Feld akzeptiert zusätzlich **Lucide-Icon-Namen** (z. B. `ambulance`, `utensils`, `parking`; kuratierte Liste in `docs/DATEN.md`). Einfarbig gerendert, Farbe automatisch kontrastreich zum Marker-Hintergrund. Font-Awesome-Klassen werden nicht direkt unterstützt (FA-Icon stattdessen als SVG hochladen).
- **DJ-Kennzeichnung für Acts:** neues optionales Feld `isDj` (Checkbox „DJ" im Admin, analog zu „Headliner"). Zeigt ein **„DJ"-Badge** (Sekundärfarbe `#e4572e`) auf der Artist-Karte – **ohne** Einfluss auf die Line-Up-Reihenfolge (anders als „Headliner"). Mit `isHeadliner` kombinierbar; dann erscheinen beide Badges.
- **Neue App-Icons aus dem ROCK-IM-DORF-Logo:** alle PWA-Icons neu erzeugt – weißes Logo auf dunklem Grund (`#121212`, passend zur `theme-color`). `apple-touch-icon.png` (180), `icon-192/512.png`, `icon-maskable-512.png` (mit Sicherheitsrand) sowie `icon.svg` (abgerundet) und `icon-maskable.svg` (full-bleed) – letzteres zusätzlich im Manifest als `maskable`. Favicon nutzt das Vektor-Logo (`favicon.svg`) und passt sich per `prefers-color-scheme` an helle/dunkle Browser-Tabs an.
- **Frage/Antwort-Accordion für beliebige Info-Seiten:** Im Admin („Infos") lässt sich pro Eintrag die Option **„Als Frage/Antwort-Accordion anzeigen"** (`faq`-Flag) setzen. Dann wird jede `## Überschrift` im Text zu einer aufklappbaren Frage; Text **vor** der ersten Frage erscheint als normaler Intro-Block darüber. Bisher war das Accordion fest nur an die Seite mit der ID `faq` gebunden – jetzt für jeden Menüpunkt (z. B. „Cashless") nutzbar.

### Fixed

- **Google-Maps-Embeds aus allen Domains:** Die iframe-Whitelist (Server-Import + Client-Render) erlaubt Google Maps jetzt in allen Varianten (`maps.`/`www.`-Subdomain, `.com/.at/.de/.ch`) statt nur `www.google.com`. Karten, die per `maps.google.com` oder `www.google.at` eingebettet sind (z. B. „Anreise"), werden dadurch nicht mehr verworfen. Spoofing (`google.com.evil.com`) bleibt blockiert.

### Removed

- Feld **`edition`** aus dem Festival-Schema entfernt (Typ, Validierung, Beispieldaten) – wurde im UI nicht mehr verwendet.

### Added

- **Admin-UI (CMS) – Phase 1:** passwortgeschützte PHP-Oberfläche (`push/cms/`, Login via `adminPasswordHash`) zum Steuern der App ohne Neu-Deploy. Schreibt server-eigene `data/app-config.json`, die die App live einliest (`useAppConfig`, 2-Min-Poll). Erste Funktion: Sichtbarkeit der **MEHR-Menüpunkte** schalten (`moreHidden[]`). Architektur + Fahrplan: `docs/ADMIN.md`.
- **Admin-UI (CMS) – Phase 2 (Infos):** Tab „Infos" zum Ein-/Ausblenden, Umbenennen, Hinzufügen/Löschen von Info-Seiten (inkl. Text/Reihenfolge/Icon) → `data/app-info.json`. Liegt die Datei vor, ersetzt sie client-seitig (`useInfo`) den Build-Stand; Editor wird aus `info.json` vorbefüllt.
- **Admin-UI (CMS) – Phase 3 (Einstellungen):** Tab „Einstellungen" für `lineupImageLimit` (Acts mit Bild im Line-Up), `background` (Hintergrundgrafik an/aus) und `themeDefault` (Standard-Theme, überschreibt die Gast-Wahl nicht). Werte in `app-config.json`; die App wendet sie live an.
- **Admin-UI (CMS) – Phase 4 (News & Push):** Tab „News" (Titel, Markdown, Kategorie, anpinnen, Veröffentlichen/Ablauf, optionaler Link) → `data/admin-news.json`, vom Newsfeed zusätzlich eingemischt (`useNewsFeed`). Tab „Push" sendet sofort an alle Abos (`push_broadcast`).
- **Admin-UI (CMS) – Phase 5 (Live-Override-Fundament):** `useDataset` lädt zu jeder Domäne zusätzlich `data/app-<domain>.json` (2-Min-Poll) und bevorzugt sie gegenüber dem Build-Stand. Basis für künftige Content-Editoren (alle Domänen) und den geplanten Server-Importer (Joomla/WordPress live).
- **Admin-UI (CMS) – Phase 6a (Inhalte-Editor):** Tab „Inhalte" mit validiertem JSON-Editor für **alle** Content-Domänen (festival, stages, artists, slots, pois, map, sponsors, tickets, weather, info, news). Vorbefüllung aus dem aktuellen Stand, Liste/Objekt-Prüfung, „Override entfernen" → zurück zum Build-Stand. Schreibt `data/app-<domain>.json`.
- **Admin-UI (CMS) – Phase 6b (Bild-Upload):** Tab „Bilder" lädt Bilder (webp/png/jpg/svg, max. 5 MB) nach `data/uploads/` hoch (serviert unter `/data/uploads/<name>`); den Pfad als Artist-`image`/Sponsor-`logo` einsetzen. Dateinamen werden bereinigt, Größe/Typ geprüft.
- **Admin-UI (CMS) – Phase 6c (Komfort-Formulare + Slots-Editor):** im „Inhalte"-Tab schema-getriebene Formulare für `stages`, `sponsors`, `pois`, `artists` und ein tabellarischer **Slots/Timetable-Editor** (Act/Bühne/Tag als Dropdowns, Zeiten als datetime-local). Umschaltbar Formular ↔ „Als JSON bearbeiten"; restliche Domänen weiter als JSON.
- **Import + Rendering: Bilder, iframes & Überschriften aus dem CMS:** Der Importer erhält jetzt sanitiziertes HTML (Überschriften, Bilder, iframes von erlaubten Hosts) statt nur reinen Text (`cms_clean_html` via DOMDocument; `data:`-Bilder/`script`/Fremd-iframes werden entfernt). Die App rendert eingebettetes HTML sicher (`rehype-raw` + `rehype-sanitize`) mit zusätzlicher **iframe-Host-Whitelist** (YouTube/Spotify/Google Maps); Bilder werden responsiv dargestellt. Artist-Bio nutzt nun denselben `Markdown`-Renderer.
- **Admin-UI (CMS) – Infos: Quelle je Eintrag:** im „Infos"-Tab pro Eintrag `manual`/`joomla`/`wordpress` + Locator (Joomla-Artikel-ID / WP-Slug) wählbar; „Aus Joomla/WordPress importieren" zieht Titel/Text nur für die markierten Einträge (Struktur + manuelle Einträge bleiben). Verbesserter HTML→Markdown-Import (Absätze bleiben erhalten).
- **Admin-UI (CMS) – Phase 7 (Server-Importer):** Tab „Quellen" zum Setzen der Datenquelle je Domäne (`manual`/`joomla`/`wordpress` + Locator) → `data/source-config.json`; „Jetzt importieren" zieht per curl aus Joomla/WordPress und schreibt `data/app-<domain>.json` (generisches Mapping; Verbindung/Token in `push/config.php` → `sources`). Unabhängig vom Build-Import (`content-sources.config.ts`).
- **Light Mode:** Umschalter „Dark / Light" unter „Mehr" (Standard bleibt **Dark**). Nur die Farb-Tokens werden überschrieben (via `<html data-theme>`), Akzentfarben bleiben; Auswahl wird persistiert und ohne Flackern beim Start gesetzt (Inline-Skript in `index.html`), `theme-color`-Meta passt sich an.
- **Kontakt**-Link unter „Mehr" (zwischen „Tickets" und „Impressum") → `rockimdorf.at/kontakt`.
- **Telegram-Live-News:** separate `data/live-news.json` (Server-Eigentum, vom lokalen Build/Deploy unangetastet), die der Newsfeed zusätzlich zu `news.json` einmischt. PHP-Webhook `push/telegram-hook.php` – unmoderiert, Allowlist (`allowedUserIds`/`allowedChatIds`, auch Gruppen), Tags `#safety/#info/#lineup/#general/#pin`, `#1h`/`#30m` (Auto-Ablauf), `@HH:mm` (geplante Veröffentlichung), Befehle `/list`, `/del <Nr>`, `/clear`, `/chatid`. Anleitung: `docs/TELEGRAM.md`.
- **News-Push** in `push/cron-send.php`: neu veröffentlichte News automatisch pushen (Kategorie/`pinned`-Filter), idempotent über `push_log`.
- **Push für Telegram-Live-News:** Tag `#push` (opt-in) bzw. automatisch für Kategorien aus `pushAutoCategories` (Standard `safety`) → freigegebene Live-News gehen zusätzlich als Web-Push raus (nur bei sofortiger Veröffentlichung; Web-Push-Setup vorausgesetzt).
- **iOS-Installations-Popup** (`IosInstallPopup`): einmaliger Hinweis beim ersten Start nur für iOS-Nutzer.
- **Deploy-Batch** (`deploy-data.bat` / `deploy-data.bat full`): baut + lädt per FTPS hoch; Zugangsdaten in `deploy.env.bat` (gitignored).
- **Artist-Page:** Spotify-Embed (`spotify`-Feld, flexibel: Link/Embed-Code/ID) **und** YouTube-Embed (`youtube`-Feld); Bild links neben Name/Genre/Spielzeit, darunter Spotify → YouTube → Bio; Spielzeit mit Start- und Endzeit.
- **Line-Up:** Filter nach **Tag** (statt Genre), Sortierung über `order`-Feld, Sichtbarkeit über `lineup`-Feld; `genres` optional.
- **Hintergrund wählbar:** Farbe **oder** Grafik (`--rid-bg-image` in `index.css`, Lesbarkeits-Schleier `--rid-bg-scrim`).
- **Impressum**-Link unter „Mehr"; **News-Links** (Markdown im Text + `link`-Button), interne Markdown-Links navigieren in-App.
- **„Mein Plan" Leerzustand** erklärt die Funktion, wenn noch keine Favoriten da sind.
- News-Feld **`hideAfterFirstOpenMin`**: blendet eine News X Minuten nach dem ersten App-Öffnen dieses Geräts aus (pro Gerät, z. B. Willkommen-News).
- **„Presented by"-Footer**: das Main-Sponsor-Logo (Tier `main`) erscheint am Ende jeder Seite (`PresentedByFooter`).
- Skripte: `npm run gen-news` (Lineup-News aus `slots.csv`), `npm run gen-icons`.
- `BACKLOG.md` für spätere Ideen (PHP-Admin-UI, E-Mail-Ingestion, CI-Deploy).

### Changed

- **Navigation umgebaut:** Bottom-Nav = Home · Line-Up · Timetable · **Mein Plan** · Mehr; **Karte** wanderte unter „Mehr".
- **Home:** Logo-Grafik im Header, Titel/Datum-Zeile, „PROGRAMMÜBERSICHT" (2 Spalten), kompakte Newsfeed-Vorschau, Wetter entfernt.
- **Timetable (Grid):** responsive Spalten (passt auf 360 px, ausgeblendete Bühnen füllen die Breite), Beschriftung Uhrzeit→Name (Name fett, mehrzeilig), Stern oben-bündig; Klick öffnet Artist-Seite, Stern-Bereich favorisiert.
- Design-Tokens als **RGB-Kanäle** (funktionierende Tailwind-Alpha-Varianten); Timetable-Stundenlinien grau.
- Diverse Umbenennungen/Texte (de/en) und 360-px-Feinschliff.
- `rid-chip`-Padding horizontal von `0.75rem` auf `0.65rem` reduziert; Willkommen-News `hideAfterFirstOpenMin` 10 → 60.
- **Line-Up:** nur die ersten `LINEUP_IMAGE_LIMIT` Acts (Standard **20**, in `src/config.ts` änderbar) werden mit Bild gezeigt; alle weiteren als kompakte Karte ohne Bild. Das Bild-Limit richtet sich nach der globalen Line-Up-Reihenfolge (konsistent auch bei Tagesfilter).
- **Home:** „PROGRAMMÜBERSICHT" wieder einspaltig (Bühnen untereinander).
- **Akzentfarbe (Gelb)** von `#f2c200` auf `#ffb300` geändert (Token `--rid-accent` + POI-Bühnenfarbe).
- **Sponsor-Footer:** Logo-Höhe von 40 px auf 80 px erhöht.
- **Timetable (Grid):** eigener deckender Hintergrund (`rid-card`, wie die HOME-Karten), damit das Hintergrundbild nicht durchscheint.
- **Artist-Seite:** Bio-Text erhält denselben deckenden `rid-card`-Hintergrund (statt durchscheinendem Seitenhintergrund).
- **Info-Detailseiten:** Fließtext (Anreise, Camping & Caravan, Cashless, BringMichHeim Bus, Platzordnung …) auf deckendem `rid-card`-Hintergrund (FAQ-Accordion unverändert).
- **App-Name:** Tab-Titel + Manifest `name` „ROCK IM DORF Festival"; Manifest `short_name` + iOS-Homescreen-Titel „ROCK IM DORF" (vorher „Festivadget").
- **Artist-Seite:** Beschriftung „Spielzeit"/„Show time" → „Stage Time" (de/en).
- **Artist-Bios:** echte Pressetexte (von rockimdorf.at) für alle 21 Nicht-DJ-Acts als `bio` eingepflegt (Platzhalter ersetzt); DJ-Einträge unverändert. Bio-Absätze (Leerzeile = `\n\n` im Text) erhalten sichtbaren Abstand (das Typography-Plugin ist nicht aktiv, daher Absatz-Abstand direkt in `Artist.tsx` gesetzt).
- **Artist-Links:** korrekte **Spotify**- und **YouTube**-Links (aus den Embeds auf rockimdorf.at) für alle 21 Nicht-DJ-Acts eingepflegt; bisherige Platzhalter-Spotify-Links entfernt. Ausnahmen: Nenda ohne YouTube, MV Weinzierl-Altpernstein ohne Spotify (auf der Seite jeweils nicht vorhanden).
- **Spotify-Embed-Style:** alle Embeds nutzen jetzt `theme=0` (einheitlicher Standard-Style wie auf der Webseite) statt der artist-individuellen Einfärbung.
- **Infos:** „Camping" → „Camping & Caravan"; neue Einträge „BringMichHeim Bus" und „Platzordnung".
- **Infos – Quelle je Untermenüpunkt:** `info.overrides` (ID → `manual`/`joomla`/`wordpress`) wird jetzt im Import angewendet; `default` liefert Struktur + Texte, eine Override-Quelle liefert nur Titel/Text (Struktur bleibt). Doku: `docs/DATEN.md`.
- **Infos – Sichtbarkeit:** neues Feld `hidden` (`InfoPage`) blendet einen Eintrag aus Menü **und** Suche aus (Direkt-Link bleibt). Neuer (versteckter) Eintrag „Parken" zwischen „Anreise" und „Camping & Caravan".
- Sprach-Umschalter unter „Mehr" heißt jetzt **„English / Deutsch"** (statt „Sprache: DE"); **„Suche"** unter „Mehr" entfernt (Suche ist im Header).
- **„Presented by"-Footer** ohne oberen Rand/Abstand (`mt-8`, `border-t` entfernt) und auf der Sponsoren-Seite sowie auf Artist-Seiten ausgeblendet.
- **Sponsor-Logos** in `webp`/`jpg` werden jetzt – wie `png`/`svg` – mit-precached (gezielter Glob `img/sponsors/**/*.{webp,jpg,jpeg}`), sodass die Sponsoren-Seite auch beim ersten Offline-Start mit jedem Logo-Format vollständig ist. Artist-Fotos und `background.webp` bleiben bewusst nur im Runtime-Cache (schlanker Precache).

### Fixed

- News-Karte gegen unbekannte Kategorien abgesichert (Fallback-Icon, kein Crash).
- Doppelte News-IDs eindeutig gemacht.
- **Scroll-Position:** neue Seiten öffnen wieder am Anfang (Scroll-to-Top bei jedem Routenwechsel) – z. B. Artist-Seite aus dem weiter unten gescrollten Line-Up.
- Spotify-Embed-Höhe auf 352 px (volle Variante, kein Leerraum mehr zwischen Spotify und YouTube).
- Safety-Hinweise im Newsfeed: deckender Hintergrund (wie andere News-Karten) statt halbtransparentem Rot; roter Rahmen/Icon bleiben.
- Mein Plan: Clash-Warnung mit deckendem Hintergrund (statt halbtransparent); Text präzisiert auf „Mindestens ein Programmpunkt überschneidet sich …".

## [1.1.0] - 2026-06-24

### Added

- **Web-Push** (Phase 5, §13): Benachrichtigungen auf den Sperrbildschirm, auch bei geschlossener App.
  - Eigener Service Worker (`src/sw.ts`, vite-plugin-pwa `injectManifest`) mit `push`/`notificationclick`-Handler; bisheriges Runtime-Caching portiert (kein Offline-Regress).
  - Client: `src/lib/push.ts` (Subscribe/Unsubscribe via Push-API), Schalter „Benachrichtigungen" unter **Mehr** (`NotificationsToggle`), gated über `VITE_VAPID_PUBLIC_KEY`.
  - Backend für einfachen PHP-Webspace unter `push/` (kein VPS): `subscribe.php`, `admin.php` (sofort senden, passwortgeschützt), `cron-send.php` (Stunden-Digest „läuft gleich"), `vapid-keys.php`, MySQL-Schema, `.htaccess`; via `minishlink/web-push`.
  - Anleitung: [`docs/PUSH.md`](docs/PUSH.md).
- Header zeigt eine **Logo-Grafik** (`/img/logo.svg`, max. 36 px hoch / 300 px breit) statt Text.
- **Zurück-Links** (`BackLink`) auf Mein Plan, Newsfeed, Infos, Sponsoren, Tickets und Suche (→ „Mehr").
- Timetable: zusätzliche Stage **„Spiel & Spaß"**; **Bühnen-Spalten ein-/ausblendbar** (persistiert).
- Home: kompakte **Newsfeed-Vorschau** (letzte 3 Einträge).

### Changed

- Menüpunkt/Seitentitel **„News & Infos" → „Newsfeed"**.
- Line-Up-Filter: **nach Tag** statt nach Genre (Mitternachtsüberlauf via `slot.dayId`).
- Home: Titel „ROCK IM DORF Festival 2026", Datumszeile „31. Juli – 2. August 2026", **Wetter-Modul entfernt**, Abschnitt „Aktuell / Als Nächstes" mit größeren Texten.
- Timetable-Grid: Klick auf einen Slot öffnet jetzt die **Artist-Seite**; ein separater **Stern-Bereich** am rechten Slot-Rand fügt zu „Mein Plan" hinzu (statt: Klick = favorisieren).
- Install-Hinweis nur noch unter **„Mehr"** (nicht mehr global).
- Design-Tokens als RGB-Kanäle hinterlegt → Tailwind-Alpha-Varianten funktionieren; Timetable-Stundenlinien jetzt **grau** statt weiß.
- Layout auf **360 px** Breite geprüft/optimiert.

### Fixed

- Timetable-Stundenlinien erschienen durch ungültige Alpha-auf-Hex-Variable weiß (jetzt grau).

## [1.0.0] - 2026-06-24

### Added

- **Code-Splitting**: alle Routen außer Home via `React.lazy` + `Suspense`; Leaflet in eigenem Chunk (lädt nur auf der Karte), Markdown/Luxon als automatische Shared-Chunks. Kein 500-kB-Warnhinweis mehr.
- **Install-Hints** (`InstallHint`, §13): `beforeinstallprompt`-Button (Android/Chrome) + iOS-Teilen-Hinweis, einmal dismissbar.
- **Finale PWA-Icons**: PNG-Raster 192/512 + maskable-512 + `apple-touch-icon` (180), generiert aus SVG via `npm run gen-icons` (sharp).
- **Deployment-Härtung**: `public/.htaccess` (HTTPS-Zwang, SPA-Fallback, Cache-Header §15), `robots.txt`, Apple-PWA-Meta-Tags (`apple-mobile-web-app-*`).

### Changed

- Manifest auf PNG-Icons (inkl. maskable) umgestellt; `vendor`-Chunk kuratiert (React/Router/Query/i18n/lucide/idb).

### Verifiziert

- Production-Build: Service Worker aktiv, 36 Precache-Einträge (App-Shell) + Laufzeit-Cache der Daten-JSONs → offline lauffähig; alle Icons erreichbar; Lazy-Chunks laden fehlerfrei on demand.

## [0.4.0] - 2026-06-24

### Added

- **Offline-Karte** (§12.4): Leaflet `L.CRS.Simple` + `ImageOverlay`, POI-Marker je Typ (Farbe/Icon), `PoiFilterBar` und `PoiSheet` (Detail mit Stage-Verknüpfung). Schematischer Platzhalter-Geländeplan (`gelaendeplan.svg`).
- **News-Feed** (§12.5): redaktionelle Items mit `publishAt`/`expiresAt`-Filter + virtuelle **Auto-Konzertstart**-Items aus `slots`, gemergt und sortiert (pinned/safety oben); prominenter `SafetyBanner`.
- **Globale Suche** (§12.7): clientseitiger Index über Artists/Slots/Info/POIs mit Token-/Substring-Match und Scoring.

### Changed

- Letzte Route-Stubs (Karte/News/Suche) durch echte Implementierungen ersetzt.

## [0.3.0] - 2026-06-24

### Added

- **Timetable** (§12.2): Grid-Ansicht (CSS Grid, Spalten = Stages nach `order`/Farbe, Zeitachse mit Stundenraster) und Listen-Ansicht, umschaltbar.
- **DayTabs** mit Mitternachtsüberlauf über `dayStart`/`dayEnd` (Luxon).
- **NowLine** (aktuelle Zeit, tickt alle 30 s) und **Clash-Indikator** (`useClashes`) über favorisierte Slots.
- **Mein Plan** (§12.3): Favoriten chronologisch nach Tag gruppiert, Clash-Warnbanner + Slot-Marker.
- **`.ics`-Reminder** (`IcsButton`): Export einzelner Termine und „Alle als .ics" mit 15-Min-Vorlauf.
- Timetable-Quelle umschaltbar gemäß §6.5 (aktiv: `csv`).

### Changed

- Slot kann direkt im Grid per Klick favorisiert/entfavorisiert werden (gelbe Füllung).

## [0.2.0] - 2026-06-24

### Added

- **Info-Seiten** (§12.9): Übersicht (`/info`) + Markdown-Detail (`/info/:id`), FAQ als Accordion, Icons je Seite.
- **Sponsoren** (§12.8): nach Tier gruppiertes Grid, Logo verlinkt auf `url`, Fallback auf Namen.
- **Tickets** (§12.11): `iframe` mit restriktivem `sandbox` bzw. `link`-Fallback je Provider.
- Wiederverwendbare `Markdown`-Komponente (Bio/Info/News).

### Changed

- Route-Stubs für Info/Sponsoren/Tickets durch echte Implementierungen ersetzt.
- React-Router v7 Future-Flags aktiviert (`v7_startTransition`, `v7_relativeSplatPath`).

### Fixed

- Platzhalter-Spotify-ID aus Beispieldaten entfernt (zeigte Spotify-404 im Embed).

## [0.1.0] - 2026-06-23

### Added

- Projektgerüst (Vite, React 18, TypeScript, TailwindCSS, Routing, PWA-Setup).
- Design-Tokens (dunkel/weiß/gelb) als CSS-Variablen + Tailwind-Theme (§2).
- App-Shell mit TopBar, unterer Tab-Bar (Home · Line-Up · Timetable · Karte · Mehr) und Offline-Badge (§9, §10).
- TanStack-Query-Setup und Versions-Polling (`version.json`, 2-Minuten-Takt) mit gezielter Invalidierung (§5.2).
- Daten-Hooks für alle Inhaltsdomänen (§7) inkl. Offline-Fallback der Version aus IndexedDB.
- Zustand-Stores: Favoriten (IndexedDB) und UI-State (localStorage) (§11).
- i18n (de Standard, en optional) via react-i18next (§14).
- Funktionale Seiten: Home (Now/Up-Next, Wetter-Strip), Line-Up (Genre-Filter), Artist-Page (Bio, Spotify-Embed, Spielzeiten, Favorisieren); Platzhalter für die übrigen Bereiche.
- Datenpipeline: `content-sources.config.ts`, Adapter (manual/joomla/wordpress/csv), `import-from-source.ts`, `build-data.ts` (Schema-Validierung + `version.json`-Hashes) (§6, §15).
- Beispiel-Inhalte (manuell) inkl. `slots.csv` und generierte `public/data/*.json`.
- `.ics`-Erzeugung mit 15-Minuten-Vorlauf (VALARM) (§11).
