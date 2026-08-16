# IMPLEMENTATION.md — ROCK IM DORF Festival App (PWA)

**🇩🇪 [Deutsch](IMPLEMENTATION.md) · 🇫🇷 [Français](IMPLEMENTATION.fr.md) · 🇪🇸 [Español](IMPLEMENTATION.es.md)**

> Working document for development with Claude Code.
> Project name (proposal): `rid-festival-app`
> Goal: installable, offline-capable progressive web app for festival visitors,
> hosted as static files on a subdomain (e.g. `app.rockimdorf.at`).

**Version of this document:** 1.1.0 · **As of:** 2026-06-23

---

## 0. Table of contents

1. Project goal & scope
2. Design direction
3. Tech stack
4. Architecture overview
5. Data freshness & caching strategy
6. Data source configuration (selectable per menu item)
7. Data model / JSON schema (normalized target)
8. Project/directory structure
9. Routing
10. Component structure
11. State & local persistence
12. Feature specifications
13. PWA / offline / installation
14. Internationalization (i18n)
15. Build & deployment (World4You)
16. GitHub project setup & changelog
17. Development roadmap (phases & effort)
18. CHANGELOG template

---

## 1. Project goal & scope

A **PWA** (no native app, no app store) as the central visitor app for the
festival. Core principle: **fully static**. All content comes from versioned
JSON files served by the web server. There is **no mandatory backend** anymore.

The content data is fetched **at build time** from selectable sources
(manual / Joomla / WordPress, see §6) and normalized to a single JSON schema.
At runtime the app is purely static.

### In scope (MVP + expansion)

| Feature | Backend needed at runtime? |
|---|---|
| Line-up + artist pages | no |
| Timetable (multiple views) | no |
| Favorites / my plan + `.ics` reminder | no |
| Interactive offline map with POIs | no |
| News/info feed with scheduled publication + auto concert start | no |
| Now / up next | no |
| Search | no |
| Sponsors section | no |
| Info pages (arrival, site, camping, caravan, cashless, ride-home, food, drinks, FAQ) | no |
| Weather | no (reads a prepared JSON) |
| Ticket shops (iframe/link) | no |
| Web push (optional expansion) | yes (separate) |

### Out of scope (deliberately)
- **Spotted** — removed (UGC + moderation + GDPR effort not wanted).
- Friends / live location sharing.
- Lock screen widget (native only).
- Own cashless/ticketing system (comes from external providers).

---

## 2. Design direction

Oriented towards rockimdorf.at (Joomla 5, T4 template):

- **Dark theme**: dark ground, white typography, **yellow accent**
  (green-event yellow) for CTAs and active states (favorited, "playing now").
- **Artist images in portrait 4:5** (1080×1350), large-area/full-bleed.
- Bold **display face** for headlines, clear sans for body text.
- Tone: rock, "off the mainstream", deliberately familiar/"small & fine".

Tokens as a Tailwind theme (placeholders — **verify exact hex/fonts from the
T4 CSS**):

```css
:root {
  --rid-bg:        #121212; /* TODO: verify from T4 CSS */
  --rid-surface:   #1c1c1c;
  --rid-text:      #ffffff;
  --rid-muted:     #b3b3b3;
  --rid-accent:    #f2c200; /* green-event yellow, TODO verify */
  --rid-accent-2:  #e4572e; /* secondary (e.g. NowLine), optional */
}
```

> Before phase 4: pull exact color values, font families and logo assets from
> the live template and enter them here.

---

## 3. Tech stack

