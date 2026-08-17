# Installing Festivadget (no build machine)

*Languages: [Deutsch](INSTALL.md) · [Français](INSTALL.fr.md) · [Español](INSTALL.es.md)*

Festivadget installs like Joomla/WordPress: **upload the release package,
open the installer in the browser, done.** No local build machine
(Node/pnpm) is required – just a webspace.

## Requirements

- Webspace with **PHP 8.1+** and FTP access (shared hosting is fine).
- Optional for **web push**: a MySQL database and a cron job.
- Optional for **CMS branding** (PWA icons): PHP extension `gd`.

The installer checks all requirements itself and shows what is missing.

## Installation

1. Extract the release package (`festivadget-vX.Y.Z.zip`) and upload its
   contents via FTP into the **webroot** of your (sub)domain. Important:
   **install immediately** – as long as no `push/config.php` exists, the
   installer is reachable by anyone.
2. Open `https://your-domain/install/` in the browser (DE/EN).
3. Fill in the wizard:
   - **CMS admin password** (required) – used to sign in at `/push/cms/`.
   - **MySQL credentials** (optional) – enables web push; the VAPID keys are
     generated automatically. Leave empty to install without push (can be
     added later in `push/config.php`, see [PUSH.en.md](PUSH.en.md)).
4. After the success message, **delete the `install/` folder** (button on the
   final page or via FTP).
5. Done: app at `/`, CMS at `/push/cms/`. Content, branding and the
   background image are all managed in the CMS (see [ADMIN.en.md](ADMIN.en.md)).
   With web push, also add the cron job ([PUSH.en.md](PUSH.en.md), step 6).

## Updates (manual)

When applying a new version, customer content stays untouched: **never
overwrite `data/` (content + uploads) and `push/config.php`** – everything
else (app files, `push/*.php`, `push/cms/`, `push/vendor/`) may be replaced.
A convenient one-click updater is planned.

## Building the release package yourself (maintainer)

Run `composer install` in `push/` once (for `push/vendor/`), then:

```bash
powershell -File tools/build-release.ps1
```

Builds the app **neutrally** (without baked-in instance values) and creates
`release/festivadget-v<version>.zip` with the app build, `push/` (without
secrets) and `install/`. Note: `data/` in the package equals the build state
of `public/data/` – use sample data for public releases.
