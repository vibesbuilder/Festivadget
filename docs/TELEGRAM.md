# Setting up Telegram live news

**🇩🇪 [Deutsch](TELEGRAM.de.md) · 🇫🇷 [Français](TELEGRAM.fr.md) · 🇪🇸 [Español](TELEGRAM.es.md)**

Send a message to your Telegram bot → it appears **within ~2 minutes** in the
app, **without a deploy**. Unmoderated, only for **allowed senders** (you).

## Architecture (short)
- Live news end up in **`data/live-news.json`** on the server – separate from
  the editorial `news.json`.
- The app reads **both** files and mixes them in the news feed.
- The local build/deploy (`deploy-data.bat`) **never** touches `live-news.json`
  (it is in `.gitignore` and is not built locally). → no overwriting.

## Setup

### 1. Create a bot
In Telegram **@BotFather** → `/newbot` → note the **bot token**.

### 2. Find your own user ID
Message **@userinfobot** → it tells you your numeric `id` (e.g. `123456789`).

### 3. Fill in `push/config.php`
```php
'telegram' => [
    'botToken'       => '123456:ABC...',          // from BotFather
    'webhookSecret'  => 'a-long-random-word',     // your choice
    'allowedUserIds' => [123456789, 987654321],   // multiple, comma-separated
    'allowedChatIds' => [-1001234567890],         // optional: allowed group(s)
    'liveNewsFile'   => __DIR__ . '/../data/live-news.json',
    'tz'             => 'Europe/Vienna',
    'maxItems'       => 200,
],
```
- **Allow multiple people:** simply add more user IDs, comma-separated, to `allowedUserIds`.
- **Allow a group** (every message of the group becomes a news item): put the **group ID** into `allowedChatIds`.

### 4. Upload `push/telegram-hook.php`
Already in the repo under `push/`. Upload via FTP to the server (together with `push/`).

### 5. Register the webhook with Telegram (once)
Open in the browser (insert token + secret):
```
https://api.telegram.org/bot<BOTTOKEN>/setWebhook?url=https://app.rockimdorf.at/push/telegram-hook.php&secret_token=<WEBHOOKSECRET>
```
Response `{"ok":true,...}` = fine. From now on Telegram forwards every message to the hook immediately.

## Usage
Send a message to the bot:
- **First line = title**, remaining lines = text.
- **Tags** (removed from the text) control options:
  - `#safety` / `#info` / `#lineup` / `#general` → category (default: general)
  - `#pin` → pinned (top of the feed)
  - `#2h` / `#30m` → expires automatically 2 hours / 30 minutes **after publication**
  - `@HH:mm` → **scheduled publication** today at HH:mm (if the time has already passed → tomorrow). Without `@` it is published immediately.
  - `#push` → additionally send as **web push** to the lock screen. **`#safety`** pushes **automatically** (even without `#push`). Push only for **immediate** publication (not for `@HH:mm`-scheduled ones) and only if web push is set up (`docs/PUSH.md`). Controllable via `pushAutoCategories` in `config.php`.
- **Commands:**
  - `/list` → shows the active live news numbered (with expiry time if set).
  - `/del <no>` → **revokes** a single live news item (number from `/list`).
  - `/clear` → deletes **all** live news.

**Example (immediate)**
```
Warning: thunderstorm
Please secure your tents and seek shelter. #safety #pin #3h
```
→ pinned safety news, disappears after 3 hours. The bot confirms "✅ Published: …".

**Example (scheduled)**
```
Soundcheck break
Short interruption on the main stage. @18:00 #info #1h
```
→ appears **at 18:00** and disappears **at 19:00** (expiry counts from 18:00).
The bot confirms "⏰ Scheduled for 01.08. 18:00: …". The message lands in
`live-news.json` immediately; the app only shows it from 18:00 because of
`publishAt`.

## Finding IDs
- **Your own user ID:** message **@userinfobot** (or send `/chatid` to your own bot – it replies with user ID and chat ID).
- **Group ID:** add the bot **to the group**, then send **`/chatid`** in the group – the bot replies with the `chat ID` (negative number, e.g. `-1001234567890`). Put it into `allowedChatIds`.
- **Important for groups:** so the bot reads **all** group messages (not only commands), go to **@BotFather** → `/setprivacy` → choose **Disable**. Otherwise only `/commands` arrive.
- ⚠️ An allowed group means: **every member** can send news to all visitors.

## Write permissions (no FTP needed)
Live news need **no FTP** – the PHP hook writes directly to `data/live-news.json`
on the same server. Prerequisite: the **`data/` folder is writable for PHP**
(usually the case on shared hosting). If not: set write permissions for `data/`.

## Notes
- `live-news.json` is created by the server on the first entry; before that the
  app simply serves no live news (no error).
- Delivered with a short cache (`max-age=120`), the app polls every 2 minutes →
  updates visible in ≤ 2 min.
- **Security:** without a valid `webhookSecret` (header from Telegram) and
  without allowed `allowedUserIds`, **nothing** is processed. Keep
  `botToken`/`webhookSecret` secret.
- Possible extension: additionally send approved live news as **push**
  (infrastructure is there, see `push/sender.php`).
