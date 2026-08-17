# Admin UI (CMS)

**🇩🇪 [Deutsch](ADMIN.md) · 🇫🇷 [Français](ADMIN.fr.md) · 🇪🇸 [Español](ADMIN.es.md)**

Password-protected web interface on the web space to control the app **without
redeploying**. Lives under `push/cms/` and uses the existing `push/` auth.

## Architecture

```
push/cms/index.php  ──(writes)──►  data/app-config.json   ──(app reads live, 2-min poll)──►  React
   │                               data/app-info.json   (phase 2)
   └─ login: adminPasswordHash     data/live-news.json  (phase 4, already present)
      from push/config.php
```

- **Auth:** session + `password_verify` against `adminPasswordHash` from
  `push/config.php` (same password as `push/admin.php`). CSRF token on every
  save.
- **Persistence:** server-owned JSON files in the `data/` folder (= `dataDir`
  from `push/config.php`). The app reads them live, just like the Telegram live
  news. These files belong to the server and are **never** built/committed
  locally (they are in `.gitignore`).
- **Client:** `src/data/useAppConfig.ts` loads `data/app-config.json`
  (2-min poll, fallback = defaults when the file is missing).

## Access

`https://app.rockimdorf.at/push/cms/` → log in with the admin password.
After logging in, the **"Settings"** tab opens (start tab).

## Interface language

The CMS is available in **German, English, French and Spanish**. The language
is changed in the **"Settings"** tab → "CMS language" and applies server-side
for all admins (stored in `push/cms-settings.json`, blocked via `.htaccess`,
never in the repo). German is the source language; the translation table lives
in `push/cms/i18n.php` (function `cms_t()`), missing keys fall back to German.
The **app language** is chosen by each visitor independently in the app itself
(German/English/French/Spanish).

## Help tab

The **"Help"** tab links all manuals (ADMIN, DATEN, PUSH, TELEGRAM,
IMPLEMENTATION) as Markdown files in all four languages. The files are copied
to `dist/docs/` during the app build and uploaded with `deploy-data.bat full`
(reachable under `/docs/<name>.md`); missing files are hidden by the tab.
Custom background image: selectable from the uploads under **Settings** →
"Background image" (`backgroundImage` in `app-config.json`).

## Branding tab (customer branding without a build)

The **"Branding"** tab customises the app's appearance at runtime – no new
build required. Everything is stored in `app-config.json` → `branding`;
removed values automatically fall back to the build defaults.

- **Title & short name**: browser tab title and home screen label
  (short name max. 12 characters). Both also feed the PWA manifest.
- **Font set**: 4 sets built from pure system/web-safe stacks (Standard,
  System, Serif, Poster) – no font files needed, stays offline-capable.
- **Colours**: accent colours plus complete palettes for the dark and light
  theme separately (hex values, pre-filled with the build defaults). The
  "Reset colours" checkbox removes all custom colours again.
- **Logo**: replaces the logo in the top bar (`/data/uploads/branding-logo.*`).
- **PWA icons**: upload one square PNG (min. 192 px, 512 px recommended) –
  the server generates 192/512 px plus a maskable icon via GD (dark
  background colour, 80 % safe zone). Requires the PHP **GD** extension on
  the server.
- **Intro video (home)**: shown full-width above the news feed. Source
  "Link/file" (direct video file via FTP/https; YouTube/Vimeo embedded as
  players automatically) or "Microsoft cloud" (OneDrive/SharePoint embed
  URL); enabled via checkbox.
- **Manifest**: as soon as a title, short name or icons are set, the app
  swaps the manifest link at runtime to `/push/manifest.php` (dynamic: name,
  colours, icons from the CMS). Without a PHP backend the static
  `manifest.webmanifest` from the build keeps applying. New icons/names take
  effect on the **next installation** of the PWA (existing installations are
  refreshed by the OS with a delay).

## Deployment

Upload `push/cms/` via FTP into the `push/` folder (like the rest of `push/`).
Prerequisite: `push/config.php` with `adminPasswordHash` set
(`php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);"`) and a
**writable** `data/` folder (`live-news.json` already lives there).