- **Build:** Vite + React 18 + TypeScript
- **Styling:** TailwindCSS (theme tokens from §2)
- **Routing:** `react-router-dom` v6 (alternative: TanStack Router)
- **Server state / data fetching:** TanStack Query (`@tanstack/react-query`)
- **Client state:** Zustand (favorites, UI state)
- **Local persistence:** IndexedDB via `idb-keyval`
- **Date/time/timezone:** Luxon (mandatory because of `Europe/Vienna` + midnight overflow)
- **Map:** Leaflet (`CRS.Simple`, ImageOverlay)
- **PWA / service worker:** `vite-plugin-pwa` (Workbox underneath)
- **i18n:** `react-i18next` (de default, en optional)
- **Icons:** `lucide-react`
- **Markdown rendering (info/news/bio):** `react-markdown` + `remark-gfm`
- **`.ics` generation:** own mini function (no package needed)
- **Build-time import:** Node scripts + `papaparse` (CSV), `node-fetch`/`undici` (REST)

> Note: **deliberately NOT FullCalendar** for the timetable. The stage×time
> grid maps more cleanly and mobile-friendly with CSS Grid.

---

## 4. Architecture overview

```
                    Browser (PWA)
   ┌──────────────────────────────────────────────┐
   │  React app (app shell, cache-first)           │
   │   - TanStack Query (data)                     │
   │   - Zustand (favorites) -> IndexedDB          │
   │   - Service worker (Workbox)                  │
   └───────────────┬──────────────────────────────┘
                   │  HTTPS GET (static)
                   ▼
   ┌──────────────────────────────────────────────┐
   │  Static web server (World4You)                │
   │   /            -> app shell (build artifacts) │
   │   /data/*.json -> content data (versioned)    │
   │   /data/version.json -> hashes (no-cache)     │
   │   /map/*.webp  -> site map image              │
   └──────────────────────────────────────────────┘

   ── build time (not at runtime) ──────────────────
   scripts/import-from-source.ts reads content-sources.config.ts
   and fetches per menu item from: manual | Joomla API | WordPress API
   -> normalizes -> public/data/*.json + version.json

   ── optional (own VPS) ──────────────────────────
   Web push (VAPID) — only if real push reminders are wanted.
```

Operation is **purely static**. Source connection happens exclusively at build
time.

---

## 5. Data freshness & caching strategy

Goal: online ~live (**≤ 2 min**), offline the last known state.

### 5.1 File classes & HTTP headers

| Path | Cache-Control | SW strategy |
|---|---|---|
| App shell (`/assets/*` with hash) | `max-age=31536000, immutable` | CacheFirst (precache) |
| `index.html` | `no-cache` | NetworkFirst |
| `/data/*.json` (content) | `max-age=120` | NetworkFirst, timeout 3 s, fallback cache |
| `/data/version.json` | `no-cache` | NetworkOnly (with cache fallback offline) |
| `/map/*.webp` | `max-age=86400` | StaleWhileRevalidate |

### 5.2 Version polling (near-live, 2-minute cycle)

`version.json` contains a content hash per dataset:

```json
{
  "generatedAt": "2026-07-31T16:00:00+02:00",
  "datasets": {
    "festival": "a1b2c3", "artists": "d4e5f6", "stages": "0099aa",
    "slots": "7788bb", "pois": "ccddee", "news": "ff1122",
    "sponsors": "334455", "info": "667788", "tickets": "99aabb",
    "weather": "bbccdd"
  }
}
```

- TanStack Query loads `version.json` with `refetchInterval: 120_000`
  (**2 min**), only when `document.visibilityState === 'visible'` and online.
- When a dataset's hash changes, **only its query is invalidated** → targeted refetch.
- `version.json` is regenerated automatically by the data build (§15).

> Content change on the server → visible for all online clients within ~2 min.

### 5.3 Offline behaviour
- The first fetch pre-caches all `/data/*.json` and the map image.
- On network failure, NetworkFirst serves from the cache; the UI shows
  "Offline / as of: HH:MM" (from `generatedAt` of the last successful fetch,
  kept in IndexedDB).

---

## 6. Data source configuration (selectable per menu item)

