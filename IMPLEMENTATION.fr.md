# IMPLEMENTATION.de.md — ROCK IM DORF Festival App (PWA)

**🇩🇪 [Deutsch](IMPLEMENTATION.de.md) · 🇬🇧 [English](IMPLEMENTATION.md) · 🇪🇸 [Español](IMPLEMENTATION.es.md)**

> Document de travail pour le développement avec Claude Code.
> Nom de projet (proposition) : `rid-festival-app`
> Objectif : progressive web app installable et utilisable hors-ligne pour les
> visiteurs du festival, hébergée en fichiers statiques sur un sous-domaine
> (p. ex. `demo.festivadget.com`).

**Version de ce document :** 1.1.0 · **État :** 2026-06-23

---

## 0. Sommaire

1. Objectif & périmètre du projet
2. Direction de design
3. Stack technique
4. Vue d'ensemble de l'architecture
5. Fraîcheur des données & stratégie de cache
6. Configuration des sources de données (au choix par élément de menu)
7. Modèle de données / schéma JSON (cible normalisée)
8. Structure du projet/des répertoires
9. Routage
10. Structure des composants
11. State & persistance locale
12. Spécifications des fonctionnalités
13. PWA / hors-ligne / installation
14. Internationalisation (i18n)
15. Build & déploiement (World4You)
16. Setup du projet GitHub & changelog
17. Feuille de route de développement (phases & effort)
18. Modèle de CHANGELOG

---

## 1. Objectif & périmètre du projet

