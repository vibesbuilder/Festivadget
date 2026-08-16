# Festivadget

**🇩🇪 [Deutsche Version](README.de.md)**

Installable, offline-capable **Progressive Web App** that acts as a visitor companion
for **multi-day events** – line-up, timetable, personal schedule, map, news and push
notifications in one app, without an app store.

Core principle: **fully static**. At runtime the app is purely static (any web space
will do); all content comes from versioned JSON files. Data is fetched **at build
time** from selectable sources (manual / Joomla / WordPress) and normalized to a
single schema. Reference project: ROCK IM DORF Festival
([app.rockimdorf.at](https://app.rockimdorf.at)).

## Screenshots

| Home | Timetable | Line-up |
|---|---|---|
| ![Home](docs/screenshots/home.png) | ![Timetable](docs/screenshots/timetable.png) | ![Line-up](docs/screenshots/lineup.png) |

| Map | News feed | More |
|---|---|---|
| ![Map](docs/screenshots/map.png) | ![News feed](docs/screenshots/news.png) | ![More](docs/screenshots/more.png) |

## Features

- **Home dashboard**: "Now / up next" programme block, news feed preview, sponsor
  branding, push opt-in.
- **Line-up & artist pages**: filter by day, detail pages with image, text and slots.
- **Timetable**: stage grid with time axis plus list view, stages can be hidden.
- **My plan**: mark favourites, personal schedule, overlap warnings, export as a
  calendar file (.ics).
- **Offline map**: Leaflet with preloaded tiles, POIs with freely definable
  categories and icons.
- **News feed**: editorial news (scheduled / pinned / expiring), automatic
  "on stage now" items generated from the programme, optional live news via Telegram.
- **Web push** (optional): lock-screen notifications including per-category opt-in
  for visitors – the backend is a set of lightweight PHP files on the same shared
  web space, no dedicated server required.
- **Weather** (optional): forecast + severe weather warnings with a selectable provider.
- **Global search** across artists, programme, info pages and POIs.
- **Info pages, sponsors, tickets**: freely editable info pages (Markdown), sponsor
  grid, ticket shop embedding.
- **Full PWA**: installable (with built-in Android/iOS hints), offline-capable,
  automatic updates, content refresh in production without an app rebuild (~2 min).
- **Dark/light theme** and selectable app language: **German, English, French,
  Spanish**.
- **Anonymous statistics** (optional): page views without user tracking, evaluated
  in the CMS.
- **Mini CMS** (optional, PHP): news editor with scheduling & push, POI/category
  management, statistics, weather configuration – usable from the orga team's
  phone; interface in **four languages** (de/en/fr/es, switchable under
  Settings).

## Architecture

Static PWA + optional PHP backend for push/CMS – deliberately without a permanently
running application server:

```
content/*.json + slots.csv ─┐
Joomla/WordPress REST ──────┼─► scripts/import-from-source.ts ─► public/data/*.json
                            ┘                                    │
                                       scripts/build-data.ts ────┴─► version.json (hashes)
React (TanStack Query) ◄── fetch /data/*.json ◄── version.json (2-min poll, targeted invalidation)
```

- **App**: Vite · React 18 · TypeScript · TailwindCSS · react-router-dom ·
  TanStack Query · Zustand · idb-keyval · Luxon · Leaflet · vite-plugin-pwa ·
  react-i18next · lucide-react · react-markdown.
- **Data pipeline**: source adapters (manual/Joomla/WordPress) → normalization +
  validation → `public/data/*.json` + `version.json` with content hashes. Clients
  poll `version.json` every 2 minutes and re-fetch only changed files.
- **Web push backend** (optional): PHP endpoints + MySQL + cron under
  [`push/`](push/) on the same shared-hosting web space; VAPID key pair, the public
  key is embedded into the client build. Details: [`docs/PUSH.en.md`](docs/PUSH.en.md).
- **CMS** (optional): PHP interface under `push/cms/` (news, POIs, statistics,
  weather; UI in de/en/fr/es). Details: [`docs/ADMIN.en.md`](docs/ADMIN.en.md).

Deep dives: [`IMPLEMENTATION.en.md`](IMPLEMENTATION.en.md) (concept),
[`docs/DATEN.en.md`](docs/DATEN.en.md) (content sources),
[`docs/TELEGRAM.en.md`](docs/TELEGRAM.en.md) (live news). All docs are also
available in German, French and Spanish (language links at the top of each file).

## Setup

Requirements: **Node.js ≥ 20** and **pnpm** (`corepack enable` is enough).

```bash
pnpm install

# Generate content data (sample data lives under content/)
pnpm run import        # sources → public/data/*.json (default: everything "manual")
pnpm run build:data    # validation + version.json (hashes)

pnpm run dev           # dev server (Vite)
```

More commands:

```bash
pnpm run typecheck     # TypeScript check
pnpm run build         # production build into dist/
pnpm run preview       # serve the built dist/ locally
pnpm run gen-icons     # generate PWA PNG icons from public/icons/*.svg (sharp)
```

## Configuration

### Content (`content/` + `content-sources.config.ts`)

All content is data, not code. Which domain comes from which source is controlled by
[`content-sources.config.ts`](content-sources.config.ts) – per domain `manual`
(JSON files under [`content/`](content/)), `joomla` or `wordpress`:

| File | Content |
|---|---|
| `festival.json` | name, dates, location, home texts, map bounds |
| `artists.json` | line-up (name, image, text, links) |
| `stages.json` / `slots.csv` | stages + programme (timetable) |
| `info.json` | info pages (Markdown) |
| `news.json` | editorial news |
| `pois.json` / `poi-categories.json` | map points + categories |
| `sponsors.json` | sponsor grid |

Guide including CMS integration and replacing the sample data:
[`docs/DATEN.en.md`](docs/DATEN.en.md).

### Environment variables (`.env`)

Copy the template: `cp .env.example .env`

| Variable | Purpose | Visibility |
|---|---|---|
| `JOOMLA_API_TOKEN` | Joomla API token (build-time import) | **secret** – Node script only |
| `WP_USER`, `WP_APP_PW` | WordPress application password (build-time import) | **secret** |
| `VITE_VAPID_PUBLIC_KEY` | public VAPID key for web push | public (client build) |

**Security rule:** only variables prefixed with `VITE_` end up in browser code.
Never prefix secrets (private VAPID key, CMS tokens) with `VITE_`.

### Branding

- **Colors/fonts**: CSS variables in [`src/styles/index.css`](src/styles/index.css)
  (dark and light theme), token structure in [`packages/tokens`](packages/tokens).
- **Logo & icons**: `public/icons/` (SVG sources, `pnpm run gen-icons` produces the
  PWA PNGs), header logo and background artwork under `public/`.
- **Copy**: app texts in all four languages under `src/i18n/`
  (`de`/`en`/`fr`/`es`).

## Deployment (static hosting)

1. `pnpm run import && pnpm run build:data && pnpm run build`
2. Upload `dist/` to your web space (HTTPS required, SPA fallback + cache headers
   via `.htaccess` – see `IMPLEMENTATION.md` §15).
3. **Content updates in production:** upload only the changed `data/*.json` +
   `version.json` – clients pick them up within ~2 minutes, no app rebuild needed.

Windows convenience: [`deploy-data.bat`](deploy-data.bat) builds and uploads via
FTPS (credentials once in `deploy.env.bat` from the template, gitignored) – without
arguments only content data, with `full` the complete app, with `push` the PHP
backend.

## Web push & CMS (optional)

Setting up VAPID, MySQL and cron on shared hosting: [`docs/PUSH.en.md`](docs/PUSH.en.md).
The push toggle appears in the app under **More** as soon as
`VITE_VAPID_PUBLIC_KEY` is set. Telegram live news:
[`docs/TELEGRAM.en.md`](docs/TELEGRAM.en.md).

## License

Code under the [GNU AGPLv3](LICENSE): free to use, modify and self-host; if you
run a modified version as a network service, you must publish your changes under
the same license. Content, logos, maps and trademarks of the reference project
are excluded.