**Core requirement:** for **every menu item / content domain** it is
individually selectable whether the data is maintained **manually** or fetched
via the **Joomla** or **WordPress API**. Resolution happens at build time via a
central configuration and exchangeable adapters. The runtime schema (§7) is
source-independent — no matter where the data comes from, it ends up in the
same normalized JSON.

### 6.1 Configuration file `content-sources.config.ts`

```ts
type Provider = "manual" | "joomla" | "wordpress";

interface JoomlaLocator {
  categoryId?: number;        // article category (e.g. line-up Friday)
  ids?: number[];             // explicit article IDs
  customFields?: Record<string, string>; // schema field -> Joomla custom field name
}

interface WordPressLocator {
  categorySlug?: string;      // category slug
  postType?: string;          // "post" or custom post type
  acf?: Record<string, string>; // schema field -> ACF field name
}

interface SourceBinding {
  provider: Provider;
  joomla?: JoomlaLocator;
  wordpress?: WordPressLocator;
  // provider === "manual" -> data comes from content/<domain>.json (maintained in the repo)
}

interface ContentSourcesConfig {
  // connection defaults (tokens ONLY from ENV, never commit):
  joomla?:    { baseUrl: string; tokenEnv: string };          // e.g. "JOOMLA_API_TOKEN"
  wordpress?: { baseUrl: string; userEnv?: string; appPwEnv?: string };

  // one binding per domain / menu item:
  bindings: {
    festival: SourceBinding;
    stages:   SourceBinding;
    artists:  SourceBinding;
    slots:    SourceBinding & { format?: "csv" | "joomla-customfields" | "wordpress-acf" };
    pois:     SourceBinding;
    news:     SourceBinding;
    sponsors: SourceBinding;
    tickets:  SourceBinding;
    weather:  SourceBinding;     // usually "manual"
    // info pages individually overridable (each page its own source):
    info: {
      default: SourceBinding;
      overrides?: Record<string, SourceBinding>; // key = InfoPage.id ("faq", "anreise", ...)
    };
  };
}
```

**Example** (artists from Joomla, FAQ manual, rest mixed):

```ts
export const config: ContentSourcesConfig = {
  joomla:    { baseUrl: "https://rockimdorf.at", tokenEnv: "JOOMLA_API_TOKEN" },
  wordpress: { baseUrl: "https://example.org", userEnv: "WP_USER", appPwEnv: "WP_APP_PW" },
  bindings: {
    festival: { provider: "manual" },
    stages:   { provider: "manual" },
    artists:  { provider: "joomla", joomla: { categoryId: 12 } },
    slots:    { provider: "joomla", format: "joomla-customfields",
                joomla: { customFields: { stage: "buehne", start: "start", end: "ende" } } },
    pois:     { provider: "manual" },
    news:     { provider: "joomla", joomla: { categoryId: 20 } },
    sponsors: { provider: "joomla" }, // weblinks component, see 6.3
    tickets:  { provider: "manual" },
    weather:  { provider: "manual" },
    info: {
      default: { provider: "joomla", joomla: { categoryId: 8 } },
      overrides: { faq: { provider: "manual" } },
    },
  },
};
```

### 6.2 Importer architecture (adapter pattern)

`scripts/import-from-source.ts` iterates over `bindings`, calls the matching
adapter per `provider`, normalizes to the schema (§7), downloads referenced
images locally (`public/img/...`, because of offline + same-origin) and writes
`public/data/<domain>.json`. Then the `version.json` hashes are recalculated.

```
scripts/
├─ import-from-source.ts        # orchestration, reads config + ENV
├─ build-data.ts                # validation (schema) + version.json (hashes)
└─ adapters/
   ├─ manual.ts                 # reads content/<domain>.json, validates
   ├─ joomla.ts                 # Joomla web services REST API
   ├─ wordpress.ts              # WordPress REST API (+ ACF)
   └─ csv.ts                    # parses content/slots.csv (papaparse)
```

Every adapter implements the same interface:

```ts
interface SourceAdapter {
  fetchDomain(domain: string, binding: SourceBinding, cfg: ContentSourcesConfig): Promise<unknown[]>;
}
```

### 6.3 Joomla adapter

- **Articles** (artists, news, info): `GET {baseUrl}/api/index.php/v1/content/articles?filter[category]={id}`
  with header `Authorization: Bearer {JOOMLA_API_TOKEN}`. Single articles via `/articles/{id}`.
- **Custom fields**: contained in the API response (com_fields) or requested
  via field parameters; mapping via `joomla.customFields`.
- **Sponsors (weblinks)**: web-services endpoint of the weblinks component if
  the plugin is active; fallback = RSS feed of the respective weblinks category
  (presented by / powered by / partner).
- **Sanitize** the HTML body of the articles and convert to Markdown (or
  cleaned HTML).

### 6.4 WordPress adapter

- **Posts/CPT**: `GET {baseUrl}/wp-json/wp/v2/{postType}?categories={id}` (auth
  via application password, basic auth via `WP_USER`/`WP_APP_PW`).
- **ACF** (counterpart to Joomla custom fields): fields in the REST response
  when "Show in REST" is active or via ACF-to-REST; mapping via
  `wordpress.acf`.
- Resolve images via `_embed`/media endpoint, download locally.

### 6.5 Timetable source (switchable)

`slots.format` determines where stage + start/end come from:

| `format` | Source | Fields |
|---|---|---|
| `csv` | `content/slots.csv` | `artistSlug,stageId,dayId,start,end,note` |
| `joomla-customfields` | custom fields of the artist articles | per `joomla.customFields` |
| `wordpress-acf` | ACF fields of the artist posts | per `wordpress.acf` |

With `csv`, slots are joined to the artists (possibly fetched from elsewhere)
via `artistSlug`. With the custom-field variants, slots are derived directly
from the artist records.

`content/slots.csv` (example):

```csv
artistSlug,stageId,dayId,start,end,note
bibiza,main,sa,2026-08-01T22:00:00+02:00,2026-08-01T23:30:00+02:00,
greeen,main,fr,2026-07-31T21:30:00+02:00,2026-07-31T23:00:00+02:00,
paula-carolina,second,fr,2026-07-31T19:30:00+02:00,2026-07-31T20:30:00+02:00,
```

### 6.6 Security of the connection

- API tokens / app passwords **exclusively in `.env`** (in `.gitignore`), never commit.
- Joomla token **read-only**, scoped; whitelist the build machine by IP if possible.
- Since the import runs **server-side** at build time, credentials **never
  reach the browser**.
- Sanitize HTML from CMS sources before saving.
- Copy images locally instead of hotlinking (offline cache + no CORS issues).

---

## 7. Data model / JSON schema (normalized target)

All files live under `/data/`. TypeScript types under `src/types/`.
IDs are short, stable strings (slug-like). Timestamps **always ISO 8601 with
offset** (`+02:00`). This is the **source-independent target** — adapters (§6)
must map onto it.

### 7.1 `festival.json`
```ts
interface Festival {
  name: string; edition: number; timezone: string; // "Europe/Vienna"
  start: string; end: string;
  days: FestivalDay[];
  contact?: { email?: string; phone?: string; web?: string };
}
interface FestivalDay {
  id: string;        // "fr" | "sa" | "so"
  label: string;     // "Friday 31.07."
  dayStart: string;  // logical start of the day
  dayEnd: string;    // logical end of the day (midnight overflow!)
}
```

### 7.2 `stages.json`
```ts
interface Stage {
  id: string; name: string; shortName: string;
  color: string; order: number; poiId?: string;
}
```

### 7.3 `artists.json`
```ts
interface Artist {
  id: string; slug: string; name: string;
  bio?: string; genres: string[]; country?: string;
  isHeadliner?: boolean; image?: string; gallery?: string[];
  links?: {
    spotify?: string; appleMusic?: string; bandcamp?: string;
    youtube?: string; instagram?: string; facebook?: string; website?: string;
  };
  spotifyEmbedId?: string;
}
```

