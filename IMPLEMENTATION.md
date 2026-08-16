# IMPLEMENTATION.md — ROCK IM DORF Festival App (PWA)

**🇬🇧 [English](IMPLEMENTATION.en.md) · 🇫🇷 [Français](IMPLEMENTATION.fr.md) · 🇪🇸 [Español](IMPLEMENTATION.es.md)**

> Arbeitsdokument für die Entwicklung mit Claude Code.
> Projektname (Vorschlag): `rid-festival-app`
> Ziel: installierbare, offline-fähige Progressive Web App für Festivalbesucher,
> gehostet als statische Files auf einer Subdomain (z. B. `app.rockimdorf.at`).

**Version dieses Dokuments:** 1.1.0 · **Stand:** 2026-06-23

---

## 0. Inhaltsverzeichnis

1. Projektziel & Scope
2. Design-Richtung
3. Tech-Stack
4. Architektur-Überblick
5. Datenaktualität & Caching-Strategie
6. Datenquellen-Konfiguration (pro Menüpunkt wählbar)
7. Datenmodell / JSON-Schema (normalisiertes Ziel)
8. Projekt-/Verzeichnisstruktur
9. Routing
10. Komponenten-Struktur
11. State & lokale Persistenz
12. Feature-Spezifikationen
13. PWA / Offline / Installation
14. Internationalisierung (i18n)
15. Build & Deployment (World4You)
16. GitHub-Projekt-Setup & Changelog
17. Entwicklungs-Roadmap (Phasen & Aufwand)
18. CHANGELOG-Vorlage

---

## 1. Projektziel & Scope

Eine **PWA** (kein Native, kein App-Store) als zentrale Besucher-App für das Festival.
Kernprinzip: **fully static**. Alle Inhalte kommen aus versionierten JSON-Dateien,
die vom Webserver ausgeliefert werden. Es gibt **keinen Pflicht-Backend** mehr.

Die Inhaltsdaten werden **zum Build-Zeitpunkt** aus wählbaren Quellen bezogen
(manuell / Joomla / WordPress, siehe §6) und auf ein einheitliches JSON-Schema
normalisiert. Im Betrieb ist die App rein statisch.

### In Scope (MVP + Ausbau)

| Feature | Backend im Betrieb nötig? |
|---|---|
| Line-Up + Artist-Pages | nein |
| Timetable (mehrere Ansichten) | nein |
| Favoriten / Mein Plan + `.ics`-Reminder | nein |
| Interaktive Offline-Karte mit POIs | nein |
| News-/Info-Feed mit getimter Veröffentlichung + Auto-Konzertstart | nein |
| Now / Up Next | nein |
| Suche | nein |
| Sponsoren-Bereich | nein |
| Info-Seiten (Anreise, Gelände, Camping, Caravan, Cashless, BringMichHeim, Kulinarik, Getränke, FAQ) | nein |
| Wetter (RastaWeather) | nein (liest fertiges JSON) |
| Ticketshops (iframe/Link) | nein |
| Web-Push (optionaler Ausbau) | ja (separat) |

### Out of Scope (bewusst)
- **Spotted** — entfernt (UGC + Moderation + DSGVO-Aufwand nicht gewünscht).
- Friends / Live-Standortteilen.
- Lockscreen-Widget (nur nativ möglich).
- Eigenes Cashless-/Ticketing-System (kommt von KUPF/Öticket).

---

## 2. Design-Richtung

Orientierung an rockimdorf.at (Joomla 5, T4-Template):