Une **PWA** (pas de natif, pas d'app store) comme app centrale des visiteurs du
festival. Principe de base : **fully static**. Tous les contenus viennent de
fichiers JSON versionnés servis par le serveur web. Il n'y a **plus de backend
obligatoire**.

Les données de contenu sont récupérées **au moment du build** depuis des
sources au choix (manuel / Joomla / WordPress, voir §6) et normalisées vers un
schéma JSON unique. En fonctionnement, l'app est purement statique.

### Dans le périmètre (MVP + extensions)

| Fonctionnalité | Backend nécessaire en fonctionnement ? |
|---|---|
| Line-up + pages artistes | non |
| Programme (plusieurs vues) | non |
| Favoris / mon planning + rappel `.ics` | non |
| Carte interactive hors-ligne avec POI | non |
| Fil d'actus/infos avec publication programmée + début de concert auto | non |
| Now / up next | non |
| Recherche | non |
| Section sponsors | non |
| Pages d'infos (accès, site, camping, caravane, cashless, retour, cuisine, boissons, FAQ) | non |
| Météo | non (lit un JSON préparé) |
| Billetteries (iframe/lien) | non |
| Web push (extension optionnelle) | oui (séparé) |

### Hors périmètre (volontairement)
- **Spotted** — supprimé (UGC + modération + effort RGPD non souhaités).
- Amis / partage de position en direct.
- Widget d'écran de verrouillage (natif seulement).
- Système cashless/billetterie propre (fourni par des prestataires externes).

---

## 2. Direction de design

Orientation sur rockimdorf.at (Joomla 5, template T4) :

- **Thème sombre** : fond sombre, typographie blanche, **accent jaune** (jaune
  green-event) pour les CTA et états actifs (favori, « joue maintenant »).
- **Images d'artistes en portrait 4:5** (1080×1350), pleine surface/full-bleed.
- **Police display** marquée pour les titres, sans claire pour le texte courant.
- Tonalité : rock, « hors du mainstream », volontairement familière/« petit & soigné ».

Tokens comme thème Tailwind (placeholders — **vérifier hex/polices exacts
depuis le CSS T4**) :

```css
:root {
  --rid-bg:        #121212; /* TODO : vérifier depuis le CSS T4 */
  --rid-surface:   #1c1c1c;
  --rid-text:      #ffffff;
  --rid-muted:     #b3b3b3;
  --rid-accent:    #f2c200; /* jaune green-event, TODO vérifier */
  --rid-accent-2:  #e4572e; /* secondaire (p. ex. NowLine), optionnel */
}
```

> Avant la phase 4 : extraire les valeurs de couleurs exactes, familles de
> polices et assets de logo du template live et les consigner ici.

---

## 3. Stack technique

- **Build :** Vite + React 18 + TypeScript
- **Styles :** TailwindCSS (tokens de thème du §2)
- **Routage :** `react-router-dom` v6 (alternative : TanStack Router)
- **State serveur / récupération de données :** TanStack Query (`@tanstack/react-query`)
- **State client :** Zustand (favoris, state UI)
- **Persistance locale :** IndexedDB via `idb-keyval`
- **Date/heure/fuseau :** Luxon (indispensable à cause de `Europe/Vienna` + débordement de minuit)
- **Carte :** Leaflet (`CRS.Simple`, ImageOverlay)
- **PWA / service worker :** `vite-plugin-pwa` (Workbox en dessous)
- **i18n :** `react-i18next` (de par défaut, en optionnel)
- **Icônes :** `lucide-react`
- **Rendu Markdown (infos/actus/bio) :** `react-markdown` + `remark-gfm`
- **Génération `.ics` :** mini-fonction maison (pas de paquet nécessaire)
- **Import au build :** scripts Node + `papaparse` (CSV), `node-fetch`/`undici` (REST)

> Remarque : **volontairement PAS FullCalendar** pour le programme. La grille
> scène×temps se réalise plus proprement et plus adaptée au mobile avec CSS Grid.

---

## 4. Vue d'ensemble de l'architecture

```
                    Navigateur (PWA)
   ┌──────────────────────────────────────────────┐
   │  App React (app shell, cache-first)           │
   │   - TanStack Query (données)                  │
   │   - Zustand (favoris) -> IndexedDB            │
   │   - Service worker (Workbox)                  │
   └───────────────┬──────────────────────────────┘
                   │  HTTPS GET (statique)
                   ▼
   ┌──────────────────────────────────────────────┐
   │  Serveur web statique (World4You)             │
   │   /            -> app shell (artefacts build) │
   │   /data/*.json -> données de contenu (versionnées) │
   │   /data/version.json -> hashes (no-cache)     │
   │   /map/*.webp  -> image du plan du site       │
   └──────────────────────────────────────────────┘

   ── au build (pas en fonctionnement) ──────────────
   scripts/import-from-source.ts lit content-sources.config.ts
   et récupère par élément de menu depuis : manual | API Joomla | API WordPress
   -> normalise -> public/data/*.json + version.json

   ── optionnel (VPS propre) ──────────────────────────
   Web push (VAPID) — seulement si de vrais rappels push sont souhaités.
```

Le fonctionnement est **purement statique**. La connexion aux sources se fait
exclusivement au build.

---

## 5. Fraîcheur des données & stratégie de cache

Objectif : en ligne ~temps réel (**≤ 2 min**), hors-ligne le dernier état connu.

### 5.1 Classes de fichiers & en-têtes HTTP

| Chemin | Cache-Control | Stratégie SW |
|---|---|---|
| App shell (`/assets/*` avec hash) | `max-age=31536000, immutable` | CacheFirst (precache) |
| `index.html` | `no-cache` | NetworkFirst |
| `/data/*.json` (contenus) | `max-age=120` | NetworkFirst, timeout 3 s, repli cache |
| `/data/version.json` | `no-cache` | NetworkOnly (avec repli cache hors-ligne) |
| `/map/*.webp` | `max-age=86400` | StaleWhileRevalidate |

### 5.2 Polling de version (near-live, cycle de 2 minutes)

`version.json` contient un hash de contenu par jeu de données :

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

- TanStack Query charge `version.json` avec `refetchInterval: 120_000`
  (**2 min**), seulement si `document.visibilityState === 'visible'` et en ligne.
- Si le hash d'un jeu de données change, **seule sa query est invalidée** → refetch ciblé.
- `version.json` est régénéré automatiquement au build des données (§15).

> Changement de contenu sur le serveur → visible pour tous les clients en ligne
> en ~2 min.

### 5.3 Comportement hors-ligne
- Le premier chargement pré-cache tous les `/data/*.json` et l'image de la carte.
- En cas de coupure réseau, NetworkFirst sert depuis le cache ; l'UI affiche
  « Hors-ligne / état : HH:MM » (depuis `generatedAt` du dernier chargement
  réussi, gardé en IndexedDB).

---

## 6. Configuration des sources de données (au choix par élément de menu)

**Exigence centrale :** pour **chaque élément de menu / domaine de contenu**,
on choisit individuellement si les données sont gérées **manuellement** ou
récupérées via l'API **Joomla** ou **WordPress**. La résolution se fait au
build via une configuration centrale et des adaptateurs interchangeables. Le
schéma d'exécution (§7) est indépendant de la source — d'où que viennent les
données, elles atterrissent dans le même JSON normalisé.

### 6.1 Fichier de configuration `content-sources.config.ts`

```ts
type Provider = "manual" | "joomla" | "wordpress";

interface JoomlaLocator {
  categoryId?: number;        // catégorie d'articles (p. ex. line-up vendredi)
  ids?: number[];             // IDs d'articles explicites
  customFields?: Record<string, string>; // champ du schéma -> nom du custom field Joomla
}

interface WordPressLocator {
  categorySlug?: string;      // slug de catégorie
  postType?: string;          // "post" ou custom post type
  acf?: Record<string, string>; // champ du schéma -> nom du champ ACF
}

interface SourceBinding {
  provider: Provider;
  joomla?: JoomlaLocator;
  wordpress?: WordPressLocator;
  // provider === "manual" -> les données viennent de content/<domaine>.json (géré dans le dépôt)
}

interface ContentSourcesConfig {
  // défauts de connexion (tokens UNIQUEMENT depuis l'ENV, ne jamais commiter) :
  joomla?:    { baseUrl: string; tokenEnv: string };          // p. ex. "JOOMLA_API_TOKEN"
  wordpress?: { baseUrl: string; userEnv?: string; appPwEnv?: string };

  // une liaison par domaine / élément de menu :
  bindings: {
    festival: SourceBinding;
    stages:   SourceBinding;
    artists:  SourceBinding;
    slots:    SourceBinding & { format?: "csv" | "joomla-customfields" | "wordpress-acf" };
    pois:     SourceBinding;
    news:     SourceBinding;
    sponsors: SourceBinding;
    tickets:  SourceBinding;
    weather:  SourceBinding;     // en général "manual"
    // pages d'infos surchargées individuellement (chaque page sa source) :
    info: {
      default: SourceBinding;
      overrides?: Record<string, SourceBinding>; // clé = InfoPage.id ("faq", "anreise", ...)
    };
  };
}
```

**Exemple** (artistes depuis Joomla, FAQ manuelle, reste mixte) :

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
    sponsors: { provider: "joomla" }, // composant weblinks, voir 6.3
    tickets:  { provider: "manual" },
    weather:  { provider: "manual" },
    info: {
      default: { provider: "joomla", joomla: { categoryId: 8 } },
      overrides: { faq: { provider: "manual" } },
    },
  },
};
```

### 6.2 Architecture de l'importeur (pattern adaptateur)

`scripts/import-from-source.ts` itère sur `bindings`, appelle l'adaptateur
correspondant selon `provider`, normalise vers le schéma (§7), télécharge
localement les images référencées (`public/img/...`, à cause du hors-ligne +
same-origin) et écrit `public/data/<domaine>.json`. Ensuite, les hashes de
`version.json` sont recalculés.

```
scripts/
├─ import-from-source.ts        # orchestration, lit config + ENV
├─ build-data.ts                # validation (schéma) + version.json (hashes)
└─ adapters/
   ├─ manual.ts                 # lit content/<domaine>.json, valide
   ├─ joomla.ts                 # API REST des web services Joomla
   ├─ wordpress.ts              # API REST WordPress (+ ACF)
   └─ csv.ts                    # parse content/slots.csv (papaparse)
