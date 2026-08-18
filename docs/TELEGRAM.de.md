# Telegram-Live-News einrichten

**🇬🇧 [English](TELEGRAM.md) · 🇫🇷 [Français](TELEGRAM.fr.md) · 🇪🇸 [Español](TELEGRAM.es.md)**

Sende eine Nachricht an deinen Telegram-Bot → sie erscheint **innerhalb von ~2 Minuten**
in der App, **ohne Deploy**. Unmoderiert, nur für **erlaubte Absender** (du).

## Architektur (kurz)
- Live-News landen in **`data/live-news.json`** auf dem Server – getrennt von der
  redaktionellen `news.json`.
- Die App liest **beide** Dateien und mischt sie im Newsfeed.
- Der lokale Build/Deploy (`deploy-data.bat`) fasst `live-news.json` **nie** an
  (sie ist in `.gitignore` und wird lokal nicht gebaut). → kein Überschreiben.

## Einrichtung

### 1. Bot anlegen
In Telegram **@BotFather** → `/newbot` → **Bot-Token** notieren.

### 2. Eigene User-ID herausfinden
Schreib **@userinfobot** an → er nennt deine numerische `id` (z. B. `123456789`).

### 3. `push/config.php` ausfüllen
```php
'telegram' => [
    'botToken'       => '123456:ABC...',          // von BotFather
    'webhookSecret'  => 'ein-langes-zufallswort', // frei wählbar
    'allowedUserIds' => [123456789, 987654321],   // mehrere durch Komma getrennt
    'allowedChatIds' => [-1001234567890],         // optional: erlaubte Gruppe(n)
    'liveNewsFile'   => __DIR__ . '/../data/live-news.json',
    'tz'             => 'Europe/Vienna',
    'maxItems'       => 200,
],
```
- **Mehrere Personen erlauben:** weitere User-IDs einfach komma-getrennt in `allowedUserIds`.
- **Eine Gruppe erlauben** (jede Nachricht der Gruppe wird zur News): die **Gruppen-ID** in `allowedChatIds`.

### 4. `push/telegram-hook.php` hochladen
Liegt schon im Repo unter `push/`. Per FTP auf den Server (zusammen mit `push/`).

### 5. Webhook bei Telegram registrieren (einmalig)
Im Browser aufrufen (Token + Secret einsetzen):
```
https://api.telegram.org/bot<BOTTOKEN>/setWebhook?url=https://app.rockimdorf.at/push/telegram-hook.php&secret_token=<WEBHOOKSECRET>
```
Antwort `{"ok":true,...}` = passt. Telegram schickt ab jetzt jede Nachricht sofort an den Hook.

## Nutzung
Nachricht an den Bot senden:
- **Erste Zeile = Titel**, restliche Zeilen = Text.
- **Tags** (werden aus dem Text entfernt) steuern Optionen:
  - `#safety` / `#info` / `#lineup` / `#general` → Kategorie (Standard: general)
  - `#pin` → angepinnt (oben im Feed)
  - `#2h` / `#30m` → läuft 2 Stunden / 30 Minuten **nach Veröffentlichung** automatisch ab
  - `@HH:mm` → **geplante Veröffentlichung** heute um HH:mm (ist die Zeit schon vorbei → morgen). Ohne `@` wird sofort veröffentlicht.
  - `#push` → zusätzlich als **Web-Push** auf den Sperrbildschirm senden. **`#safety`** pusht **automatisch** (auch ohne `#push`). Push nur bei **sofortiger** Veröffentlichung (nicht bei `@HH:mm`-geplanten) und nur, wenn Web-Push eingerichtet ist (`docs/PUSH.de.md`). Steuerbar über `pushAutoCategories` in `config.php`.
- **Befehle:**
  - `/list` → zeigt die aktiven Live-News nummeriert (mit Ablaufzeit, falls gesetzt).
  - `/del <Nr>` → **widerruft** eine einzelne Live-News (Nummer aus `/list`).
  - `/clear` → löscht **alle** Live-News.

**Beispiel (sofort)**
```
Achtung Gewitter
Bitte die Zelte sichern und Schutz suchen. #safety #pin #3h
```
→ angepinnte Safety-News, verschwindet nach 3 Stunden. Bot bestätigt „✅ Veröffentlicht: …".

**Beispiel (geplant)**
```
Soundcheck-Pause
Kurze Unterbrechung auf der Main Stage. @18:00 #info #1h
```
→ erscheint **um 18:00** und verschwindet **um 19:00** (Ablauf zählt ab 18:00). Bot bestätigt „⏰ Geplant für 01.08. 18:00: …". Die Nachricht landet sofort in `live-news.json`; die App zeigt sie wegen `publishAt` erst ab 18:00.

## IDs herausfinden
- **Eigene User-ID:** Schreib **@userinfobot** an (oder send `/chatid` an deinen eigenen Bot – er antwortet mit User-ID und Chat-ID).
- **Gruppen-ID:** Bot **in die Gruppe holen**, dann in der Gruppe **`/chatid`** senden – der Bot antwortet mit der `Chat-ID` (negative Zahl, z. B. `-1001234567890`). Diese in `allowedChatIds` eintragen.
- **Wichtig für Gruppen:** Damit der Bot **alle** Gruppen-Nachrichten liest (nicht nur Befehle), bei **@BotFather** → `/setprivacy` → **Disable** wählen. Sonst kommen nur `/befehle` an.
- ⚠️ Eine erlaubte Gruppe heißt: **jedes Mitglied** kann News an alle Besucher senden.

## Schreibrechte (kein FTP nötig)
Live-News brauchen **kein FTP** – der PHP-Hook schreibt direkt in `data/live-news.json` auf
demselben Server. Voraussetzung: der **Ordner `data/` ist für PHP beschreibbar** (auf Shared
Hosting i. d. R. der Fall). Falls nicht: Schreibrechte für `data/` setzen.

## Hinweise
- `live-news.json` wird vom Server beim ersten Eintrag angelegt; vorher liefert die App
  einfach keine Live-News (kein Fehler).
- Auslieferung mit kurzem Cache (`max-age=120`), die App pollt alle 2 Minuten → Updates
  in ≤ 2 min sichtbar.
- **Sicherheit:** Ohne gültiges `webhookSecret` (Header von Telegram) und ohne erlaubte
  `allowedUserIds` wird **nichts** verarbeitet. Halte `botToken`/`webhookSecret` geheim.
- Erweiterung möglich: freigegebene Live-News zusätzlich als **Push** verschicken
  (Infrastruktur ist da, siehe `push/sender.php`).