### 7.4 `slots.json`
```ts
interface Slot {
  id: string; artistId: string; stageId: string; dayId: string;
  start: string; end: string; note?: string; cancelled?: boolean;
}
```

### 7.5 `pois.json`
```ts
type PoiType =
  | "stage" | "wc" | "food" | "drink" | "firstaid" | "atm"
  | "info" | "entrance" | "exit" | "camping" | "caravan"
  | "cashless" | "shuttle" | "merch" | "parking";
interface Poi {
  id: string; type: PoiType; name: string; description?: string;
  x: number; y: number;   // pixel coordinates in the CRS.Simple system
  stageId?: string; icon?: string;
}
```

### 7.6 `map.json`
```ts
interface MapConfig {
  image: string; width: number; height: number;
  minZoom: number; maxZoom: number;
}
```

### 7.7 `news.json`
```ts
type NewsCategory = "info" | "safety" | "lineup" | "general";
interface NewsItem {
  id: string; title: string; body: string; category: NewsCategory;
  publishAt: string;      // client shows it only from this point in time
  expiresAt?: string; pinned?: boolean;
  image?: string; link?: { label: string; url: string };
}
```
> **Auto concert-start entries** are generated at runtime from `slots.json`
> (see §12.5) and merged with the editorial news.

### 7.8 `sponsors.json`
```ts
type SponsorTier = "main" | "premium" | "partner" | "supporter";
interface Sponsor { id: string; name: string; logo: string; tier: SponsorTier; url?: string; order: number; }
```

### 7.9 `info.json`
```ts
interface InfoPage {
  id: string;   // "anreise"|"gelaende"|"camping"|"caravan"|"cashless"
                // "bringmichheim"|"kulinarik"|"getraenke"|"faq"
  title: string; icon?: string; order: number; body: string; // Markdown
}
```

### 7.10 `tickets.json`
```ts
interface TicketProvider { id: string; name: string; embedType: "iframe" | "link"; url: string; note?: string; }
interface TicketsConfig { providers: TicketProvider[]; }
```

### 7.11 `weather.json`
```ts
interface WeatherDay {
  dayId: string; date: string; tempMin: number; tempMax: number;
  symbolCode: string; precipitationProb?: number; summary?: string;
}
interface Weather { generatedAt: string; source: "open-meteo" | "geosphere"; days: WeatherDay[]; }
```

---

## 8. Project/directory structure

```
rid-festival-app/
├─ public/
│  ├─ data/                 # generated by import + build-data
│  │  ├─ festival.json stages.json artists.json slots.json
│  │  ├─ pois.json map.json news.json sponsors.json
│  │  ├─ info.json tickets.json weather.json
│  │  └─ version.json       # generated (hashes)
│  ├─ map/gelaendeplan.webp
│  ├─ img/{artists,sponsors}/...   # placed locally by the importer
│  ├─ icons/                # PWA icons (192,512,maskable)
│  └─ manifest.webmanifest
├─ content/                 # manually maintained sources (provider:"manual")
│  ├─ festival.json stages.json pois.json tickets.json weather.json ...
│  └─ slots.csv             # if slots.format === "csv"
├─ content-sources.config.ts  # §6: source per menu item
├─ .env.example             # JOOMLA_API_TOKEN, WP_USER, WP_APP_PW (sample values)
├─ scripts/
│  ├─ import-from-source.ts
│  ├─ build-data.ts
│  └─ adapters/{manual,joomla,wordpress,csv}.ts
├─ src/
│  ├─ main.tsx App.tsx
│  ├─ routes/               # pages (§9)
│  ├─ components/           # (§10)
│  ├─ features/{timetable,favorites,map,news}/
│  ├─ data/                 # query hooks (useArtists, useSlots, useVersion, ...)
│  ├─ lib/                  # ics.ts time.ts search.ts sw-register.ts
│  ├─ store/                # Zustand stores
│  ├─ types/                # schema types from §7
│  ├─ i18n/                 # de.json en.json config
│  └─ styles/
├─ CLAUDE.md CHANGELOG.md README.md LICENSE .gitignore
├─ vite.config.ts tailwind.config.ts package.json
```