```

Chaque adaptateur implémente la même interface :

```ts
interface SourceAdapter {
  fetchDomain(domain: string, binding: SourceBinding, cfg: ContentSourcesConfig): Promise<unknown[]>;
}
```

### 6.3 Adaptateur Joomla

- **Articles** (artistes, actus, infos) : `GET {baseUrl}/api/index.php/v1/content/articles?filter[category]={id}`
  avec l'en-tête `Authorization: Bearer {JOOMLA_API_TOKEN}`. Article unique via `/articles/{id}`.
- **Custom fields** : contenus dans la réponse de l'API (com_fields) ou à
  demander via paramètres de champs ; mapping via `joomla.customFields`.
- **Sponsors (weblinks)** : endpoint web-services du composant weblinks si le
  plugin est actif ; repli = flux RSS de la catégorie weblinks concernée
  (presented by / powered by / partenaires).
- **Assainir** le corps HTML des articles et le convertir en Markdown (ou HTML
  nettoyé).

### 6.4 Adaptateur WordPress

- **Posts/CPT** : `GET {baseUrl}/wp-json/wp/v2/{postType}?categories={id}`
  (auth via application password, basic auth via `WP_USER`/`WP_APP_PW`).
- **ACF** (équivalent des custom fields Joomla) : champs dans la réponse REST
  si « Show in REST » est actif ou via ACF-to-REST ; mapping via `wordpress.acf`.
- Résoudre les images via `_embed`/endpoint media, les télécharger localement.

### 6.5 Source du programme (commutable)

`slots.format` détermine d'où viennent scène + début/fin :

| `format` | Source | Champs |
|---|---|---|
| `csv` | `content/slots.csv` | `artistSlug,stageId,dayId,start,end,note` |
| `joomla-customfields` | custom fields des articles artistes | selon `joomla.customFields` |
| `wordpress-acf` | champs ACF des posts artistes | selon `wordpress.acf` |

Avec `csv`, les créneaux sont joints aux artistes (éventuellement récupérés
ailleurs) via `artistSlug`. Avec les variantes custom fields, les créneaux sont
dérivés directement des enregistrements artistes.

`content/slots.csv` (exemple) :

```csv
artistSlug,stageId,dayId,start,end,note
bibiza,main,sa,2026-08-01T22:00:00+02:00,2026-08-01T23:30:00+02:00,
greeen,main,fr,2026-07-31T21:30:00+02:00,2026-07-31T23:00:00+02:00,
paula-carolina,second,fr,2026-07-31T19:30:00+02:00,2026-07-31T20:30:00+02:00,
```

### 6.6 Sécurité de la connexion

- Tokens d'API / mots de passe d'application **exclusivement dans `.env`**
  (dans `.gitignore`), ne jamais commiter.
- Token Joomla **en lecture seule**, scopé ; si possible autoriser la machine
  de build par IP.
- Comme l'import tourne **côté serveur** au build, les identifiants
  n'atteignent **jamais le navigateur**.
- Assainir le HTML des sources CMS avant enregistrement.
- Copier les images localement au lieu de hotlinker (cache hors-ligne + pas de
  problèmes CORS).

---

## 7. Modèle de données / schéma JSON (cible normalisée)

Tous les fichiers sont sous `/data/`. Types TypeScript sous `src/types/`.
Les IDs sont des chaînes courtes et stables (façon slug). Horodatages
**toujours en ISO 8601 avec offset** (`+02:00`). C'est la **cible indépendante
de la source** — les adaptateurs (§6) doivent mapper dessus.

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
  label: LocalizedText; // "Vendredi 31.07." (LocalizedText: see §7.7)
  dayStart: string;  // début logique de la journée
  dayEnd: string;    // fin logique de la journée (débordement de minuit !)
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
  bio?: LocalizedText; genres: string[]; country?: string;
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
  id: string; type: PoiType; name: LocalizedText; description?: LocalizedText;
  x: number; y: number;   // coordonnées en pixels dans le système CRS.Simple
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
type LocalizedText = string | Partial<Record<"de" | "en" | "fr" | "es", string>>;
type NewsCategory = "info" | "safety" | "lineup" | "general";
interface NewsItem {
  id: string; title: LocalizedText; body: LocalizedText; category: NewsCategory;
  publishAt: string;      // le client n'affiche qu'à partir de ce moment
  expiresAt?: string; pinned?: boolean;
  image?: string; link?: { label: LocalizedText; url: string };
}
```
> **Contenus localisables** : `title`, `body` et `link.label` acceptent soit une
> chaîne simple (monolingue), soit une carte de langues comme `{ "de": "…", "en": "…" }`.
> L'appli résout la langue de l'utilisateur avec la chaîne de repli langue → en → de →
> première valeur disponible (`src/lib/localized.ts`) ; les textes push sont résolus
> par langue d'abonnement (`push/texts.php`).
> Les **entrées auto de début de concert** sont générées à l'exécution depuis
> `slots.json` (voir §12.5) et fusionnées avec les actus éditoriales.

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
  title: LocalizedText; icon?: string; order: number; body: LocalizedText; // Markdown
}
```

### 7.10 `tickets.json`
```ts
interface TicketProvider { id: string; name: string; embedType: "iframe" | "link"; url: string; note?: LocalizedText; }
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