- **Dunkles Theme**: dunkler Grund, weiße Typografie, **gelber Akzent** (Green-Event-Gelb)
  für CTAs und aktive Zustände (favorisiert, „läuft jetzt").
- **Artist-Bilder im Hochformat 4:5** (1080×1350), großflächig/full-bleed.
- Kräftige **Display-Schrift** für Headlines, klare Sans für Fließtext.
- Tonalität: rockig, „abseits des Mainstreams", bewusst familiär/„klein & fein".

Tokens als Tailwind-Theme (Platzhalter — **exakte Hex/Fonts aus T4-CSS verifizieren**):

```css
:root {
  --rid-bg:        #121212; /* TODO: aus T4-CSS verifizieren */
  --rid-surface:   #1c1c1c;
  --rid-text:      #ffffff;
  --rid-muted:     #b3b3b3;
  --rid-accent:    #f2c200; /* Green-Event-Gelb, TODO verifizieren */
  --rid-accent-2:  #e4572e; /* sekundär (z. B. NowLine), optional */
}
```

> Vor Phase 4: exakte Farbwerte, Schriftfamilien und Logo-Assets aus dem
> Live-Template ziehen und hier eintragen.

---

## 3. Tech-Stack

- **Build:** Vite + React 18 + TypeScript
- **Styling:** TailwindCSS (Theme-Tokens aus §2)
- **Routing:** `react-router-dom` v6 (Alternative: TanStack Router)
- **Server-State / Datenabruf:** TanStack Query (`@tanstack/react-query`)
- **Client-State:** Zustand (Favoriten, UI-State)
- **Lokale Persistenz:** IndexedDB via `idb-keyval`
- **Datum/Zeit/Zeitzone:** Luxon (zwingend wegen `Europe/Vienna` + Mitternachtsüberlauf)
- **Karte:** Leaflet (`CRS.Simple`, ImageOverlay)
- **PWA / Service Worker:** `vite-plugin-pwa` (Workbox darunter)
- **i18n:** `react-i18next` (de Standard, en optional)
- **Icons:** `lucide-react`
- **Markdown-Rendering (Info/News/Bio):** `react-markdown` + `remark-gfm`
- **`.ics`-Erzeugung:** eigene Mini-Funktion (kein Paket nötig)
- **Build-Time-Import:** Node-Skripte + `papaparse` (CSV), `node-fetch`/`undici` (REST)

> Hinweis: **FullCalendar bewusst NICHT** für die Timetable. Der Stage×Zeit-Raster lässt sich
> mit CSS Grid sauberer und mobil-tauglicher abbilden.

---

## 4. Architektur-Überblick

```
                    Browser (PWA)
   ┌──────────────────────────────────────────────┐
   │  React-App (App-Shell, cache-first)           │
   │   - TanStack Query (Daten)                    │
   │   - Zustand (Favoriten) -> IndexedDB          │
   │   - Service Worker (Workbox)                  │
   └───────────────┬──────────────────────────────┘
                   │  HTTPS GET (statisch)
                   ▼
   ┌──────────────────────────────────────────────┐
   │  Statischer Webserver (World4You)             │
   │   /            -> App-Shell (Build-Artefakte) │
   │   /data/*.json -> Inhaltsdaten (versioniert)  │
   │   /data/version.json -> Hashes (no-cache)     │
   │   /map/*.webp  -> Geländeplan-Bild            │
   └──────────────────────────────────────────────┘

   ── Build-Time (nicht im Betrieb) ──────────────────
   scripts/import-from-source.ts liest content-sources.config.ts
   und holt je Menüpunkt aus: manual | Joomla-API | WordPress-API
   -> normalisiert -> public/data/*.json + version.json

   ── optional (eigener VPS) ──────────────────────────
   Web-Push (VAPID) — nur falls echte Push-Reminder gewünscht.
```

Der Betrieb ist **rein statisch**. Quellenanbindung passiert ausschließlich beim Build.

---

## 5. Datenaktualität & Caching-Strategie

Ziel: online ~live (**≤ 2 min**), offline letzter bekannter Stand.

### 5.1 Dateiklassen & HTTP-Header

| Pfad | Cache-Control | SW-Strategie |
|---|---|---|
| App-Shell (`/assets/*` mit Hash) | `max-age=31536000, immutable` | CacheFirst (precache) |
| `index.html` | `no-cache` | NetworkFirst |
| `/data/*.json` (Inhalte) | `max-age=120` | NetworkFirst, Timeout 3 s, Fallback Cache |
| `/data/version.json` | `no-cache` | NetworkOnly (mit Cache-Fallback offline) |
| `/map/*.webp` | `max-age=86400` | StaleWhileRevalidate |

### 5.2 Versions-Polling (near-live, 2-Minuten-Takt)

`version.json` enthält pro Datensatz einen Content-Hash:

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

- TanStack Query lädt `version.json` mit `refetchInterval: 120_000` (**2 min**), nur wenn
  `document.visibilityState === 'visible'` und online.
- Bei Hash-Änderung eines Datensatzes wird **nur dessen Query invalidiert** → gezielter Refetch.
- `version.json` wird beim Daten-Build (§15) automatisch neu erzeugt.

> Inhaltsänderung auf dem Server → sichtbar bei allen Online-Clients innerhalb von ~2 min.

### 5.3 Offline-Verhalten
- Erstabruf cacht alle `/data/*.json` und das Karten-Bild vor.
- Bei Netzausfall liefert NetworkFirst aus dem Cache; UI zeigt „Offline / Stand: HH:MM"
  (aus `generatedAt` des letzten erfolgreichen Abrufs, in IndexedDB gehalten).

---

## 6. Datenquellen-Konfiguration (pro Menüpunkt wählbar)

**Kernanforderung:** Für **jeden Menüpunkt / jede Inhaltsdomäne** ist einzeln wählbar,
ob die Daten **manuell** gepflegt oder per **Joomla**- bzw. **WordPress-API** bezogen werden.
Die Auflösung passiert beim Build über eine zentrale Konfiguration und austauschbare Adapter.
Das Laufzeit-Schema (§7) ist quellenunabhängig — egal woher die Daten kommen, sie landen
im selben normalisierten JSON.

### 6.1 Konfigurationsdatei `content-sources.config.ts`

```ts
type Provider = "manual" | "joomla" | "wordpress";

interface JoomlaLocator {
  categoryId?: number;        // Artikel-Kategorie (z. B. Line-Up Freitag)
  ids?: number[];             // explizite Artikel-IDs
  customFields?: Record<string, string>; // Schema-Feld -> Joomla-Custom-Field-Name
}

interface WordPressLocator {
  categorySlug?: string;      // Kategorie-Slug
  postType?: string;          // "post" oder Custom Post Type
  acf?: Record<string, string>; // Schema-Feld -> ACF-Feldname
}

interface SourceBinding {
  provider: Provider;
  joomla?: JoomlaLocator;
  wordpress?: WordPressLocator;
  // provider === "manual" -> Daten kommen aus content/<domain>.json (im Repo gepflegt)
}

interface ContentSourcesConfig {
  // Verbindungs-Defaults (Tokens NUR aus ENV, nie committen):
  joomla?:    { baseUrl: string; tokenEnv: string };          // z. B. "JOOMLA_API_TOKEN"
  wordpress?: { baseUrl: string; userEnv?: string; appPwEnv?: string };

  // Pro Domäne / Menüpunkt eine Bindung:
  bindings: {
    festival: SourceBinding;
    stages:   SourceBinding;
    artists:  SourceBinding;
    slots:    SourceBinding & { format?: "csv" | "joomla-customfields" | "wordpress-acf" };
    pois:     SourceBinding;
    news:     SourceBinding;
    sponsors: SourceBinding;
    tickets:  SourceBinding;
    weather:  SourceBinding;     // i. d. R. "manual" (von RastaWeather befüllt)
    // Info-Seiten einzeln überschreibbar (jede Seite eigene Quelle):
    info: {
      default: SourceBinding;
      overrides?: Record<string, SourceBinding>; // key = InfoPage.id ("faq", "anreise", ...)
    };
  };
}
```

**Beispiel** (Artists aus Joomla, FAQ manuell, Rest gemischt):

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
    sponsors: { provider: "joomla" }, // Weblinks-Komponente, siehe 6.3
    tickets:  { provider: "manual" },
    weather:  { provider: "manual" },
    info: {
      default: { provider: "joomla", joomla: { categoryId: 8 } },
      overrides: { faq: { provider: "manual" } },
    },
  },
};
```

### 6.2 Importer-Architektur (Adapter-Pattern)

`scripts/import-from-source.ts` iteriert über `bindings`, ruft je `provider` den passenden
Adapter, normalisiert auf das Schema (§7), lädt referenzierte Bilder lokal herunter
(`public/img/...`, wegen Offline + Same-Origin) und schreibt `public/data/<domain>.json`.
Danach werden `version.json`-Hashes neu berechnet.

```
scripts/
├─ import-from-source.ts        # Orchestrierung, liest config + ENV
├─ build-data.ts                # Validierung (Schema) + version.json (Hashes)
└─ adapters/
   ├─ manual.ts                 # liest content/<domain>.json, validiert
   ├─ joomla.ts                 # Joomla Web-Services REST API
   ├─ wordpress.ts              # WordPress REST API (+ ACF)
   └─ csv.ts                    # parst content/slots.csv (papaparse)