> An optional `backend/` (FastAPI) only if web push (§13) is actually built.

---

## 9. Routing

Mobile-first, bottom tab bar for the main sections.

| Path | Page | Content |
|---|---|---|
| `/` | Home | now/up next, pinned news, next favorite, weather teaser |
| `/lineup` | Line-up | artist grid, genre filter, headliners first |
| `/artist/:slug` | Artist page | bio, Spotify embed, stage times, favorite |
| `/timetable` | Timetable | grid/list view, day tabs, clash markers, now line |
| `/favorites` | My plan | favorited slots, `.ics` export, clash hint |
| `/map` | Map | Leaflet, POI filter, detail sheet |
| `/news` | News & info | merged feed (editorial + auto concert start), safety on top |
| `/info` + `/info/:id` | Info | overview + Markdown detail |
| `/sponsors` | Sponsors | grouped by tier |
| `/tickets` | Tickets | iframe/link per provider |
| `/search` | Search | global (artists/slots/info/POIs) |

Tab bar (5 slots): **Home · Line-up · Timetable · Map · More**.
"More" opens a sheet: my plan, news, info, sponsors, tickets, search, language.

---

## 10. Component structure

```
App
├─ AppShell (TopBar, <Outlet/>, BottomNav, OfflineBadge)
├─ data/  useVersion() (2-min poll) · useFestival/useStages/useArtists/useSlots/usePois/...
├─ features/timetable/  TimetableGrid · TimetableList · DayTabs · SlotCard · NowLine · useClashes()
├─ features/favorites/  FavoriteButton · useFavorites() · IcsButton
├─ features/map/        FestivalMap (CRS.Simple) · PoiMarker · PoiFilterBar · PoiSheet
├─ features/news/       NewsFeed (merge editorial+auto, publishAt filter) · NewsItemCard · SafetyBanner
└─ components/  ArtistCard ArtistGrid GenreFilter SpotifyEmbed SponsorGrid
                InfoList InfoPage NowNextWidget SearchOverlay WeatherStrip
                TicketEmbed InstallHint
```

---

## 11. State & local persistence

- **Server state** (all JSON): TanStack Query, `staleTime` 2 min, invalidated by version polling.
- **Favorites**: Zustand store, persisted in IndexedDB (`idb-keyval`, key `favorites`) as `Set<slotId>`.
- **UI state** (day, filter, language): Zustand + `localStorage`.
- **Last data state** (`generatedAt`): IndexedDB, for the offline display.

`.ics` generation (`src/lib/ics.ts`): VEVENT with VALARM (`-PT15M`); works on
iOS + Android. Reminder UX: star = favorite; the "Reminder (.ics)" button
downloads the event with a 15-min lead.

---

## 12. Feature specifications

### 12.1 Line-up + artist pages
Grid of `ArtistCard` (headliners first, otherwise alphabetical), genre filter
(chips). Artist page: header (image 4:5, name, genre, country), bio (Markdown),
`SpotifyEmbed`, stage times from `slots`, `FavoriteButton`.

### 12.2 Timetable (multiple views)
- **Grid**: CSS Grid, columns = stages (by `order`, color `stage.color`), rows = time axis.
- **List**: chronological per day, filter "favorites only".
- **DayTabs** by `FestivalDay`; midnight overflow via `dayStart/dayEnd` (Luxon).
- **NowLine**: current time; **clash indicator** via `useClashes()` over favorited slots.
- Slot data source per §6.5 (csv / joomla-customfields / wordpress-acf).