## 8. Structure du projet/des répertoires

```
rid-festival-app/
├─ public/
│  ├─ data/                 # généré par import + build-data
│  │  ├─ festival.json stages.json artists.json slots.json
│  │  ├─ pois.json map.json news.json sponsors.json
│  │  ├─ info.json tickets.json weather.json
│  │  └─ version.json       # généré (hashes)
│  ├─ map/gelaendeplan.webp
│  ├─ img/{artists,sponsors}/...   # déposé localement par l'importeur
│  ├─ icons/                # icônes PWA (192,512,maskable)
│  └─ manifest.webmanifest
├─ content/                 # sources gérées manuellement (provider:"manual")
│  ├─ festival.json stages.json pois.json tickets.json weather.json ...
│  └─ slots.csv             # si slots.format === "csv"
├─ content-sources.config.ts  # §6 : source par élément de menu
├─ .env.example             # JOOMLA_API_TOKEN, WP_USER, WP_APP_PW (valeurs d'exemple)
├─ scripts/
│  ├─ import-from-source.ts
│  ├─ build-data.ts
│  └─ adapters/{manual,joomla,wordpress,csv}.ts
├─ src/
│  ├─ main.tsx App.tsx
│  ├─ routes/               # pages (§9)
│  ├─ components/           # (§10)
│  ├─ features/{timetable,favorites,map,news}/
│  ├─ data/                 # hooks de query (useArtists, useSlots, useVersion, ...)
│  ├─ lib/                  # ics.ts time.ts search.ts sw-register.ts
│  ├─ store/                # stores Zustand
│  ├─ types/                # types du schéma du §7
│  ├─ i18n/                 # de.json en.json config
│  └─ styles/
├─ CLAUDE.md CHANGELOG.md README.md LICENSE .gitignore
├─ vite.config.ts tailwind.config.ts package.json
```