```

Jeder Adapter implementiert dasselbe Interface:

```ts
interface SourceAdapter {
  fetchDomain(domain: string, binding: SourceBinding, cfg: ContentSourcesConfig): Promise<unknown[]>;
}
```

### 6.3 Joomla-Adapter

- **Artikel** (Artists, News, Info): `GET {baseUrl}/api/index.php/v1/content/articles?filter[category]={id}`
  mit Header `Authorization: Bearer {JOOMLA_API_TOKEN}`. Einzelartikel über `/articles/{id}`.
- **Custom Fields**: in der API-Antwort enthalten (com_fields) bzw. per Feldparameter anfordern;
  Mapping über `joomla.customFields`.
- **Sponsoren (Weblinks)**: Web-Services-Endpunkt der Weblinks-Komponente, falls Plugin aktiv;
  Fallback = RSS-Feed der jeweiligen Weblinks-Kategorie (presented by / powered by / Partner).
- HTML-Body der Artikel **sanitizen** und nach Markdown konvertieren (oder bereinigtes HTML).

### 6.4 WordPress-Adapter

- **Posts/CPT**: `GET {baseUrl}/wp-json/wp/v2/{postType}?categories={id}` (Auth via
  Application Password, Basic-Auth über `WP_USER`/`WP_APP_PW`).
- **ACF** (Pendant zu Joomla Custom Fields): Felder im REST-Response, wenn „Show in REST"
  aktiv ist bzw. via ACF-to-REST; Mapping über `wordpress.acf`.
- Bilder via `_embed`/Media-Endpoint auflösen, lokal herunterladen.

### 6.5 Timetable-Quelle (umschaltbar)

`slots.format` bestimmt, woher Bühne + Start/Ende kommen:

| `format` | Quelle | Felder |
|---|---|---|
| `csv` | `content/slots.csv` | `artistSlug,stageId,dayId,start,end,note` |
| `joomla-customfields` | Custom Fields der Artist-Artikel | gemäß `joomla.customFields` |
| `wordpress-acf` | ACF-Felder der Artist-Posts | gemäß `wordpress.acf` |

Bei `csv` werden Slots über den `artistSlug` mit den (ggf. anderswoher bezogenen) Artists
gejoint. Bei den Custom-Field-Varianten werden Slots direkt aus den Artist-Datensätzen abgeleitet.

`content/slots.csv` (Beispiel):

```csv
artistSlug,stageId,dayId,start,end,note
bibiza,main,sa,2026-08-01T22:00:00+02:00,2026-08-01T23:30:00+02:00,
greeen,main,fr,2026-07-31T21:30:00+02:00,2026-07-31T23:00:00+02:00,
paula-carolina,second,fr,2026-07-31T19:30:00+02:00,2026-07-31T20:30:00+02:00,
```

### 6.6 Sicherheit der Anbindung

- API-Tokens / App-Passwörter **ausschließlich in `.env`** (in `.gitignore`), nie committen.
- Joomla-Token **read-only**, gescoped; Build-Maschine möglichst per IP freischalten.
- Da der Import **server-seitig** zur Build-Zeit läuft, gelangen Credentials **nie in den Browser**.
- HTML aus CMS-Quellen vor dem Speichern sanitizen.
- Bilder lokal kopieren statt hotlinken (Offline-Cache + keine CORS-Probleme).

---

## 7. Datenmodell / JSON-Schema (normalisiertes Ziel)

Alle Dateien liegen unter `/data/`. TypeScript-Typen unter `src/types/`.
IDs sind kurze, stabile Strings (slug-artig). Zeitstempel **immer ISO 8601 mit Offset** (`+02:00`).
Dies ist das **quellenunabhängige Ziel** — Adapter (§6) müssen hierauf mappen.

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
  label: string;     // "Freitag 31.07."
  dayStart: string;  // logischer Tagesbeginn
  dayEnd: string;    // logisches Tagesende (Mitternachtsüberlauf!)
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
  x: number; y: number;   // Pixelkoordinaten im CRS.Simple-System
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
  publishAt: string;      // Client zeigt erst ab diesem Zeitpunkt
  expiresAt?: string; pinned?: boolean;
  image?: string; link?: { label: string; url: string };
}
```
> **Auto-Konzertstart-Einträge** werden zur Laufzeit aus `slots.json` erzeugt (siehe §12.5)
> und mit den redaktionellen News gemerged.

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