### 12.3 Favorites / my plan + `.ics`
Star on slot/artist; "My plan" shows favorites chronologically with clash
warning; `.ics` individually or "all".

### 12.4 Interactive offline map
Leaflet `L.CRS.Simple` + `L.imageOverlay` (bounds from `map.json`). POI markers
per `type`, filter bar, `PoiSheet` with detail. Image pre-cached as `.webp` →
fully offline. Skip GPS own-position for now.

### 12.5 News feed (scheduled) + auto concert start
Editorial items only visible when `publishAt <= now` (and `expiresAt > now`).
Auto concert start: per slot a virtual item
`{category:"lineup", title:"Now: <artist> @ <stage>", time:slot.start}`,
visible from `start <= now`. Merge both, descending by time, `pinned`/`safety`
on top. Safety prominent (banner). Scheduled preparation via a future
`publishAt`.

### 12.6 Now / up next
`NowNextWidget` on home: per stage "playing now" + "up next" from `slots` + `now`.

### 12.7 Search
Client-side index over artists/slots/info/POIs; substring/token match
(optionally `match-sorter`).

### 12.8 Sponsors
`SponsorGrid` grouped by `tier`; logo links to `url`.

### 12.9 Info pages
Markdown render; FAQ as `## question` + answer (optionally accordion).
Source per page individually configurable (§6.1, `info.overrides`).

### 12.10 Weather
`WeatherStrip` reads `weather.json`. Per day symbol + min/max.

### 12.11 Ticket shops
`tickets.json` controls `embedType`. **iframe** with `sandbox` + `allow` list;
**fallback "link"** if the shop forbids framing via `X-Frame-Options`/CSP.

---

## 13. PWA / offline / installation

- `vite-plugin-pwa`. Precache app shell; runtime caching per §5.1.
- `manifest.webmanifest`: name, short name, theme/background color
  (dark/yellow), icons (192/512 + maskable), `display:"standalone"`,
  `start_url:"/"`, `scope:"/"`.
- Install hints: Android/Chrome `beforeinstallprompt` button; iOS `InstallHint`
  ("Share → Add to Home Screen").
- **Web push** remains an optional expansion (VAPID, separate backend, iOS only
  after home-screen install). Not needed for the MVP — reminders run via `.ics`.

---

## 14. Internationalization (i18n)

`react-i18next`, default **de**, optionally **en/fr/es**. UI strings in
`src/i18n/{de,en,fr,es}.json`. Content data monolingual (de); optional `*_en`
fields possible later, not in the MVP.

---

## 15. Build & deployment (World4You)

1. Create `.env` with credentials (from `.env.example`).
2. `npm run import` → `import-from-source.ts` reads
   `content-sources.config.ts`, fetches per menu item from
   manual/Joomla/WordPress, downloads images locally, writes `public/data/*`.
3. `npm run build:data` → validates the schema, generates `version.json` (hashes).
4. `npm run build` → Vite build into `dist/`.
5. Upload `dist/` via SFTP to the subdomain docroot (`app.rockimdorf.at`). **HTTPS mandatory.**
6. `.htaccess` (Apache): SPA fallback + headers.

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]

<FilesMatch "\.(js|css|webp|woff2)$">
  Header set Cache-Control "public, max-age=31536000, immutable"
</FilesMatch>
<FilesMatch "version\.json$">
  Header set Cache-Control "no-cache"
</FilesMatch>
<FilesMatch "^(?!version).*\.json$">
  Header set Cache-Control "public, max-age=120"
</FilesMatch>
<FilesMatch "(index\.html|sw\.js)$">
  Header set Cache-Control "no-cache"