> Un `backend/` optionnel (FastAPI) seulement si le web push (§13) est
> réellement construit.

---

## 9. Routage

Mobile-first, barre d'onglets basse pour les sections principales.

| Chemin | Page | Contenu |
|---|---|---|
| `/` | Accueil | now/up next, actus épinglées, prochain favori, teaser météo |
| `/lineup` | Line-up | grille d'artistes, filtre de genre, têtes d'affiche d'abord |
| `/artist/:slug` | Page artiste | bio, embed Spotify, horaires, favori |
| `/timetable` | Programme | vue grille/liste, onglets jours, marqueurs de conflit, ligne « now » |
| `/favorites` | Mon planning | créneaux favoris, export `.ics`, alerte de conflit |
| `/map` | Carte | Leaflet, filtre POI, feuille de détail |
| `/news` | Actus & infos | fil fusionné (éditorial + début de concert auto), sécurité en haut |
| `/info` + `/info/:id` | Infos | vue d'ensemble + détail Markdown |
| `/sponsors` | Sponsors | groupés par niveau |
| `/tickets` | Billets | iframe/lien par prestataire |
| `/search` | Recherche | globale (artistes/créneaux/infos/POI) |

Barre d'onglets (5 emplacements) : **Accueil · Line-up · Programme · Carte · Plus**.
« Plus » ouvre une feuille : mon planning, actus, infos, sponsors, billets,
recherche, langue.