### 7.11 `weather.json` (von RastaWeather befüllt)
```ts
interface WeatherDay {
  dayId: string; date: string; tempMin: number; tempMax: number;
  symbolCode: string; precipitationProb?: number; summary?: string;
}
interface Weather { generatedAt: string; source: "open-meteo" | "geosphere"; days: WeatherDay[]; }
```

---

## 8. Projekt-/Verzeichnisstruktur

```
rid-festival-app/
├─ public/
│  ├─ data/                 # erzeugt durch import + build-data
│  │  ├─ festival.json stages.json artists.json slots.json
│  │  ├─ pois.json map.json news.json sponsors.json
│  │  ├─ info.json tickets.json weather.json
│  │  └─ version.json       # generiert (Hashes)
│  ├─ map/gelaendeplan.webp
│  ├─ img/{artists,sponsors}/...   # vom Importer lokal abgelegt
│  ├─ icons/                # PWA-Icons (192,512,maskable)
│  └─ manifest.webmanifest
├─ content/                 # manuell gepflegte Quellen (provider:"manual")
│  ├─ festival.json stages.json pois.json tickets.json weather.json ...
│  └─ slots.csv             # falls slots.format === "csv"
├─ content-sources.config.ts  # §6: Quelle pro Menüpunkt
├─ .env.example             # JOOMLA_API_TOKEN, WP_USER, WP_APP_PW (Beispielwerte)
├─ scripts/
│  ├─ import-from-source.ts
│  ├─ build-data.ts
│  └─ adapters/{manual,joomla,wordpress,csv}.ts
├─ src/
│  ├─ main.tsx App.tsx
│  ├─ routes/               # Seiten (§9)
│  ├─ components/           # (§10)
│  ├─ features/{timetable,favorites,map,news}/
│  ├─ data/                 # Query-Hooks (useArtists, useSlots, useVersion, ...)
│  ├─ lib/                  # ics.ts time.ts search.ts sw-register.ts
│  ├─ store/                # Zustand-Stores
│  ├─ types/                # Schema-Typen aus §7
│  ├─ i18n/                 # de.json en.json config
│  └─ styles/
├─ CLAUDE.md CHANGELOG.md README.md LICENSE .gitignore
├─ vite.config.ts tailwind.config.ts package.json
```

