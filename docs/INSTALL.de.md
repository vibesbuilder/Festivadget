# Festivadget installieren (ohne Build-Maschine)

*Sprachen: [English](INSTALL.md) · [Français](INSTALL.fr.md) · [Español](INSTALL.es.md)*

Festivadget wird wie Joomla/WordPress installiert: **Release-Paket hochladen,
Installer im Browser öffnen, fertig.** Es ist keine lokale Build-Maschine
(Node/pnpm) nötig – nur ein Webspace.

## Voraussetzungen

- Webspace mit **PHP 8.1+** und FTP-Zugang (Shared Hosting reicht).
- Optional für **Web-Push**: eine MySQL-Datenbank und ein Cronjob.
- Optional fürs **CMS-Branding** (PWA-Icons): PHP-Erweiterung `gd`.

Der Installer prüft alle Voraussetzungen selbst und zeigt an, was fehlt.

## Installation

1. Release-Paket (`festivadget-vX.Y.Z.zip`) entpacken und den Inhalt per FTP
   in den **Webroot** der (Sub-)Domain hochladen. **Unterordner (z. B. `/testapp/`) werden nicht unterstützt** – der Installer prüft das (der App-Build nutzt absolute Pfade); notfalls eine Subdomain auf das Verzeichnis zeigen lassen. Wichtig: Das Paket **sofort
   installieren** – solange keine `push/config.php` existiert, ist der
   Installer für jeden erreichbar.
2. Im Browser `https://deine-domain/install/` öffnen (DE/EN).
3. Assistent ausfüllen:
   - **CMS-Admin-Passwort** (Pflicht) – damit meldest du dich unter
     `/push/cms/` an.
   - **MySQL-Zugang** (optional) – aktiviert Web-Push; die VAPID-Schlüssel
     werden dabei automatisch erzeugt. Leer lassen = ohne Push installieren
     (später in `push/config.php` nachtragbar, siehe [PUSH.de.md](PUSH.de.md)).
4. Nach der Erfolgsmeldung den **Ordner `install/` löschen** (Knopf auf der
   Abschlussseite oder per FTP).
5. Fertig: App unter `/`, CMS unter `/push/cms/`. Inhalte, Branding und
   Hintergrundbild pflegst du komplett im CMS (siehe [ADMIN.de.md](ADMIN.de.md)).
   Mit Web-Push noch den Cronjob anlegen ([PUSH.de.md](PUSH.de.md), Schritt 6).

## Updates

Für Updates gibt es das eigene **Update-Paket** `festivadget-update-vX.Y.Z.zip`
(wie das Release, aber **ohne `data/` und ohne `install/`**). Kundeninhalte
bleiben in beiden Varianten unangetastet – `data/` (Inhalte, Uploads,
Branding), `push/config.php` und die CMS-/Wetter-Einstellungen werden nie
überschrieben.

- **Komfort (1-Klick):** Im CMS unter **Update** das Update-Paket hochladen –
  fertig. Das CMS prüft das Paket (volle Release-Pakete werden abgelehnt),
  spielt nur ungeschützte Dateien ein und zeigt die installierte Version
  (Datei `VERSION`). Braucht die PHP-Erweiterung `zip` (oder `phar`).
- **Minimal (FTP):** Update-Paket entpacken und per FTP über die Installation
  kopieren (überschreiben). Da `data/` und `install/` nicht im Paket sind,
  ist nichts weiter zu beachten.

## Release-Paket selbst bauen (Maintainer)

Einmalig `composer install` in `push/` (für `push/vendor/`), dann:

```bash
powershell -File tools/build-release.ps1
```

Baut die App **neutral** (ohne eingebaute Instanz-Werte) und erzeugt
`release/festivadget-v<version>.zip` mit App-Build, `push/` (ohne Secrets)
und `install/`. Hinweis: `data/` im Paket entspricht dem Build-Stand von
`public/data/` – für öffentliche Releases Beispieldaten verwenden.