---

## 10. Structure des composants

```
App
├─ AppShell (TopBar, <Outlet/>, BottomNav, OfflineBadge)
├─ data/  useVersion() (poll 2 min) · useFestival/useStages/useArtists/useSlots/usePois/...
├─ features/timetable/  TimetableGrid · TimetableList · DayTabs · SlotCard · NowLine · useClashes()
├─ features/favorites/  FavoriteButton · useFavorites() · IcsButton
├─ features/map/        FestivalMap (CRS.Simple) · PoiMarker · PoiFilterBar · PoiSheet
├─ features/news/       NewsFeed (fusion éditorial+auto, filtre publishAt) · NewsItemCard · SafetyBanner
└─ components/  ArtistCard ArtistGrid GenreFilter SpotifyEmbed SponsorGrid
                InfoList InfoPage NowNextWidget SearchOverlay WeatherStrip
                TicketEmbed InstallHint
```

---

## 11. State & persistance locale

- **State serveur** (tous les JSON) : TanStack Query, `staleTime` 2 min,
  invalidé par le polling de version.
- **Favoris** : store Zustand, persisté en IndexedDB (`idb-keyval`, clé
  `favorites`) comme `Set<slotId>`.
- **State UI** (jour, filtre, langue) : Zustand + `localStorage`.
- **Dernier état des données** (`generatedAt`) : IndexedDB, pour l'affichage hors-ligne.

Génération `.ics` (`src/lib/ics.ts`) : VEVENT avec VALARM (`-PT15M`) ;
fonctionne sur iOS + Android. UX de rappel : étoile = favori ; le bouton
« Rappel (.ics) » télécharge l'événement avec 15 min d'avance.

---

## 12. Spécifications des fonctionnalités

### 12.1 Line-up + pages artistes
Grille d'`ArtistCard` (têtes d'affiche d'abord, sinon alphabétique), filtre de
genre (chips). Page artiste : en-tête (image 4:5, nom, genre, pays), bio
(Markdown), `SpotifyEmbed`, horaires depuis `slots`, `FavoriteButton`.

### 12.2 Programme (plusieurs vues)
- **Grille** : CSS Grid, colonnes = scènes (par `order`, couleur `stage.color`), lignes = axe temporel.
- **Liste** : chronologique par jour, filtre « favoris uniquement ».
- **DayTabs** selon `FestivalDay` ; débordement de minuit via `dayStart/dayEnd` (Luxon).
- **NowLine** : heure actuelle ; **indicateur de conflit** via `useClashes()` sur les créneaux favoris.
- Source des créneaux selon §6.5 (csv / joomla-customfields / wordpress-acf).

### 12.3 Favoris / mon planning + `.ics`
Étoile sur créneau/artiste ; « Mon planning » montre les favoris
chronologiquement avec alerte de conflit ; `.ics` individuel ou « tous ».

### 12.4 Carte interactive hors-ligne
Leaflet `L.CRS.Simple` + `L.imageOverlay` (bounds depuis `map.json`). Marqueurs
POI par `type`, barre de filtres, `PoiSheet` avec détail. Image pré-cachée en
`.webp` → totalement hors-ligne. Pas de position GPS propre pour l'instant.