> Optionales `backend/` (FastAPI) nur, falls Web-Push (§13) tatsächlich gebaut wird.

---

## 9. Routing

Mobile-first, untere Tab-Bar für die Hauptbereiche.

| Pfad | Seite | Inhalt |
|---|---|---|
| `/` | Home | Now/Up Next, gepinnte News, nächster Favorit, Wetter-Teaser |
| `/lineup` | Line-Up | Artist-Grid, Genre-Filter, Headliner zuerst |
| `/artist/:slug` | Artist-Page | Bio, Spotify-Embed, Spielzeiten, Favorisieren |
| `/timetable` | Timetable | Grid-/Listen-Ansicht, Tag-Tabs, Clash-Marker, Now-Linie |
| `/favorites` | Mein Plan | favorisierte Slots, `.ics`-Export, Clash-Hinweis |
| `/map` | Karte | Leaflet, POI-Filter, Detail-Sheet |
| `/news` | News & Infos | gemergter Feed (redaktionell + Auto-Konzertstart), Safety oben |
| `/info` + `/info/:id` | Infos | Übersicht + Markdown-Detail |
| `/sponsors` | Sponsoren | nach Tier gruppiert |
| `/tickets` | Tickets | iframe/Link je Provider |
| `/search` | Suche | global (Artists/Slots/Info/POIs) |