## Generic live override (foundation)

`useDataset` (query layer) additionally loads `data/app-<domain>.json` for
**every** domain (2-min poll). If this file exists, it **replaces** the build
state `data/<domain>.json`. Both the admin editors and the server importer
build on this: both write `app-<domain>.json`. If it is missing, the build
state applies unchanged. (News: the "News" tab writes `admin-news.json`; if the
file exists, it **replaces** `news.json` in the feed – Telegram
`live-news.json` is still mixed in **additionally**.)

## Feature stages

1. **Foundation + More menu** ✅ — login; visibility of the More menu items
   (`moreHidden[]` in `app-config.json`).
2. **Info pages** ✅ — toggle items on/off, rename, add/delete (incl. text,
   order, icon) → `data/app-info.json`. If the file exists, it **replaces** the
   build state (`info.json`) client-side; the editor is pre-filled from
   `info.json` on first use (seed). Hidden entries (`hidden`) are removed from
   menu **and** search but remain reachable via direct link.
   **Q&A accordion:** checkbox "Show as Q&A accordion" (`faq: true`) per entry.
   Every `## heading` in the text then becomes a collapsible question, the text
   below it the answer; text **before** the first `## question` appears as a
   normal intro block above. Without `## …` in the text it stays normal
   Markdown. Usable for any entry (e.g. "Cashless").
   **Source per entry:** field `source` (`manual`/`joomla`/`wordpress`) +
   `sourceLocator` (Joomla article ID or WP slug/ID). "Import from
   Joomla/WordPress" pulls title/text from the article **only** for entries
   marked this way; structure and manual entries remain. (Save first, then
   import.)
3. **Global settings** ✅ — `lineupImageLimit` (acts with image), `background`
   (background artwork on/off), `themeDefault` (`dark`/`light`, only applies
   until the visitor toggles themselves). In `app-config.json`.
4. **News & push** ✅ — news editor (title, Markdown text, category, pin,
   publish/expiry, optional link) → `data/admin-news.json`. **The only** news
   management: pre-filled from `news.json` on first open, afterwards it
   **replaces** the build state in the feed (`useNewsFeed`); Telegram
   `live-news.json` is mixed in additionally. The cron pushes new entries
   automatically (category filter, see `docs/PUSH.en.md`). **Auto-push
   categories** are chosen under "Settings" (`pushNewsCategories`). The push
   tab sends immediately to all subscriptions (`push_broadcast` from
   `sender.php`).
5. **Live override for all domains** ✅ — `useDataset` prefers
   `data/app-<domain>.json` (see above). Foundation for 6/7.
6. **Content editors per domain**
   - 6a ✅ Generic **"Content" tab**: every domain (festival, stages, artists,
     slots, pois, map, sponsors, tickets, weather, info, news) as a validated
     JSON editor (pre-filled from the current state, list/object check,
     "Remove override" → build state) → `data/app-<domain>.json`.
   - 6a-POI ✅ **POI categories** as their own domain ("Content" →
     "POI categories", `app-poi-categories.json`):
     `id`/`label`/`icon`(emoji)/`color`/`order`/`hidden`. Create your own
     categories, rename, **show/hide** (`hidden` = master switch, entirely
     removed from map + filter). In the POI form, `type` is a dropdown of these
     categories; per POI optionally a **custom icon** (`icon`, overrides the
     category icon). **Icons** may be: **emoji**, **image path** (upload your
     own artwork via the "Images" tab → `/data/uploads/…`) or a **Lucide icon
     name** (e.g. `ambulance`, `utensils`, `parking`; full list in
     `docs/DATEN.en.md`) – applies to categories and individual POIs.
     Font Awesome classes (`<i class="fa-…">`) do **not** work directly; upload
     the FA icon as SVG instead.
   - 6b ✅ **Image upload** ("Images" tab) → `data/uploads/`, served under
     `/data/uploads/<name>`; use the path in "Content" as `image`/`logo`.
   - 6c ✅ **Convenience forms** (instead of JSON) for `stages`, `sponsors`,
     `pois`, `artists` (schema-driven) + **tabular slots editor** (act/stage/
     day as dropdowns, times as datetime-local). Switchable in the "Content"
     tab between form and "Edit as JSON"; remaining domains stay JSON.
