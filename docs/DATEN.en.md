# Maintaining & connecting data

**🇩🇪 [Deutsch](DATEN.md) · 🇫🇷 [Français](DATEN.fr.md) · 🇪🇸 [Español](DATEN.es.md)**

This guide explains two things:

1. **Enabling the Joomla connection** – fetch individual sections automatically from a Joomla website.
2. **Replacing the sample data with real data** – e.g. timetable, line-up, info pages.

> Core principle (IMPLEMENTATION.md §6): at runtime the app is **purely
> static**. Data is fetched **at build time** from the configured sources,
> normalized to the schema (§7) and stored as `public/data/*.json`. The running
> app only reads these JSON files.

The workflow is always:

```bash
npm run import      # fetches data per content-sources.config.ts -> public/data/*.json
npm run build:data  # validates + generates version.json (hashes)
npm run build       # production build into dist/  (only for deployment)
```

---

## 1. Enabling the Joomla connection

### 1.1 Prerequisites in Joomla

In Joomla 5, enable the **Web Services / API** and create an **API token**:

- Enable *System → Global Configuration → API*.
- On the user (preferably a **read-only** account) under *Edit → API Token* generate a token.
- The required content (articles) lives in **categories** – note the
  **category IDs** (e.g. line-up = 12, news = 20). The ID is in the category's
  URL in the backend (`&id=…`).

### 1.2 Put the token into `.env` (never commit!)

The `.env` is a plain text file in the **project root folder**. Copy the template:

```bash
cp .env.example .env          # macOS/Linux/Git Bash
```
```bat
copy .env.example .env        :: Windows (cmd / PowerShell)
```

Then enter the real Joomla token in `.env` (one line, **no** spaces around the
`=`, no quotes):

```ini
JOOMLA_API_TOKEN=your-real-token
```

- The **name** `JOOMLA_API_TOKEN` must match `tokenEnv` in
  `content-sources.config.ts` (default: `tokenEnv: "JOOMLA_API_TOKEN"`).
- The **value** is the API token generated in Joomla in 1.1.
- `npm run import` loads the `.env` **automatically** (Node
  `process.loadEnvFile`) and passes the token to the Joomla adapter (as HTTP
  header `Authorization: Bearer …`).

`.env` is in `.gitignore` – tokens never end up in the repo nor in the browser
(the import only runs locally/at build time, the token stays on your machine).

### 1.3 Switching the source per section (`content-sources.config.ts`)

Here you choose **per content domain** where the data comes from
(`manual` | `joomla` | `wordpress`). Example: line-up and news from Joomla,
the rest still manual:

```ts
joomla: { baseUrl: "https://example.com", tokenEnv: "JOOMLA_API_TOKEN" },
bindings: {
  artists: { provider: "joomla", joomla: { categoryId: 12 } },
  news:    { provider: "joomla", joomla: { categoryId: 20 } },
  // everything else stays "manual":
  festival: { provider: "manual" },
  stages:   { provider: "manual" },
  slots:    { provider: "manual", format: "csv" },
  // ...
}
```

> According to CLAUDE.md, `content-sources.config.ts` is an area that
> **requires confirmation** – make changes deliberately.

#### Info pages: source **per sub-item**

The info pages can be bound to their own source per entry. `info.default`
provides the **structure + texts** (`content/info.json`: `id`, `icon`,
`order`, `hidden`, fallback title/text). In `info.overrides` a different source
can be chosen per **entry ID** – it then only provides **title/text**, the
structure stays from `default`:

```ts
info: {
  default: { provider: "manual" }, // content/info.json
  overrides: {
    // text of the "parken" page from Joomla article 42, order/icon/hidden stay from content/info.json:
    parken: { provider: "joomla", joomla: { ids: [42] } },
    // "platzordnung" from WordPress:
    platzordnung: { provider: "wordpress", wordpress: { postType: "page", acf: { body: "inhalt" } } },
  },
},
```

**Visibility:** every info entry can be hidden from menu **and** search with
`"hidden": true` (in `content/info.json`) – the page remains reachable via
direct link (`/info/<id>`) (handy for preparing/previewing).

### 1.4 Importing

```bash
npm run import && npm run build:data
```

The Joomla adapter (`scripts/adapters/joomla.ts`) calls
`{baseUrl}/api/index.php/v1/content/articles?filter[category]={id}` with the
`Bearer` token, cleans the HTML (`scripts/lib/normalize.ts`) and maps it onto
the schema.

### 1.5 Mapping fields (custom fields)

Joomla **custom fields** (e.g. stage times on the artist article) are mapped to
schema fields via `customFields`:

```ts
artists: {
  provider: "joomla",
  joomla: {
    categoryId: 12,
    customFields: { country: "land", spotifyEmbedId: "spotify" },
  },
},
```

If the **timetable slots** should come directly from artist articles (instead
of CSV), see §6.5 in IMPLEMENTATION.md – set `slots.format` to
`"joomla-customfields"` (this path is still being refined in the adapter;
currently the most robust way for the timetable is the CSV, see below).

> **Note on the current state:** the Joomla/WordPress adapter already provides
> a generic mapping (id, slug, name, body, image, custom fields). The
> *domain-specific* fine mapping (which Joomla field becomes exactly which
> artist/news field) is deliberately kept lean and can be adapted per project
> in `scripts/adapters/joomla.ts`.

---

## 2. Replacing the sample data with real data

As long as a section is set to `provider: "manual"`, the data comes from the
[`content/`](../content/) folder. Simply fill the sample files there with real
content and run `npm run import && npm run build:data`.

| Section | File | Format |
|---|---|---|
| Festival/days | `content/festival.json` | object |
| Stages | `content/stages.json` | array |
| Acts/line-up | `content/artists.json` | array |
| **Timetable** | `content/slots.csv` | CSV |
| Map POIs | `content/pois.json` | array |
| POI categories | `content/poi-categories.json` | array |
| Site map | `content/map.json` (+ `public/map/…`) | object + image |
| News | `content/news.json` | array |
| Sponsors | `content/sponsors.json` (+ `public/img/sponsors/…`) | array |
| Info pages | `content/info.json` | array (Markdown in `body`) |
| Tickets | `content/tickets.json` | object |
| Weather | `content/weather.json` | object |

The exact field descriptions are in `src/types/index.ts` or IMPLEMENTATION.md §7.

### 2.0 Acts (`content/artists.json`)

- **`spotify`** (optional): embeds a Spotify player on the artist page. You can
  flexibly enter whatever you copy from Spotify:
  - the **share link** (`Share → Copy link`), e.g. `https://open.spotify.com/artist/XXXX?si=…`
  - the complete **embed code** (`Share → Embed → Copy code`, the whole `<iframe …>`)
  - or short `artist/XXXX` resp. `track/XXXX`, `album/XXXX`, `playlist/XXXX`

  ```json
  { "slug": "greeen", "name": "GReeeN",
    "spotify": "https://open.spotify.com/artist/4LM5wjVbpvUS6kU5dejdMS" }
  ```
- **`youtube`** (optional): embeds a YouTube video below the Spotify player.
  Allowed: the watch link (`https://www.youtube.com/watch?v=…`), the short
  link (`https://youtu.be/…`), the embed URL, the complete `<iframe>` embed
  code **or** the bare 11-character video ID.

  ```json
  { "slug": "greeen", "name": "GReeeN", "youtube": "https://youtu.be/dQw4w9WgXcQ" }
  ```
- **`genres`** (optional): may be empty (`[]`) **or omitted entirely** – then
  simply no genre line is shown.
- **`lineup`** (optional): controls whether the act appears in the **line-up**.
  Default is visible; `"lineup": false` hides it there (e.g. programme items
  like yoga or a pub quiz that should only appear in the timetable).
  Timetable/stage times are unaffected.

  ```json
  { "id": "yoga", "slug": "yoga", "name": "Yoga", "lineup": false }
  ```
- **`order`** (optional, number): defines the **sort order in the line-up** –
  smaller number = further to the front. Acts **with** `order` come first
  (ascending), then all **without** `order` automatically (headliners first,
  then alphabetically). So you don't have to number everything – setting the
  ones you want to place deliberately is enough.

  ```json
  { "id": "bibiza", "slug": "bibiza", "name": "Bibiza", "order": 1 }
  ```
- **`isHeadliner`** (optional, true): shows a **"Headliner" badge** on the card
  **and** sorts the act towards the front of the line-up (before the acts
  without `order`).
- **`isDj`** (optional, true): shows a **"DJ" badge** (secondary color) on the
  card – **without** effect on the order. Combinable with `isHeadliner` (then
  both badges).

### 2.05 News (`content/news.json`)

Required: `id`, `title`, `body`, `category` (`info`/`safety`/`lineup`/`general`), `publishAt`.
Optional:
- **`expiresAt`** (ISO with offset): the news disappears at this **absolute** time (same for everyone).
- **`hideAfterFirstOpenMin`** (number): hides the news **X minutes after this
  device first opened the app** (individual per device – ideal for the welcome
  news).