Tab-Bar (5 Slots): **Home · Line-Up · Timetable · Karte · Mehr**.
„Mehr" öffnet ein Sheet: Mein Plan, News, Infos, Sponsoren, Tickets, Suche, Sprache.

---

## 10. Komponenten-Struktur

```
App
├─ AppShell (TopBar, <Outlet/>, BottomNav, OfflineBadge)
├─ data/  useVersion() (2-min-Poll) · useFestival/useStages/useArtists/useSlots/usePois/...
├─ features/timetable/  TimetableGrid · TimetableList · DayTabs · SlotCard · NowLine · useClashes()
├─ features/favorites/  FavoriteButton · useFavorites() · IcsButton
├─ features/map/        FestivalMap (CRS.Simple) · PoiMarker · PoiFilterBar · PoiSheet
├─ features/news/       NewsFeed (merge redaktionell+auto, publishAt-Filter) · NewsItemCard · SafetyBanner
└─ components/  ArtistCard ArtistGrid GenreFilter SpotifyEmbed SponsorGrid
                InfoList InfoPage NowNextWidget SearchOverlay WeatherStrip
                TicketEmbed InstallHint
```

---

## 11. State & lokale Persistenz

- **Server-State** (alle JSON): TanStack Query, `staleTime` 2 min, durch Versions-Polling invalidiert.
- **Favoriten**: Zustand-Store, persistiert in IndexedDB (`idb-keyval`, Key `favorites`) als `Set<slotId>`.
- **UI-State** (Tag, Filter, Sprache): Zustand + `localStorage`.
- **Letzter Datenstand** (`generatedAt`): IndexedDB, für Offline-Anzeige.

`.ics`-Erzeugung (`src/lib/ics.ts`): VEVENT mit VALARM (`-PT15M`); funktioniert iOS + Android.
Reminder-UX: Stern = Favorit; Button „Erinnerung (.ics)" lädt Termin mit 15-Min-Vorlauf.

---

## 12. Feature-Spezifikationen

### 12.1 Line-Up + Artist-Pages
Grid aus `ArtistCard` (Headliner zuerst, sonst alphabetisch), Genre-Filter (Chips).
Artist-Page: Header (Bild 4:5, Name, Genre, Land), Bio (Markdown), `SpotifyEmbed`,
Spielzeiten aus `slots`, `FavoriteButton`.

### 12.2 Timetable (mehrere Ansichten)
- **Grid**: CSS Grid, Spalten = Stages (nach `order`, Farbe `stage.color`), Reihen = Zeitachse.
- **Liste**: chronologisch je Tag, Filter „nur Favoriten".
- **DayTabs** nach `FestivalDay`; Mitternachtsüberlauf über `dayStart/dayEnd` (Luxon).
- **NowLine**: aktuelle Zeit; **Clash-Indikator** via `useClashes()` über favorisierte Slots.
- Datenquelle der Slots gemäß §6.5 (csv / joomla-customfields / wordpress-acf).

### 12.3 Favoriten / Mein Plan + `.ics`
Stern an Slot/Artist; „Mein Plan" zeigt Favoriten chronologisch mit Clash-Warnung; `.ics` einzeln oder „alle".

### 12.4 Interaktive Offline-Karte
Leaflet `L.CRS.Simple` + `L.imageOverlay` (bounds aus `map.json`). POI-Marker je `type`,
Filterleiste, `PoiSheet` mit Detail. Bild als `.webp` vorgecacht → vollständig offline.
GPS-Eigenposition vorerst weglassen.

