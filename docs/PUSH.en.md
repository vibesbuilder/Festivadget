# Setting up web push (phase 5)

**🇩🇪 [Deutsch](PUSH.md) · 🇫🇷 [Français](PUSH.fr.md) · 🇪🇸 [Español](PUSH.es.md)**

Web push enables notifications on the lock screen, **even when the app is
closed** – for safety announcements, short-notice changes and "on soon" hints.

The backend is deliberately minimal: a few **PHP files** in the
[`push/`](../push/) folder on the same **web space** (no VPS needed).
Prerequisites: **PHP 8.1+** with `openssl`, `mbstring` and `gmp` **or**
`bcmath`, plus **MySQL** and **cron**.

## Overview

| File | Purpose |
|---|---|
| `push/subscribe.php` | receives subscriptions from the browser, stores them in MySQL |
| `push/admin.php` | password-protected page for **immediate** sending (+ statistics button) |
| `push/cron-send.php` | via cron: digest "playing within the next hour" |
| `push/vapid-keys.php` | generates the VAPID key pair once |
| `push/sender.php`, `db.php` | shared logic (sending, DB, schema) |
| `push/weather.php`, `weather-providers.php` | weather endpoint (GeoSphere/OpenWeather/WeatherAPI.com/MET Norway, file cache; settings in the CMS "Weather" tab) |
| `push/track.php`, `stats-db.php` | anonymous usage counter (app → MySQL) |
| `push/stats.php` | statistics page (same password as admin.php) |

> **Upload shortcut:** `deploy-data.bat push` uploads all `push\*.php` –
> except `config.php`/`config.example.php`/`vapid-keys.php` (and without `vendor\`).

The client side (service worker + "Enable notifications" toggle under **More**)
is already included in the app.

## Two machines – who does what?

**Two** places are involved. Every step below is marked with the place:

- 💻 **Local PC** (your Windows machine with Node/npm + the project): build the
  app, generate keys, prepare `config.php`, upload everything via
  `deploy-data.bat`/FTP.
- 🌐 **Web space** (e.g. World4You): this is where the **PHP files** + **MySQL**
  + **cron** run. You enter commands there **via SSH** (if your plan has SSH)
  **or** in the **customer panel** (cron, database) or not directly at all –
  then you do everything on the PC and only upload files.

> Rule of thumb: **all `npm …` commands = 💻 PC.** **PHP/cron/DB = 🌐 web space.**
> If your web space has **no SSH**, you need **no command line** there – you do
> everything on the PC and upload via FTP (see the notes per step).

## Step by step

### 1. 💻 Generate VAPID keys (on the PC, easiest via Node)
In the project folder:
```bash
npx web-push generate-vapid-keys
```
Outputs **public key** and **private key**.
- **Public key** → later into the app `.env` (`VITE_VAPID_PUBLIC_KEY`) **and** into `config.php`.
- **Private key** → only into `config.php`. **Never commit.**

*(Alternative without Node: `push/vapid-keys.php` – but needs PHP on the
server/SSH. If used, delete the file from the server afterwards.)*

### 2. 💻/🌐 Composer dependency (`push/vendor/`)
Sending uses `minishlink/web-push` → the folder `push/vendor/` must get onto the server.
- **With SSH on the web space:** there `cd push && composer install`.
- **Without SSH (the normal case):** run `composer install` in `push/` on the
  **PC** and upload the folder **`push/vendor/` via FTP**. (Composer on the PC
  required: `getcomposer.org`.)

### 3. 🌐 Create the MySQL database
Create a database in your hosting **customer panel** (note name/user/password).
Tables are created **automatically** on first access (`push_subscriptions`,
`push_log`).

### 4. 💻→🌐 Create `push/config.php`
Copy and fill in on the **PC**, then upload **via FTP** as `push/config.php`:
```bash
copy push\config.example.php push\config.php   :: Windows
```
Enter: DB access (from step 3), `vapid.publicKey`/`privateKey` (step 1),
`adminPasswordHash` (generate on the PC: `php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);"`),
a `cronSecret` (random string). `config.php` is gitignored.

### 5. 💻 Build & deploy the app with the public key
Into the app `.env` (PC):
```ini
VITE_VAPID_PUBLIC_KEY=<public key from step 1>
```
Then **`deploy-data.bat full`**. The "Notifications" toggle under **More** only
appears if this key is set.

### 6. 🌐 Set up the cron job (only for the concert-start digest)
In the hosting **customer panel** → cron jobs, hourly:
```
0 * * * *  php /path/to/push/cron-send.php
```
Only HTTP cron possible? Point an external pinger (e.g. cron-job.org) at
`https://app.rockimdorf.at/push/cron-send.php?key=<cronSecret>`.
*(NO cron is needed for the Telegram `#push` notifications – they go out immediately.)*