### 12.5 Fil d'actus (programmé) + début de concert auto
Les entrées éditoriales ne sont visibles que si `publishAt <= now` (et
`expiresAt > now`). Début de concert auto : par créneau, une entrée virtuelle
`{category:"lineup", title:"Maintenant : <artiste> @ <scène>", time:slot.start}`,
visible dès `start <= now`. Fusionner les deux, décroissant par heure,
`pinned`/`safety` en haut. Sécurité mise en avant (bannière). Préparation à
l'avance via un `publishAt` futur.

### 12.6 Now / up next
`NowNextWidget` sur l'accueil : par scène « joue maintenant » + « à suivre »
depuis `slots` + `now`.

### 12.7 Recherche
Index côté client sur artistes/créneaux/infos/POI ; correspondance
sous-chaîne/token (optionnellement `match-sorter`).

### 12.8 Sponsors
`SponsorGrid` groupé par `tier` ; le logo pointe vers `url`.

### 12.9 Pages d'infos
Rendu Markdown ; FAQ comme `## question` + réponse (accordéon optionnel).
Source configurable individuellement par page (§6.1, `info.overrides`).

### 12.10 Météo
`WeatherStrip` lit `weather.json`. Par jour, symbole + min/max.

### 12.11 Billetteries
`tickets.json` pilote `embedType`. **iframe** avec `sandbox` + liste `allow` ;
**repli « link »** si la boutique interdit le framing via
`X-Frame-Options`/CSP.

---

## 13. PWA / hors-ligne / installation

- `vite-plugin-pwa`. Precache de l'app shell ; runtime caching selon §5.1.
- `manifest.webmanifest` : nom, nom court, couleur de thème/fond
  (sombre/jaune), icônes (192/512 + maskable), `display:"standalone"`,
  `start_url:"/"`, `scope:"/"`.
- Aides à l'installation : bouton `beforeinstallprompt` Android/Chrome ; iOS
  `InstallHint` (« Partager → Sur l'écran d'accueil »).
- Le **web push** reste une extension optionnelle (VAPID, backend séparé, iOS
  seulement après installation sur l'écran d'accueil). Inutile pour le MVP —
  les rappels passent par `.ics`.

---

## 14. Internationalisation (i18n)

`react-i18next`, défaut **de**, optionnellement **en/fr/es**. Chaînes d'UI dans
`src/i18n/{de,en,fr,es}.json`. Données de contenu monolingues (de) ; champs
`*_en` optionnels possibles plus tard, pas dans le MVP.

---

## 15. Build & déploiement (World4You)

1. Créer `.env` avec les identifiants (depuis `.env.example`).
2. `npm run import` → `import-from-source.ts` lit `content-sources.config.ts`,
   récupère par élément de menu depuis manual/Joomla/WordPress, télécharge les
   images localement, écrit `public/data/*`.
3. `npm run build:data` → valide le schéma, génère `version.json` (hashes).
4. `npm run build` → build Vite vers `dist/`.
5. Téléverser `dist/` par SFTP dans le docroot du sous-domaine
   (`demo.festivadget.com`). **HTTPS obligatoire.**
6. `.htaccess` (Apache) : repli SPA + en-têtes.

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

**Mise à jour des données en exploitation :** lancer `import` + `build:data`,
ne téléverser que les `data/*.json` modifiés + `version.json`. Les clients se
mettent à jour (≤ 2 min). Pas de rebuild de l'app shell nécessaire.

---

## 16. Setup du projet GitHub & changelog

- **Dépôt** : démarrer privé, public plus tard. **Aucun** identifiant, aucun
  vrai token commité.