</FilesMatch>
```

**Data update during operation:** run `import` + `build:data`, upload only the
changed `data/*.json` + `version.json`. Clients catch up (≤ 2 min). No
app-shell rebuild needed.

---

## 16. GitHub project setup & changelog

- **Repo**: start private, later public. Commit **no** credentials, no real tokens.
- **`.gitignore`**: `node_modules`, `dist`, `.env*`, (optionally) generated
  `public/data/*` (track sample files).
- **Branching**: feature-branch workflow.
- **CLAUDE.md**: German comments; confirmation required for changes to the data
  schema, dependencies, core logic (caching/timetable/favorites) and to
  `content-sources.config.ts`.
- **README.md**: setup, `import`/`build:data`/`build`, deployment, source configuration.
- **LICENSE**: **GNU AGPLv3** for the code (content/logos/maps excluded).
- **CI (optional)**: GitHub Action `build` (lint + typecheck + build).
- **Versioning**: **SemVer**; **changelog in "Keep a Changelog" format** (§18); tag per release.

---

## 17. Development roadmap (phases & effort)

> Effort = focused development time with Claude Code.

**Phase 0 — scaffold (~1 day)**
Vite+TS+Tailwind (design tokens §2), routing, AppShell+BottomNav, TanStack
Query, `vite-plugin-pwa`, manifest/icons, `version.json` 2-min polling. → `v0.1.0`.

**Phase 1 — data pipeline + content read-only (~4–5 days)**
`content-sources.config.ts`, adapters (manual/Joomla/WordPress/csv),
`import` + `build:data`, schema/types; line-up, artist pages (+Spotify embed),
info pages, sponsors, weather strip, tickets. → `v0.2.0`.

**Phase 2 — timetable & favorites (~3 days)**
Grid + list, day tabs, now line, favorites (IndexedDB), clash finder, `.ics`
reminder, my plan, now/up next; switchable timetable source (§6.5). → `v0.3.0`.

**Phase 3 — map & news & search (~2–3 days)**
Leaflet map + POIs + filter, news feed (publishAt + auto concert start +
safety), global search. → `v0.4.0`.

**Phase 4 — offline hardening & polish (~1–2 days)**
Fine-tune caching, offline indicator, install hints, exact design tokens from
the T4 CSS, icons/splash, Lighthouse PWA check. → `v1.0.0`.

**Phase 5 — web push (optional, ~2–3 days)**
VAPID, subscription backend, admin for sending. → `v1.1.0`.

**Total MVP (phases 0–4): ~11–14 days.** Biggest non-code item: **content
maintenance** (bios, photos, timetable, drawing the map) — plan early.

---

## 18. CHANGELOG template

File `CHANGELOG.md` (Keep a Changelog + SemVer):

```markdown
# Changelog

All notable changes to this project are documented here.
Format per Keep a Changelog, versioning per SemVer.

## [Unreleased]
### Added
### Changed
### Fixed

## [0.1.0] - 2026-06-XX
### Added
- Project scaffold (Vite, React, TS, Tailwind, routing, PWA setup)
- Version polling (version.json, 2-minute cycle) and caching strategy
```

---

## Change history of this document

### [1.1.0] - 2026-06-23
**Removed**
- Feature **Spotted** entirely removed (schema, route, components, backend write path, phase).
- Mandatory micro-backend removed — operation is now purely static; web push
  only as an optional expansion.

**Added**
- §6 **data source configuration**: selectable per menu item between
  `manual` / `joomla` / `wordpress` (incl. `content-sources.config.ts`, adapter
  architecture, Joomla and WordPress mapping, security).
- §6.5 **switchable timetable source**: `csv` | `joomla-customfields` | `wordpress-acf`.
- §2 **design direction** (dark/white/yellow, 4:5 artist images, tokens to be
  verified from the T4 CSS).

**Changed**
- Cache/polling unified from 60 s/5 min to **2 minutes** (`max-age=120`,
  `refetchInterval 120_000`).
- Roadmap adjusted (data pipeline in phase 1, Spotted phase removed).

### [1.0.0] - 2026-06-22
- First version.

---