> **Joomla API URL:** the importer uses the SEF form
> `…/api/v1/content/articles` (without `index.php`). On some servers (e.g.
> World4You) the path after `index.php/` is swallowed (PATH_INFO) → the
> `index.php` form then returns 404 for every call. Prerequisite: active SEF
> `.htaccess` with the authorization pass-through line
> (`RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`).

7. **Server importer** ✅ — "Sources" tab: per domain
   `manual`/`joomla`/`wordpress` + locator (Joomla: category ID, WP: category
   slug) → `data/source-config.json`. "Import now" (`importer.php`) pulls via
   curl and writes `app-<domain>.json`. Connection/token in `push/config.php` →
   `sources`. **Best-effort generic mapping** (id/slug/title/name/body) – fits
   text content; post-edit structured domains (artists/slots/news) in the
   "Content" tab if needed. Controls only the server import, **independent** of
   `content-sources.config.ts` (build import). The `body` keeps **sanitized
   HTML**: headings, images and iframes from allowed hosts
   (YouTube/Spotify/Google Maps); `data:` images, `script` and foreign iframes
   are removed (`cms_clean_html`). The app renders this safely
   (`rehype-raw`+`rehype-sanitize`, additional iframe host whitelist in the
   client).

## Getting a Joomla API bearer token (for the server importer)

The token is **generated in Joomla** (per user), not "found" somewhere:

1. **Enable plugins** (System → Plugins): *Web Services - Content* (unlocks
   `/v1/content/articles`) and *User - Joomla API Token* (generates + verifies
   the bearer token). *Basic authentication* is **not** needed.
2. **Set the API login permission** (System → Global Configuration →
   Permissions): for the API user's group, **"Web Services Login"
   (`core.login.api`) = Allowed**. By default **only** the Super User has it →
   if missing, you get **403 "Forbidden"**. Recommendation: a dedicated,
   minimal group "API" (parent Public) with only this permission.
3. **Generate the token** (Users → Manage → edit the API user): tab
   **"Joomla API Token"** → show/regenerate → copy.
4. Enter it in `push/config.php` → `sources.joomla.token` (ONLY the token,
   single quotes, WITHOUT `Authorization: Bearer`; secret, never commit).
5. **Locator:** per info entry the **article ID** (Content → Articles, column
   "ID"); for the per-domain import the **category ID** (Content → Categories).
6. Test (URL **without** `index.php`, otherwise possibly 404):
   `curl -g -H "Authorization: Bearer <TOKEN>" -H "Accept: application/vnd.api+json" "https://rockimdorf.at/api/v1/content/articles"` → JSON with articles = ok.

**Failure patterns:** 404 everywhere = `index.php` URL (PATH_INFO) or
plugin/`.htaccess` (see above). 403 = group without `core.login.api`. 401 =
token invalid/missing.

## app-config.json – fields

| Field              | Type                | Meaning                                                |
|--------------------|---------------------|--------------------------------------------------------|
| `moreHidden`       | `string[]`          | Hidden More menu items (keys, see below).              |
| `lineupImageLimit` | `number?`           | Acts with image in the line-up (otherwise 20).         |
| `background`       | `boolean?`          | Background artwork on/off (default: on).               |
| `backgroundImage`  | `string?`           | Custom background image (`/data/uploads/…`, empty = bundled artwork). |
| `themeDefault`     | `"dark"\|"light"?`  | Default theme until the visitor picks one themselves.  |
| `homeVideo`        | `object?`           | Intro video on home (`{url, source, enabled}`), managed in the CMS "Branding" tab. |
| `branding`         | `object?`           | Customer branding (colours, font, logo, title, icons) – managed via the CMS "Branding" tab. |

More menu keys: `news`, `map`, `info`, `sponsors`, `tickets`, `contact`,
`impressum`, `theme`, `language` (must match `src/routes/More.tsx`).
