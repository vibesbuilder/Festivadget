# Admin-UI (CMS)

**🇬🇧 [English](ADMIN.md) · 🇫🇷 [Français](ADMIN.fr.md) · 🇪🇸 [Español](ADMIN.es.md)**

Passwortgeschützte Weboberfläche auf dem Webspace zum Steuern der App **ohne
Neu-Deploy**. Liegt unter `push/cms/` und nutzt die vorhandene `push/`-Auth.

## Architektur

```
push/cms/index.php  ──(schreibt)──►  data/app-config.json   ──(App liest live, 2-Min-Poll)──►  React
   │                                 data/app-info.json   (Phase 2)
   └─ Login: adminPasswordHash       data/live-news.json  (Phase 4, schon vorhanden)
      aus push/config.php
```

- **Auth:** Session + `password_verify` gegen `adminPasswordHash` aus
  `push/config.php` (gleiches Passwort wie `push/admin.php`). CSRF-Token bei
  jedem Speichern.
- **Persistenz:** server-eigene JSON-Dateien im `data/`-Ordner (= `dataDir` aus
  `push/config.php`). Die App liest sie wie die Telegram-Live-News live ein.
  Diese Dateien gehören dem Server und werden **nie** lokal gebaut/committed
  (in `.gitignore`).
- **Client:** `src/data/useAppConfig.ts` lädt `data/app-config.json`
  (2-Min-Poll, Fallback = Defaults, wenn die Datei fehlt).

## Aufruf

`https://demo.festivadget.com/push/cms/` → mit dem Admin-Passwort anmelden.
Nach dem Login öffnet sich der Tab **„Einstellungen"** (Start-Tab).

## Sprache der Oberfläche

Das CMS ist in **Deutsch, Englisch, Französisch und Spanisch** verfügbar. Die
Sprache wird im Tab **„Einstellungen"** → „CMS-Sprache" umgestellt und gilt
serverseitig für alle Admins (gespeichert in `push/cms-settings.json`, per
`.htaccess` gesperrt, nie im Repo). Deutsch ist die Quellsprache; die
Übersetzungstabelle liegt in `push/cms/i18n.php` (Funktion `cms_t()`), fehlende
Schlüssel fallen auf Deutsch zurück. Die **App-Sprache** wählt jeder Gast
unabhängig davon selbst in der App (eine von 32 Sprachen, Liste in der
README). Die Vorgabe für Gäste, die noch nicht gewählt haben, steht unter
Einstellungen → „Standard-Sprache der App" (`languageDefault`); Inhalte lassen
sich je Sprache übersetzen (siehe „Mehrsprachige Inhalte in den Editoren").

## Hilfe-Tab

Der Tab **„Hilfe"** verlinkt alle Handbücher (ADMIN, DATEN, PUSH, TELEGRAM,
IMPLEMENTATION) als Markdown-Dateien in allen vier Sprachen. Die Dateien werden
beim App-Build nach `dist/docs/` kopiert und mit `deploy-data.bat full`
hochgeladen (erreichbar unter `/docs/<Name>.md`); fehlende Dateien blendet der
Tab aus. Eigenes Hintergrundbild: unter **Einstellungen** → „Hintergrundbild"
aus den Uploads wählbar (`backgroundImage` in `app-config.json`).

## Branding-Tab (Kunden-Branding ohne Build)

Der Tab **„Branding"** passt das Erscheinungsbild der App zur Laufzeit an –
ohne neuen Build. Gespeichert wird alles in `app-config.json` → `branding`;
entfernte Werte fallen automatisch auf den Build-Stand zurück.

- **Titel & Kurzname**: Browser-Tab-Titel und Home-Bildschirm-Label
  (Kurzname max. 12 Zeichen). Beide fließen auch ins PWA-Manifest.
- **Schrift-Set**: 4 Sets aus reinen System-/Websafe-Stacks (Standard,
  System, Serifen, Plakat) – keine Font-Dateien nötig, bleibt offline-fähig.
- **Farben**: Akzentfarben + komplette Paletten getrennt für Dunkel- und
  Hell-Theme (Hex-Werte, vorausgefüllt mit den Build-Standards). Checkbox
  „Farben zurücksetzen" entfernt alle eigenen Farben wieder.
- **Logo**: ersetzt das Logo in der Kopfzeile (`/data/uploads/branding-logo.*`).
- **PWA-Icons**: ein quadratisches PNG (mind. 192 px, empfohlen 512 px)
  hochladen – der Server erzeugt daraus per GD 192/512 px + maskable-Icon
  (dunkle Hintergrundfarbe, 80 % Safe-Zone). Voraussetzung: PHP-Erweiterung
  **GD** am Server.