- **`pinned`** (true) → top of the feed. **`link`** → button: `{ "label": "…", "url": "…" }`.
- Links **in the text** via Markdown: `[text](https://…)` or internal `[My plan](/favorites)`.

```json
{
  "id": "news-welcome", "title": "Welcome!", "body": "Great to have you here.",
  "category": "general", "publishAt": "2026-05-31T10:00:00+02:00",
  "pinned": true, "hideAfterFirstOpenMin": 10
}
```

### 2.06 POI categories (`content/poi-categories.json`)

Categories of the map points – color, icon and visibility. A POI references a
category's `id` via `type`.

- **`id`** (required): key that `Poi.type` points to (e.g. `parking`). **Do not
  change afterwards** – otherwise existing POIs point into the void (fallback
  rendering).
- **`label`** (required): display name in filter/detail. **`color`** (required): hex color of the marker.
- **`icon`** (required): three forms possible –
  1. **Emoji** (e.g. `🅿️`).
  2. **Image path/URL** (e.g. `/data/uploads/zelt.svg`, upload via the
     "Images" tab). Values starting with `/`, `http(s):`, `data:` or ending in
     `.svg/.png/.webp/.jpg/.gif` are rendered as an image.
  3. **Lucide icon name** (monochrome, color automatically contrasts with the
     marker). Available names:
     `ambulance`, `first-aid`, `cross`, `plus`, `utensils` (`food`), `beer`, `coffee`, `pizza`, `wine`,
     `cooking-pot`, `car`, `bus`, `train-front` (`train`), `bike`, `square-parking` (`parking`),
     `circle-parking`, `tent`, `caravan`, `music`, `mic`, `guitar`, `disc-3` (`dj`), `info`,
     `badge-info`, `ticket`, `tickets`,
     `shower-head` (`shower`), `bath`, `baby`, `dog`, `accessibility`, `credit-card`, `shopping-bag`,
     `box`, `shirt`, `wifi`, `phone`, `map-pin`, `flag`, `star`, `heart`, `flame`, `trees`, `sun`, `umbrella`,
     `door-open`, `log-out` (`exit`), `square-arrow-right` (`square-arrow-right-exit`),
     `square-arrow-out-up-right`, `shield`, `droplet`, `zap`, `anchor`, `cigarette`.

  > **Font Awesome:** FA classes (`<i class="fa-…">`) are **not** supported
  > directly (the app does not bundle Font Awesome). If you want an FA icon:
  > "Download SVG" on fontawesome.com, upload it in the "Images" tab and enter
  > it as an image path (form 2) – or use a matching Lucide icon (form 3).
- **`order`** (number): order in the filter bar.
- **`hidden`** (true): hides the category **completely** – from the map AND the
  filter, for **all** visitors (master switch).

Individual POIs can set their **own** marker icon with **`icon`** (emoji
**or** image path); empty = category icon.

```json
{ "id": "parking", "label": "Parking", "color": "#9aa0a6", "icon": "🅿️", "order": 15 }
```

### 2.1 Timetable (`content/slots.csv`)

Columns: `artistSlug,stageId,dayId,start,end,note`

```csv
artistSlug,stageId,dayId,start,end,note
greeen,main,fr,2026-07-31T21:30:00+02:00,2026-07-31T23:00:00+02:00,
bibiza,main,sa,2026-08-01T22:00:00+02:00,2026-08-01T23:30:00+02:00,
```

Important:

- `artistSlug` must match a `slug` in `artists.json` (otherwise the import fails).
- `stageId` must correspond to an `id` in `stages.json`.
- `dayId` is the `id` of a day from `festival.json` (`fr`/`sa`/`so`).
  Performances **after midnight** (e.g. 00:30) get the `dayId` of the
  **previous day** – this way they correctly count towards the right festival
  day (midnight overflow).
- Timestamps **always ISO 8601 with offset** (`+02:00` = Vienna summer time).

### 2.2 Images

Place images locally under `public/img/...` and the site map under
`public/map/gelaendeplan.webp` and reference them in the JSON files by path
(e.g. `"image": "/img/artists/bibiza.webp"`). Local instead of hotlinking
because of the offline cache and CORS (§6.6). The header logo lives in
`public/img/logo.svg`.

### 2.3 Data updates during operation

After `import` + `build:data`, upload only the changed `dist/data/*.json`
**and** `version.json` to the server. The app polls `version.json` every 2
minutes and only re-fetches changed datasets – **no** complete rebuild/upload
of the app needed (IMPLEMENTATION.md §15).
