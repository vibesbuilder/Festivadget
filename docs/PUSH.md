# Web-Push einrichten (Phase 5)

Web-Push erlaubt Benachrichtigungen auf den Sperrbildschirm, **auch wenn die App
geschlossen ist** – für Safety-Durchsagen, kurzfristige Änderungen und „läuft gleich"-Hinweise.

Das Backend ist bewusst minimal: ein paar **PHP-Dateien** im Ordner [`push/`](../push/) auf
demselben **Webspace** (kein VPS nötig). Voraussetzung: **PHP 8.1+** mit `openssl`, `mbstring`
und `gmp` **oder** `bcmath`, dazu **MySQL** und **Cron**.

## Überblick

| Datei | Zweck |
|---|---|
| `push/subscribe.php` | nimmt Abos vom Browser entgegen, speichert sie in MySQL |
| `push/admin.php` | passwortgeschützte Seite zum **sofortigen** Senden (+ Statistik-Button) |
| `push/cron-send.php` | per Cron: Digest „läuft in der nächsten Stunde" |
| `push/vapid-keys.php` | erzeugt einmalig das VAPID-Schlüsselpaar |
| `push/sender.php`, `db.php` | gemeinsame Logik (Versand, DB, Schema) |
| `push/weather.php`, `weather-providers.php` | Wetter-Endpoint (GeoSphere/OpenWeather/WeatherAPI.com/MET Norway, Datei-Cache; Einstellungen im CMS-Tab „Wetter") |
| `push/track.php`, `stats-db.php` | anonymer Nutzungs-Zähler (App → MySQL) |
| `push/stats.php` | Statistik-Seite (gleiches Passwort wie admin.php) |

> **Upload-Kurzweg:** `deploy-data.bat push` lädt alle `push\*.php` hoch –
> außer `config.php`/`config.example.php`/`vapid-keys.php` (und ohne `vendor\`).

Die Client-Seite (Service Worker + „Benachrichtigungen aktivieren"-Schalter unter **Mehr**)
ist bereits in der App enthalten.

## Zwei Maschinen – wer macht was?

Es sind **zwei** Orte beteiligt. Jeder Schritt unten ist mit dem Ort markiert:

- 💻 **Lokaler PC** (dein Windows-Rechner mit Node/npm + dem Projekt): App bauen, Keys
  erzeugen, `config.php` vorbereiten, alles per `deploy-data.bat`/FTP hochladen.
- 🌐 **Webspace** (World4You): hier laufen die **PHP-Dateien** + **MySQL** + **Cron**.
  Befehle dort gibst du **per SSH** ein (falls dein Tarif SSH hat) **oder** im
  **Kundenbereich** (Cron, Datenbank) bzw. gar nicht direkt – dann erledigst du alles am
  PC und lädst nur Dateien hoch.

> Faustregel: **Alle `npm …`-Befehle = 💻 PC.** **PHP/Cron/DB = 🌐 Webspace.**
> Hat dein Webspace **kein SSH**, brauchst du dort **keine Kommandozeile** – du machst alles
> am PC und lädst per FTP hoch (siehe Hinweise je Schritt).

## Schritt für Schritt

### 1. 💻 VAPID-Schlüssel erzeugen (am PC, am einfachsten via Node)
Im Projektordner:
```bash
npx web-push generate-vapid-keys
```
Gibt **Public Key** und **Private Key** aus.
- **Public Key** → später in die App-`.env` (`VITE_VAPID_PUBLIC_KEY`) **und** in `config.php`.
- **Private Key** → nur in `config.php`. **Niemals committen.**

*(Alternative ohne Node: `push/vapid-keys.php` – braucht aber PHP am Server/SSH. Falls genutzt,
die Datei danach vom Server löschen.)*

### 2. 💻/🌐 Composer-Abhängigkeit (`push/vendor/`)
Das Versenden nutzt `minishlink/web-push` → der Ordner `push/vendor/` muss auf den Server.
- **Mit SSH am Webspace:** dort `cd push && composer install`.
- **Ohne SSH (Normalfall):** am **PC** `composer install` in `push/` ausführen und den Ordner
  **`push/vendor/` per FTP hochladen**. (Composer am PC nötig: `getcomposer.org`.)

### 3. 🌐 MySQL-Datenbank anlegen
Im **World4You-Kundenbereich** eine Datenbank anlegen (Name/User/Passwort notieren). Tabellen
werden beim ersten Zugriff **automatisch** erstellt (`push_subscriptions`, `push_log`).

### 4. 💻→🌐 `push/config.php` erstellen
Am **PC** kopieren und ausfüllen, dann **per FTP** als `push/config.php` hochladen:
```bash
copy push\config.example.php push\config.php   :: Windows
```
Eintragen: DB-Zugang (aus Schritt 3), `vapid.publicKey`/`privateKey` (Schritt 1),
`adminPasswordHash` (am PC erzeugen: `php -r "echo password_hash('DEIN_PASSWORT', PASSWORD_DEFAULT);"`),
ein `cronSecret` (zufällige Zeichenkette). `config.php` ist gitignored.

### 5. 💻 App mit Public-Key bauen & deployen
In die App-`.env` (PC):
```ini
VITE_VAPID_PUBLIC_KEY=<Public Key aus Schritt 1>
```
Dann **`deploy-data.bat full`**. Der „Benachrichtigungen"-Schalter unter **Mehr** erscheint nur,
wenn dieser Key gesetzt ist.

### 6. 🌐 Cronjob einrichten (nur für den Konzertstart-Digest)
Im **World4You-Kundenbereich** → Cronjobs, stündlich:
```
0 * * * *  php /pfad/zu/push/cron-send.php
```
Nur HTTP-Cron möglich? Externen Pinger (z. B. cron-job.org) auf
`https://app.rockimdorf.at/push/cron-send.php?key=<cronSecret>` zeigen lassen.
*(Für die Telegram-`#push`-Benachrichtigungen ist KEIN Cron nötig – die gehen sofort raus.)*

**Cron-Frequenz & News-Latenz:** Automatische News-Pushes gehen erst beim nächsten Cron-Lauf
raus – das Intervall bestimmt also die **Latenz**. Mehrere (gestaffelte) Cron-Einträge, z. B.
alle 10–15 Min, drücken sie entsprechend. Dann aber die **Digest-Vorlaufzeit** (CMS →
Einstellungen → `upcomingWindowMin`) passend verkleinern (z. B. 15–20 Min), sonst meldet
„Gleich live" Acts bis zu 60 Min zu früh. Mehrere Crons **nicht auf dieselbe Minute** legen
(sonst theoretisch doppelter Versand, bevor `push_log` greift) – ein paar Minuten versetzen.

**Mehrere Cron-Einträge beim selben Hoster:** Erlaubt dein Hoster denselben Dateipfad nicht
mehrfach als Cron, nutze die mitgelieferten Wrapper `push/cron-send-1.php` … `cron-send-5.php`
(jeder bindet nur `cron-send.php` ein – der Inhalt bleibt an einer Stelle). So legst du z. B.
6 Cron-Einträge an, gestaffelt auf `:00, :10, :20, :30, :40, :50` → Push alle ~10 Min.

> **Nicht** per `sleep()` verzögern: lang laufende PHP-Prozesse sind auf Shared Hosting
> unzuverlässig (HTTP-Cron-Timeouts ~30 s, `max_execution_time`, blockierte Worker). Staffle
> stattdessen über die **Cron-Zeiten** (Minutenfeld) oder einen externen Pinger (z. B.
> cron-job.org), der `cron-send.php?key=…` alle N Minuten aufruft – dann genügt **eine** Datei.

**Sofortiger Push (ohne Cron-Wartezeit):** Im CMS-Tab „News" gibt es je Eintrag das Häkchen
**„Sofort pushen"** – beim Speichern geht der (bereits veröffentlichte) Eintrag sofort raus
(kategoriebewusst, einmalig via `push_log`).

## „Mein Plan"-Erinnerungen

Gäste können im Benachrichtigungs-Popover (Glocke im Header) **„Mein Plan"** aktivieren und
bekommen dann **vor Konzertbeginn** eine Erinnerung zu ihren **favorisierten** Acts. Technisch:
- Die favorisierten Slot-IDs werden (nur IDs, anonym) im Abo gespeichert (`push_subscriptions.plan`)
  und bei jeder Favoriten-Änderung automatisch ans Backend nachgezogen.
- Der Cron (`cron-send.php`, Block a2) sendet **eine Push pro favorisiertem Act** innerhalb der
  Vorlaufzeit (`upcomingWindowMin`), jeden Slot pro Gerät **nur einmal** (Dedup über `push_log`,
  ref `plan:<hash>:<slotId>`).
- „Mein Plan"-Abonnenten bekommen den allgemeinen „Gleich live"-Digest (= Line-Up) **weiterhin**,
  aber **ohne ihre favorisierten Acts** – die kommen als persönliche Einzel-Erinnerung. So
  erscheint kein Act doppelt. (Block a1 = Digest an Nicht-Plan-Abos mit Line-Up; Block a2 =
  Plan-Abonnenten: Einzel-Pushes für Favoriten + personalisierter Digest der übrigen Acts,
  letzteres nur wenn „Line-Up" abonniert. Dedup pro Gerät+Slot via `push_log`.)

Damit „Mein Plan" greift, muss `autoPushUpcoming` aktiv sein und der Cron laufen (s. o.).

## Abo-Statistik (anonym)

Der Cron schreibt bei jedem Lauf (höchstens ~stündlich) einen Snapshot der **Abo-Zahlen** in
die Tabelle `push_stats` – **ausschließlich Zähler**, keine personenbezogenen Daten: Abos
gesamt sowie je Kategorie (Infos/Line-Up/Allgemein; Sicherheit = alle Abos). Anzeige (aktuell +
Verlauf) im **CMS-Tab „Push"**. Ohne Cron entsteht kein Verlauf; die aktuellen Zahlen sind
trotzdem live sichtbar.

## Testen

1. App über **HTTPS** öffnen (Push braucht HTTPS; iOS nur als installierte PWA, iOS 16.4+).
2. Unter **Mehr → Benachrichtigungen → Aktivieren** das Abo anlegen (Browser fragt um Erlaubnis).
3. `push/admin.php` öffnen, anmelden, Testnachricht senden → Notification erscheint.
4. Cron testen: `php push/cron-send.php` (CLI) bzw. die URL mit `?key=` aufrufen – JSON-Report zeigt `candidates`/`sent`.

## Push-Kategorien (wer bekommt was)

Automatische News-Pushes (Cron) sind **zweifach gefiltert**:

1. **Admin** legt fest, welche Kategorien überhaupt automatisch pushen – im CMS unter
   **Einstellungen → „Auto-Push: Kategorien"** (Infos / Line-Up / Allgemein). Gespeichert in
   `data/app-config.json` (`pushNewsCategories`), vom Cron live gelesen; Fallback ist
   `pushNewsCategories` aus `config.php`. **Sicherheit ist immer dabei.**
2. **Jeder Gast** wählt in der App unter **Benachrichtigungen**, welche dieser Kategorien er
   erhalten will. Die Auswahl wird im Abo gespeichert (Spalte `categories` in
   `push_subscriptions`; leer = nur Sicherheit, NULL = alle für Altbestand). **Sicherheit
   kommt immer an** und ist nicht abwählbar.

Effektiv gepusht wird ein News-Item also nur, wenn die Kategorie **admin-seitig aktiv** (oder
das Item `pinned`) ist **und** der Gast diese Kategorie gewählt hat (Sicherheit ausgenommen –
immer). Manuelle Pushes aus `push/admin.php` gehen weiterhin an **alle** Abos.

> Quelle der News für den Auto-Push ist `data/admin-news.json` (der „News"-Tab im CMS), sonst
> der Build-Stand `news.json`.

## Sicherheit

- `push/config.php` und `push/vendor/` sind in `.gitignore` und werden per `.htaccess`
  zusätzlich vor direktem Zugriff geschützt.
- Der private VAPID-Key und das Admin-Passwort bleiben ausschließlich auf dem Server.
- `cron-send.php` ist per `cronSecret` gegen fremde Aufrufe abgesichert.

## Erweiterungen (optional)

- **Getimte News pushen:** In `cron-send.php` zusätzlich `news.json` lesen und Items mit
  `category="safety"`/`pinned`, deren `publishAt` seit dem letzten Lauf erreicht wurde,
  als Push verschicken (Idempotenz wie bei Slots über `push_log`, ref = `news:<id>`).
- **Punktgenaue Erinnerungen:** externen 1-Minuten-Cron nutzen und das Zeitfenster in
  `cron-send.php` von 60 min auf z. B. 15 min verengen.