### 12.5 News-Feed (getimt) + Auto-Konzertstart
Redaktionelle Items nur sichtbar wenn `publishAt <= now` (und `expiresAt > now`).
Auto-Konzertstart: pro Slot virtuelles Item `{category:"lineup", title:"Jetzt: <Artist> @ <Stage>", time:slot.start}`,
sichtbar ab `start <= now`. Beide mergen, absteigend nach Zeit, `pinned`/`safety` oben.
Safety prominent (Banner). Getimte Vorab-Pflege über zukünftiges `publishAt`.

### 12.6 Now / Up Next
`NowNextWidget` auf Home: pro Stage „läuft gerade" + „als nächstes" aus `slots` + `now`.

### 12.7 Suche
Clientseitiger Index über Artists/Slots/Info/POIs; Substring-/Token-Match (optional `match-sorter`).

### 12.8 Sponsoren
`SponsorGrid` gruppiert nach `tier`; Logo verlinkt auf `url`.

### 12.9 Info-Seiten
Markdown-Render; FAQ als `## Frage` + Antwort (optional Accordion).
Quelle je Seite einzeln konfigurierbar (§6.1, `info.overrides`).

### 12.10 Wetter
`WeatherStrip` liest `weather.json` (RastaWeather). Pro Tag Symbol + Min/Max.

### 12.11 Ticketshops
`tickets.json` steuert `embedType`. **iframe** mit `sandbox` + `allow`-Liste; **Fallback „link"**,
falls Shop Framing per `X-Frame-Options`/CSP verbietet (KUPF/Öticket prüfen).

---

## 13. PWA / Offline / Installation

- `vite-plugin-pwa`. Precache App-Shell; Runtime-Caching gemäß §5.1.
- `manifest.webmanifest`: Name, Short-Name, Theme-/Background-Color (dunkel/gelb), Icons (192/512 + maskable),
  `display:"standalone"`, `start_url:"/"`, `scope:"/"`.