**Cron frequency & news latency:** automatic news pushes only go out on the
next cron run – the interval therefore determines the **latency**. Several
(staggered) cron entries, e.g. every 10–15 min, reduce it accordingly. But then
also reduce the **digest lead time** (CMS → Settings → `upcomingWindowMin`)
appropriately (e.g. 15–20 min), otherwise "On soon" reports acts up to 60 min
too early. Do **not** put several crons on the same minute (otherwise
theoretically double sending before `push_log` kicks in) – offset by a few
minutes.

**Several cron entries with the same hoster:** if your hoster does not allow
the same file path multiple times as cron, use the bundled wrappers
`push/cron-send-1.php` … `cron-send-5.php` (each only includes
`cron-send.php` – the logic stays in one place). This way you create e.g. 6
cron entries staggered at `:00, :10, :20, :30, :40, :50` → push every ~10 min.

> Do **not** delay via `sleep()`: long-running PHP processes are unreliable on
> shared hosting (HTTP cron timeouts ~30 s, `max_execution_time`, blocked
> workers). Stagger via the **cron times** (minute field) instead, or use an
> external pinger (e.g. cron-job.org) that calls `cron-send.php?key=…` every N
> minutes – then **one** file is enough.

**Immediate push (without cron delay):** in the CMS "News" tab there is a
checkbox **"Push immediately"** per entry – on save, the (already published)
entry goes out immediately (category-aware, once via `push_log`).

## "My plan" reminders

Visitors can enable **"My plan"** in the notification popover (bell in the
header) and then get a reminder **before the start** of their **favourited**
acts. Technically:
- The favourited slot IDs are stored (IDs only, anonymously) in the
  subscription (`push_subscriptions.plan`) and synced to the backend
  automatically on every favourites change.
- The cron (`cron-send.php`, block a2) sends **one push per favourited act**
  within the lead time (`upcomingWindowMin`), each slot per device **only
  once** (dedup via `push_log`, ref `plan:<hash>:<slotId>`).
- "My plan" subscribers **still** get the general "On soon" digest (= line-up),
  but **without their favourited acts** – those come as a personal individual
  reminder. So no act appears twice. (Block a1 = digest to non-plan subs with
  line-up; block a2 = plan subscribers: individual pushes for favourites +
  personalized digest of the remaining acts, the latter only if "Line-up" is
  subscribed. Dedup per device+slot via `push_log`.)

For "My plan" to work, `autoPushUpcoming` must be active and the cron must run
(see above).

## Subscription statistics (anonymous)

On every run (at most ~hourly) the cron writes a snapshot of the
**subscription numbers** into the `push_stats` table – **counters only**, no
personal data: total subscriptions and per category (info/line-up/general;
safety = all subscriptions). Display (current + history) in the **CMS "Push"
tab**. Without cron no history accumulates; the current numbers are still
visible live.

## Testing

1. Open the app via **HTTPS** (push needs HTTPS; iOS only as an installed PWA, iOS 16.4+).
2. Under **More → Notifications → Enable** create the subscription (the browser asks for permission).
3. Open `push/admin.php`, log in, send a test message → notification appears.
4. Test the cron: `php push/cron-send.php` (CLI) or call the URL with `?key=` – the JSON report shows `candidates`/`sent`.

## Push categories (who gets what)

Automatic news pushes (cron) are filtered **twice**:

1. The **admin** defines which categories push automatically at all – in the
   CMS under **Settings → "Auto-push: categories"** (info / line-up / general).
   Stored in `data/app-config.json` (`pushNewsCategories`), read live by the
   cron; fallback is `pushNewsCategories` from `config.php`. **Safety is always
   included.**
2. **Each visitor** chooses in the app under **Notifications** which of these
   categories they want to receive. The choice is stored in the subscription
   (column `categories` in `push_subscriptions`; empty = safety only, NULL =
   all for legacy rows). **Safety always arrives** and cannot be deselected.

A news item is therefore effectively pushed only if the category is
**admin-side active** (or the item is `pinned`) **and** the visitor has chosen
this category (except safety – always). Manual pushes from `push/admin.php`
still go to **all** subscriptions.

> The news source for the auto-push is `data/admin-news.json` (the "News" tab
> in the CMS), otherwise the build state `news.json`.

## Security

- `push/config.php` and `push/vendor/` are in `.gitignore` and additionally
  protected from direct access via `.htaccess`.
- The private VAPID key and the admin password stay exclusively on the server.
- `cron-send.php` is protected against foreign calls via `cronSecret`.

## Extensions (optional)

- **Push scheduled news:** in `cron-send.php` additionally read `news.json` and
  send items with `category="safety"`/`pinned` whose `publishAt` was reached
  since the last run as push (idempotency like slots via `push_log`, ref =
  `news:<id>`).
- **Minute-precise reminders:** use an external 1-minute cron and narrow the
  window in `cron-send.php` from 60 min to e.g. 15 min.