- **Intro-Video (Home)**: wird in voller Breite oberhalb des Newsfeeds
  angezeigt. Quelle „Link/Datei" (direkte Videodatei per FTP/https;
  YouTube/Vimeo automatisch als Player) oder „Microsoft-Cloud"
  (OneDrive/SharePoint-„Einbetten"-URL); per Checkbox aktivierbar.
- **Manifest**: sobald Titel, Kurzname oder Icons gesetzt sind, tauscht die
  App den Manifest-Link zur Laufzeit auf `/push/manifest.php` (dynamisch:
  Name, Farben, Icons aus dem CMS). Ohne PHP-Backend gilt weiter das
  statische `manifest.webmanifest` aus dem Build. Neue Icons/Namen greifen
  bei der **nächsten Installation** der PWA (bestehende Installationen
  aktualisiert das Betriebssystem verzögert).

## Deployment

`push/cms/` per FTP in den `push/`-Ordner hochladen (wie der Rest von `push/`).
Voraussetzung: `push/config.php` mit gesetztem `adminPasswordHash`
(`php -r "echo password_hash('DEIN_PASSWORT', PASSWORD_DEFAULT);"`) und ein
**beschreibbarer** `data/`-Ordner (dort liegt schon `live-news.json`).

## Generischer Live-Override (Fundament)

`useDataset` (Query-Schicht) lädt zu **jeder** Domäne zusätzlich `data/app-<domain>.json`
(2-Min-Poll). Liegt diese Datei vor, **ersetzt** sie den Build-Stand `data/<domain>.json`.
Darauf bauen sowohl die Admin-Editoren als auch der Server-Importer: beide schreiben
`app-<domain>.json`. Fehlt sie, gilt unverändert der Build-Stand. (News: der Tab „News"
schreibt `admin-news.json`; liegt die Datei vor, **ersetzt** sie `news.json` im Feed –
Telegram-`live-news.json` wird weiterhin **zusätzlich** gemischt.)

## Ausbaustufen

1. **Fundament + MEHR** ✅ — Login; Sichtbarkeit der MEHR-Menüpunkte
   (`moreHidden[]` in `app-config.json`).
2. **Infos** ✅ — Punkte ein/aus, umbenennen, hinzufügen/löschen (inkl. Text,
   Reihenfolge, Icon) → `data/app-info.json`. Liegt die Datei vor, **ersetzt**
   sie client-seitig den Build-Stand (`info.json`); der Editor wird beim ersten
   Mal aus `info.json` vorbefüllt (Seed). Versteckte Einträge (`hidden`) sind
   aus Menü **und** Suche raus, per Direkt-Link aber erreichbar.
   **Frage/Antwort-Accordion:** Häkchen „Als Frage/Antwort-Accordion anzeigen"
   (`faq: true`) je Eintrag. Dann wird jede `## Überschrift` im Text zu einer
   aufklappbaren Frage, der Text darunter zur Antwort; Text **vor** der ersten
   `## Frage` erscheint als normaler Intro-Block darüber. Ohne `## …` im Text
   bleibt es normales Markdown. Nutzbar für jeden Eintrag (z. B. „Cashless").
   **Quelle je Eintrag:** Feld `source` (`manual`/`joomla`/`wordpress`) +
   `sourceLocator` (Joomla-Artikel-ID bzw. WP-Slug/ID). „Aus Joomla/WordPress
   importieren" zieht **nur** für die so markierten Einträge Titel/Text aus dem
   Artikel; Struktur und manuelle Einträge bleiben. (Erst speichern, dann
   importieren.)
3. **Globale Einstellungen** ✅ — in `app-config.json`: `lineupImageLimit`
   (Acts mit Bild), `background`/`backgroundImage` (Hintergrundgrafik an/aus,
   eigenes Bild aus den Uploads), `homeHeader` (Festivalname + Datum auf Home),
   `themeDefault` (`dark`/`light`) und `languageDefault` (eine der 32
   App-Sprachen) – beide greifen nur, solange der Gast nicht selbst wählt –,
   `contactUrl`/`impressumUrl` (Ziele der MEHR-Punkte „Kontakt" und
   „Impressum"; ohne URL bleibt der Punkt ausgeblendet, weil jede Instanz ihr
   eigenes Impressum hat) sowie die Push-Automatik (`autoPushNews`,
   `autoPushUpcoming`, `upcomingWindowMin`, `pushNewsCategories` – siehe
   `docs/PUSH.de.md`).
4. **News & Push** ✅ — News-Editor (Titel, Markdown-Text, Kategorie, anpinnen,
   Veröffentlichen/Ablauf, optionaler Link) → `data/admin-news.json`. **Einzige**
   News-Verwaltung: beim ersten Öffnen aus `news.json` vorbefüllt, danach **ersetzt**
   sie den Build-Stand im Feed (`useNewsFeed`); Telegram-`live-news.json` wird zusätzlich
   gemischt. Der Cron pusht neue Einträge automatisch (Kategorie-Filter, siehe `docs/PUSH.de.md`).
   **Auto-Push-Kategorien** wählt man unter „Einstellungen" (`pushNewsCategories`).
   Push-Tab sendet sofort an alle Abos (`push_broadcast` aus `sender.php`).
   Das Formular für eine neue News steht **ganz oben** im Tab. Je News wählt man
   die **Push-Empfänger**: alle (Vorgabe) oder eine **zufällige Auswahl** mit
   Anzahl, optional **nur als Push** (nicht im Newsfeed) – für Gewinnspiele,
   siehe `docs/PUSH.de.md`.
5. **Live-Override für alle Domänen** ✅ — `useDataset` bevorzugt
   `data/app-<domain>.json` (siehe oben). Fundament für 6/7.
6. **Content-Editoren je Domäne**
   - 6a ✅ Generischer **„Inhalte"-Tab**: jede Domäne (festival, stages, artists,
     slots, pois, map, sponsors, tickets, weather, info, news) als validierter
     JSON-Editor (Vorbefüllung aus aktuellem Stand, Liste/Objekt-Prüfung,
     „Override entfernen" → Build-Stand) → `data/app-<domain>.json`.
   - 6a-POI ✅ **POI-Kategorien** als eigene Domäne („Inhalte" → „POI-Kategorien",
     `app-poi-categories.json`): `id`/`label`/`icon`(Emoji)/`color`/`order`/`hidden`.
     Eigene Kategorien anlegen, umbenennen, **ein-/ausblenden** (`hidden` = Master-Schalter,
     komplett weg von Karte + Filter). Im POI-Formular ist `type` ein Dropdown aus diesen
     Kategorien; je POI optional ein **eigenes Icon** (`icon`, überschreibt das Kategorie-Icon).
     **Icons** dürfen sein: **Emoji**, **Bildpfad** (eigene Grafik via Tab „Bilder" hochladen →
     `/data/uploads/…`) oder ein **Lucide-Icon-Name** (z. B. `ambulance`, `utensils`, `parking`;
     volle Liste in `docs/DATEN.de.md`) – gilt für Kategorien und einzelne POIs. Font-Awesome-Klassen
     (`<i class="fa-…">`) gehen **nicht** direkt; FA-Icon stattdessen als SVG hochladen.
   - 6b ✅ **Bild-Upload** (Tab „Bilder") → `data/uploads/`, serviert unter
     `/data/uploads/<name>`; Pfad in „Inhalte" als `image`/`logo` einsetzen.
   - 6c ✅ **Komfort-Formulare** (statt JSON) für `stages`, `sponsors`, `pois`,
     `artists` (schema-getrieben) + **tabellarischer Slots-Editor** (Act/Bühne/
     Tag als Dropdowns, Zeiten als datetime-local). Im „Inhalte"-Tab umschaltbar
     zwischen Formular und „Als JSON bearbeiten"; übrige Domänen weiter als JSON.
> **Joomla-API-URL:** Der Importer nutzt die SEF-Form `…/api/v1/content/articles`
> (ohne `index.php`). Auf manchen Servern (z. B. World4You) wird der Pfad nach
> `index.php/` verschluckt (PATH_INFO) → die `index.php`-Form liefert dann für
> jeden Aufruf 404. Voraussetzung: aktive SEF-`.htaccess` mit der Authorization-
> Durchreich-Zeile (`RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`).

7. **Server-Importer** ✅ — Tab „Quellen": je Domäne `manual`/`joomla`/`wordpress`
   + Locator (Joomla: Kategorie-ID, WP: Kategorie-Slug) → `data/source-config.json`.
   „Jetzt importieren" (`importer.php`) zieht per curl und schreibt `app-<domain>.json`.
   Verbindung/Token in `push/config.php` → `sources`. **Best-effort generisches
   Mapping** (id/slug/title/name/body) – passt für Text-Inhalte; strukturierte
   Domänen (Artists/Slots/News) ggf. im „Inhalte"-Tab nachbearbeiten. Steuert nur
   den Server-Import, **unabhängig** von `content-sources.config.ts` (Build-Import).
   Der `body` behält **sanitiziertes HTML**: Überschriften, Bilder und iframes von
   erlaubten Hosts (YouTube/Spotify/Google Maps); `data:`-Bilder, `script` und
   fremde iframes werden entfernt (`cms_clean_html`). Die App rendert das sicher
   (`rehype-raw`+`rehype-sanitize`, zusätzliche iframe-Host-Whitelist im Client).

## Mehrsprachige Inhalte in den Editoren

Gäste wählen eine von 32 App-Sprachen; Inhalte können mitziehen. In den
Editoren für **Infos**, **News** (Titel, Text, Link-Text), **POIs** (Name,
Beschreibung), **POI-Kategorien** (Label) und **Artists** (Bio) hat jedes
Textfeld einen aufklappbaren Block **„Übersetzungen"** (standardmäßig
en/fr/es). Ein Feld mit nur dem deutschen Text bleibt ein einfacher String –
voll kompatibel mit einsprachigen Daten. Weitere Sprachen, die bereits Inhalt
tragen (z. B. aus dem JSON-Editor), erscheinen ebenfalls im Block und bleiben
beim Speichern erhalten. Festivaltage und Ticket-Hinweise werden über den
JSON-Editor im Tab „Inhalte" übersetzt. Format und Fallback-Reihenfolge:
`docs/DATEN.de.md`, Abschnitt 2.4.

## Wetter-Tab

Wetter-Anbieter und Standort für das Home-Widget und die Wetterseite.
Gespeichert in `push/weather-settings.json` (per `.htaccess` gesperrt, kann
API-Keys enthalten; überschreibt `weather` in `push/config.php`).

- **Anbieter**: GeoSphere Austria (Österreich, kein Key), MET Norway (weltweit,
  kein Key), OpenWeather oder WeatherAPI.com (weltweit, kostenloser API-Key
  nötig – wird im selben Formular eingetragen). Die Attribution in der App
  folgt dem Anbieter.
- **Standort**: Breite/Länge plus Anzeigename; bei GeoSphere optional eine
  TAWES-Station-ID für den Messwert „aktuell".
- **„Speichern & Verbindung testen"** speichert, leert den Cache und holt
  sofort eine Vorhersage – Fehler landen im Tab „Protokoll".
- Der Server cacht die Vorhersage 15 Minuten (`push/weather-cache.json`, kein
  Cron nötig); **„Wetter-Cache leeren"** erzwingt einen frischen Abruf.

## Statistik-Tab

Anonyme Nutzungszahlen der App, gesammelt von `push/track.php` in der Tabelle
`app_stats_events` (gleiche Datenbank wie Push; für lokale Tests auch
SQLite). Je Seitenaufruf werden nur Zeit, Seitenname, Sprache, Theme und zwei
**zufällige** Kennungen (Gerät, Sitzung) gespeichert – keine IPs, keine
User-Agents, keine Cookies. Der Zähler läuft nur im Produktions-Build.

Angezeigt werden: Seitenaufrufe, eindeutige Geräte und Sitzungen (gesamt /
letzte 7 Tage / heute), die meistgenutzten Bereiche, ein Tagesverlauf der
letzten 14 Tage, die Stunden-Verteilung (an den Festivaltagen, sonst die
letzten 7 Tage), PWA-Installationen und Geräte, die die App als installierte
PWA starten, sowie die Sprach- und Theme-Verteilung. Der
**Push-Abo-Verlauf** (Zahlen je Kategorie, vom Cron geschrieben) steht
ebenfalls hier und lässt sich als CSV exportieren. **„Statistik
zurücksetzen"** löscht alle Seitenaufruf-Daten; der Abo-Verlauf bleibt.

## Protokoll-Tab

Server-Protokoll (Tabelle `app_log`, 90 Tage aufbewahrt): Push-Versand,
Abo-Änderungen, Admin-Logins, Wetter-/Telegram-Fehler und von der App
gemeldete Client-Fehler (`push/track.php`, höchstens 5 je Sitzung). Einträge
haben eine Stufe (`info`/`warn`/`error`) und eine Quelle (`push`, `auth`,
`weather`, `client`, …); der Tab zeigt die neuesten 200, filtert nach Stufe
und Quelle und lässt sich komplett leeren. IPs oder personenbezogene Daten
werden nicht protokolliert.

## Joomla API Bearer-Token besorgen (für den Server-Importer)

Der Token wird **in Joomla erzeugt** (pro Benutzer), nicht irgendwo „gefunden":

1. **Plugins aktivieren** (System → Plugins): *Web Services - Content* (schaltet
   `/v1/content/articles` frei) und *User - Joomla API Token* (erzeugt + prüft
   den Bearer-Token). *Basisauthentifizierung* wird **nicht** benötigt.
2. **API-Login-Recht setzen** (System → Globale Konfiguration → Berechtigungen):
   für die Gruppe des API-Benutzers **„Web-Services-Anmeldung" (`core.login.api`)
   = Erlaubt**. Hat standardmäßig **nur** der Super User → fehlt sie, kommt **403
   „Forbidden"**. Empfehlung: eigene, minimale Gruppe „API" (übergeordnet Public),
   nur mit diesem Recht.
3. **Token erzeugen** (Benutzer → Verwalten → den API-Benutzer bearbeiten):
   Reiter **„Joomla API Token"** → anzeigen/neu generieren → kopieren.
4. In `push/config.php` → `sources.joomla.token` eintragen (NUR der Token, einfache
   Quotes, OHNE `Authorization: Bearer`; geheim, nie committen).
5. **Locator:** je Info-Eintrag die **Artikel-ID** (Inhalt → Beiträge, Spalte „ID");
   für den per-Domäne-Import die **Kategorie-ID** (Inhalt → Kategorien).
6. Test (URL **ohne** `index.php`, sonst evtl. 404):
   `curl -g -H "Authorization: Bearer <TOKEN>" -H "Accept: application/vnd.api+json" "https://rockimdorf.at/api/v1/content/articles"` → JSON mit Artikeln = ok.

**Fehlerbilder:** 404 überall = `index.php`-URL (PATH_INFO) oder Plugin/`.htaccess`
(siehe oben). 403 = Gruppe ohne `core.login.api`. 401 = Token ungültig/fehlt.

## app-config.json – Felder

| Feld               | Typ                 | Bedeutung                                           |
|--------------------|---------------------|-----------------------------------------------------|
| `moreHidden`       | `string[]`          | Ausgeblendete MEHR-Punkte (Schlüssel, siehe unten). |
| `lineupImageLimit` | `number?`           | Acts mit Bild im Line-Up (sonst 20).                |
| `background`       | `boolean?`          | Hintergrundgrafik an/aus (Default: an).             |
| `backgroundImage`  | `string?`           | Eigenes Hintergrundbild (`/data/uploads/…`, leer = Build-Grafik). |
| `themeDefault`     | `"dark"\|"light"?`  | Standard-Theme, solange der Gast nicht selbst wählt.|
| `homeVideo`        | `object?`           | Intro-Video auf Home (`{url, source, enabled}`), gepflegt im CMS-Tab „Branding". |
| `branding`         | `object?`           | Kunden-Branding (Farben, Schrift, Logo, Titel, Icons) – gepflegt über den CMS-Tab „Branding". |
| `homeHeader`       | `boolean?`          | Festivalname + Datum auf Home (Default: an).        |
| `languageDefault`  | `string?`           | Standard-Sprache der App, solange der Gast nicht selbst wählt (einer der 32 Codes; sonst Build-Vorgabe, sonst `en`). |
| `contactUrl`       | `string?`           | Ziel des MEHR-Punkts „Kontakt"; leer = Punkt ausgeblendet. |
| `impressumUrl`     | `string?`           | Ziel des MEHR-Punkts „Impressum"; leer = Punkt ausgeblendet. |
| `autoPushNews`     | `boolean?`          | Cron pusht neue News automatisch (siehe `docs/PUSH.de.md`). |
| `autoPushUpcoming` | `boolean?`          | „Gleich live"-Digest + „Mein Plan"-Erinnerungen per Cron. |
| `upcomingWindowMin`| `number?`           | Vorlaufzeit des Digests in Minuten (Default 60).    |
| `pushNewsCategories` | `string[]?`       | Kategorien, die der Cron automatisch pusht (`info`/`lineup`/`general`; Sicherheit immer). |

MEHR-Schlüssel: `news`, `map`, `info`, `sponsors`, `tickets`, `contact`,
`impressum`, `theme`, `language` (müssen mit `src/routes/More.tsx`
übereinstimmen).