- **`.gitignore`** : `node_modules`, `dist`, `.env*`, (optionnel) les
  `public/data/*` générés (suivre les fichiers d'exemple).
- **Branching** : workflow par feature branches.
- **CLAUDE.md** : commentaires en allemand ; confirmation requise pour les
  changements du schéma de données, des dépendances, de la logique centrale
  (cache/programme/favoris) et de `content-sources.config.ts`.
- **README.md** : setup, `import`/`build:data`/`build`, déploiement, configuration des sources.
- **LICENSE** : **GNU AGPLv3** pour le code (contenus/logos/cartes exclus).
- **CI (optionnel)** : GitHub Action `build` (lint + typecheck + build).
- **Versioning** : **SemVer** ; **changelog au format « Keep a Changelog »**
  (§18) ; tag par release.

---

## 17. Feuille de route de développement (phases & effort)

> Effort = temps de développement concentré avec Claude Code.

**Phase 0 — squelette (~1 jour)**
Vite+TS+Tailwind (tokens de design §2), routage, AppShell+BottomNav, TanStack
Query, `vite-plugin-pwa`, manifest/icônes, polling 2 min de `version.json`. → `v0.1.0`.

**Phase 1 — pipeline de données + contenus en lecture seule (~4–5 jours)**
`content-sources.config.ts`, adaptateurs (manual/Joomla/WordPress/csv),
`import` + `build:data`, schéma/types ; line-up, pages artistes (+embed
Spotify), pages d'infos, sponsors, bandeau météo, billets. → `v0.2.0`.

**Phase 2 — programme & favoris (~3 jours)**
Grille + liste, onglets jours, ligne « now », favoris (IndexedDB), détecteur de
conflits, rappel `.ics`, mon planning, now/up next ; source du programme
commutable (§6.5). → `v0.3.0`.

**Phase 3 — carte & actus & recherche (~2–3 jours)**
Carte Leaflet + POI + filtre, fil d'actus (publishAt + début de concert auto +
sécurité), recherche globale. → `v0.4.0`.

**Phase 4 — durcissement hors-ligne & polish (~1–2 jours)**
Affiner le cache, indicateur hors-ligne, aides à l'installation, tokens de
design exacts depuis le CSS T4, icônes/splash, check PWA Lighthouse. → `v1.0.0`.

**Phase 5 — web push (optionnel, ~2–3 jours)**
VAPID, backend d'abonnements, admin d'envoi. → `v1.1.0`.

**Total MVP (phases 0–4) : ~11–14 jours.** Plus gros poste hors code :
**gestion du contenu** (bios, photos, programme, dessin de la carte) — à
planifier tôt.

---

## 18. Modèle de CHANGELOG

Fichier `CHANGELOG.md` (Keep a Changelog + SemVer) :

```markdown
# Changelog

Tous les changements notables de ce projet sont documentés ici.
Format selon Keep a Changelog, versioning selon SemVer.

## [Unreleased]
### Added
### Changed
### Fixed

## [0.1.0] - 2026-06-XX
### Added
- Squelette du projet (Vite, React, TS, Tailwind, routage, setup PWA)
- Polling de version (version.json, cycle de 2 minutes) et stratégie de cache
```

---

## Historique des modifications de ce document

### [1.1.0] - 2026-06-23
**Removed**
- Fonctionnalité **Spotted** entièrement supprimée (schéma, route, composants,
  chemin d'écriture backend, phase).
- Micro-backend obligatoire supprimé — le fonctionnement est désormais purement
  statique ; le web push n'est plus qu'une extension optionnelle.

**Added**
- §6 **configuration des sources de données** : au choix par élément de menu
  entre `manual` / `joomla` / `wordpress` (incl. `content-sources.config.ts`,
  architecture d'adaptateurs, mapping Joomla et WordPress, sécurité).
- §6.5 **source du programme commutable** : `csv` | `joomla-customfields` | `wordpress-acf`.
- §2 **direction de design** (sombre/blanc/jaune, images d'artistes 4:5, tokens
  à vérifier depuis le CSS T4).

**Changed**
- Cache/polling unifiés de 60 s/5 min à **2 minutes** (`max-age=120`,
  `refetchInterval 120_000`).
- Feuille de route ajustée (pipeline de données en phase 1, phase Spotted supprimée).

### [1.0.0] - 2026-06-22
- Première version.

---