- Installations-Hinweise: Android/Chrome `beforeinstallprompt`-Button; iOS `InstallHint`
  („Teilen → Zum Home-Bildschirm").
- **Web-Push** bleibt optionaler Ausbau (VAPID, separater Backend, iOS nur nach Home-Bildschirm-Install).
  Für MVP nicht nötig — Reminder laufen über `.ics`.

---

## 14. Internationalisierung (i18n)

`react-i18next`, Standard **de**, optional **en**. UI-Strings in `src/i18n/{de,en}.json`.
Inhaltsdaten einsprachig (de); optionale `*_en`-Felder später möglich, nicht im MVP.

---

## 15. Build & Deployment (World4You)

1. `.env` mit Credentials anlegen (aus `.env.example`).
2. `npm run import` → `import-from-source.ts` liest `content-sources.config.ts`, holt je Menüpunkt
   aus manual/Joomla/WordPress, lädt Bilder lokal, schreibt `public/data/*`.
3. `npm run build:data` → validiert Schema, erzeugt `version.json` (Hashes).
4. `npm run build` → Vite-Build nach `dist/`.
5. Upload `dist/` per SFTP auf Subdomain-Docroot (`app.rockimdorf.at`). **HTTPS Pflicht.**
6. `.htaccess` (Apache): SPA-Fallback + Header.

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

**Daten-Update im Betrieb:** `import` + `build:data` laufen lassen, nur geänderte `data/*.json`
+ `version.json` hochladen. Clients ziehen nach (≤ 2 min). Kein App-Shell-Rebuild nötig.

---

## 16. GitHub-Projekt-Setup & Changelog

- **Repo**: privat starten, später public. **Keine** Credentials, keine echten Tokens committen.
- **`.gitignore`**: `node_modules`, `dist`, `.env*`, (optional) generierte `public/data/*` (Beispiel-Files tracken).
- **Branching**: Feature-Branch-Workflow (wie RASTAMAN).
- **CLAUDE.md**: deutsche Kommentare; Bestätigung erforderlich bei Änderungen an
  Datenschema, Dependencies, Kernlogik (Caching/Timetable/Favoriten) und an `content-sources.config.ts`.
- **README.md**: Setup, `import`/`build:data`/`build`, Deployment, Quellen-Konfiguration.
- **LICENSE**: **GNU AGPLv3** für den Code (Inhalte/Logos/Karten ausgenommen).
- **CI (optional)**: GitHub Action `build` (lint + typecheck + build).
- **Versionierung**: **SemVer**; **Changelog im „Keep a Changelog"-Format** (§18); Tag pro Release.

---

## 17. Entwicklungs-Roadmap (Phasen & Aufwand)

> Aufwand = konzentrierte Entwicklungszeit mit Claude Code.

**Phase 0 — Gerüst (~1 Tag)**
Vite+TS+Tailwind (Design-Tokens §2), Routing, AppShell+BottomNav, TanStack Query,
`vite-plugin-pwa`, Manifest/Icons, `version.json`-2-min-Polling. → `v0.1.0`.

**Phase 1 — Datenpipeline + Inhalte read-only (~4–5 Tage)**
`content-sources.config.ts`, Adapter (manual/Joomla/WordPress/csv), `import` + `build:data`,
Schema/Typen; Line-Up, Artist-Pages (+Spotify-Embed), Info-Seiten, Sponsoren, Wetter-Strip, Tickets. → `v0.2.0`.

**Phase 2 — Timetable & Favoriten (~3 Tage)**
Grid + Liste, DayTabs, NowLine, Favoriten (IndexedDB), Clash-Finder, `.ics`-Reminder,
Mein Plan, Now/Up Next; Timetable-Quelle umschaltbar (§6.5). → `v0.3.0`.

**Phase 3 — Karte & News & Suche (~2–3 Tage)**
Leaflet-Karte + POIs + Filter, News-Feed (publishAt + Auto-Konzertstart + Safety), globale Suche. → `v0.4.0`.

**Phase 4 — Offline-Härtung & Polish (~1–2 Tage)**
Caching feinjustieren, Offline-Indikator, Install-Hints, exakte Design-Tokens aus T4-CSS,
Icons/Splash, Lighthouse-PWA-Check. → `v1.0.0`.

**Phase 5 — Web-Push (optional, ~2–3 Tage)**
VAPID, Subscription-Backend, Admin zum Senden. → `v1.1.0`.

**Summe MVP (Phase 0–4): ~11–14 Tage.** Größter Nicht-Code-Posten: **Content-Pflege**
(Bios, Fotos, Timetable, Karte zeichnen) — früh einplanen.

---

## 18. CHANGELOG-Vorlage

Datei `CHANGELOG.md` (Keep a Changelog + SemVer):

```markdown
# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden hier dokumentiert.
Format nach Keep a Changelog, Versionierung nach SemVer.

## [Unreleased]
### Added
### Changed
### Fixed

## [0.1.0] - 2026-06-XX
### Added
- Projektgerüst (Vite, React, TS, Tailwind, Routing, PWA-Setup)
- Versions-Polling (version.json, 2-Minuten-Takt) und Caching-Strategie
```

---

## Änderungshistorie dieses Dokuments

### [1.1.0] - 2026-06-23
**Removed**
- Feature **Spotted** vollständig entfernt (Schema, Route, Komponenten, Backend-Schreibpfad, Phase).
- Pflicht-Micro-Backend entfernt — Betrieb ist nun rein statisch; Web-Push nur noch optionaler Ausbau.

**Added**
- §6 **Datenquellen-Konfiguration**: pro Menüpunkt wählbar zwischen `manual` / `joomla` / `wordpress`
  (inkl. `content-sources.config.ts`, Adapter-Architektur, Joomla- und WordPress-Mapping, Sicherheit).
- §6.5 **Timetable-Quelle umschaltbar**: `csv` | `joomla-customfields` | `wordpress-acf`.
- §2 **Design-Richtung** (dunkel/weiß/gelb, 4:5-Artist-Bilder, Tokens aus T4-CSS zu verifizieren).

**Changed**
- Cache/Polling von 60 s/5 min auf **2 Minuten** vereinheitlicht (`max-age=120`, `refetchInterval 120_000`).
- Roadmap angepasst (Datenpipeline in Phase 1, Spotted-Phase entfernt).

### [1.0.0] - 2026-06-22
- Erstfassung.

---

*Ende IMPLEMENTATION.md*
