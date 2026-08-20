# IMPLEMENTATION.de.md — ROCK IM DORF Festival App (PWA)

**🇩🇪 [Deutsch](IMPLEMENTATION.de.md) · 🇬🇧 [English](IMPLEMENTATION.md) · 🇫🇷 [Français](IMPLEMENTATION.fr.md)**

> Documento de trabajo para el desarrollo con Claude Code.
> Nombre del proyecto (propuesta): `rid-festival-app`
> Objetivo: progressive web app instalable y utilizable sin conexión para los
> visitantes del festival, alojada como archivos estáticos en un subdominio
> (p. ej. `demo.festivadget.com`).

**Versión de este documento:** 1.1.0 · **Fecha:** 2026-06-23

---

## 0. Índice

1. Objetivo y alcance del proyecto
2. Dirección de diseño
3. Stack tecnológico
4. Vista general de la arquitectura
5. Frescura de datos y estrategia de caché
6. Configuración de fuentes de datos (elegible por elemento de menú)
7. Modelo de datos / esquema JSON (objetivo normalizado)
8. Estructura del proyecto/directorios
9. Enrutado
10. Estructura de componentes
11. Estado y persistencia local
12. Especificaciones de funcionalidades
13. PWA / offline / instalación
14. Internacionalización (i18n)
15. Build y despliegue (World4You)
16. Configuración del proyecto en GitHub y changelog
17. Hoja de ruta de desarrollo (fases y esfuerzo)
18. Plantilla de CHANGELOG

---

## 1. Objetivo y alcance del proyecto

Una **PWA** (sin app nativa, sin app store) como app central de los visitantes
del festival. Principio básico: **fully static**. Todos los contenidos vienen
de archivos JSON versionados servidos por el servidor web. Ya **no hay backend
obligatorio**.

Los datos de contenido se obtienen **en tiempo de build** desde fuentes
elegibles (manual / Joomla / WordPress, ver §6) y se normalizan a un único
esquema JSON. En funcionamiento, la app es puramente estática.

### Dentro del alcance (MVP + ampliaciones)

| Funcionalidad | ¿Backend necesario en funcionamiento? |
|---|---|
| Cartel + páginas de artistas | no |
| Horarios (varias vistas) | no |
| Favoritos / mi plan + recordatorio `.ics` | no |
| Mapa interactivo offline con POIs | no |
| Feed de noticias/info con publicación programada + inicio de concierto automático | no |
| Now / up next | no |
| Búsqueda | no |
| Sección de patrocinadores | no |
| Páginas de información (llegada, recinto, camping, caravana, cashless, vuelta a casa, gastronomía, bebidas, FAQ) | no |
| El tiempo | no (lee un JSON preparado) |
| Tiendas de entradas (iframe/enlace) | no |
| Web push (ampliación opcional) | sí (aparte) |

### Fuera del alcance (deliberadamente)
- **Spotted** — eliminado (UGC + moderación + esfuerzo RGPD no deseados).
- Amigos / compartir ubicación en vivo.
- Widget de pantalla de bloqueo (solo nativo).
- Sistema propio de cashless/entradas (lo aportan proveedores externos).

---

## 2. Dirección de diseño

Orientado a rockimdorf.at (Joomla 5, plantilla T4):

- **Tema oscuro**: fondo oscuro, tipografía blanca, **acento amarillo**
  (amarillo green-event) para CTAs y estados activos (favorito, «suena ahora»).
- **Imágenes de artistas en vertical 4:5** (1080×1350), a gran superficie/full-bleed.
- **Tipografía display** potente para titulares, sans clara para el texto corrido.
- Tono: rockero, «fuera del mainstream», deliberadamente cercano/«pequeño y cuidado».

Tokens como tema de Tailwind (marcadores de posición — **verificar hex/fuentes
exactos desde el CSS de T4**):

```css
:root {
  --rid-bg:        #121212; /* TODO: verificar desde el CSS de T4 */
  --rid-surface:   #1c1c1c;
  --rid-text:      #ffffff;
  --rid-muted:     #b3b3b3;
  --rid-accent:    #f2c200; /* amarillo green-event, TODO verificar */
  --rid-accent-2:  #e4572e; /* secundario (p. ej. NowLine), opcional */
}
```

> Antes de la fase 4: extraer los valores de color exactos, familias
> tipográficas y assets del logo desde la plantilla en vivo y anotarlos aquí.

---

## 3. Stack tecnológico

- **Build:** Vite + React 18 + TypeScript
- **Estilos:** TailwindCSS (tokens de tema del §2)
- **Enrutado:** `react-router-dom` v6 (alternativa: TanStack Router)
- **Estado de servidor / obtención de datos:** TanStack Query (`@tanstack/react-query`)
- **Estado de cliente:** Zustand (favoritos, estado de UI)
- **Persistencia local:** IndexedDB vía `idb-keyval`
- **Fecha/hora/zona horaria:** Luxon (imprescindible por `Europe/Vienna` + desbordamiento de medianoche)
- **Mapa:** Leaflet (`CRS.Simple`, ImageOverlay)
- **PWA / service worker:** `vite-plugin-pwa` (Workbox por debajo)
- **i18n:** `react-i18next` (de por defecto, en opcional)
- **Iconos:** `lucide-react`
- **Renderizado de Markdown (info/noticias/bio):** `react-markdown` + `remark-gfm`
- **Generación de `.ics`:** minifunción propia (sin paquete)
- **Importación en build:** scripts de Node + `papaparse` (CSV), `node-fetch`/`undici` (REST)

> Nota: **deliberadamente NO FullCalendar** para los horarios. La cuadrícula
> escenario×tiempo se resuelve más limpia y apta para móvil con CSS Grid.

---

## 4. Vista general de la arquitectura

```
                    Navegador (PWA)
   ┌──────────────────────────────────────────────┐
   │  App React (app shell, cache-first)           │
   │   - TanStack Query (datos)                    │
   │   - Zustand (favoritos) -> IndexedDB          │
   │   - Service worker (Workbox)                  │
   └───────────────┬──────────────────────────────┘
                   │  HTTPS GET (estático)
                   ▼
   ┌──────────────────────────────────────────────┐
   │  Servidor web estático (World4You)            │
   │   /            -> app shell (artefactos build)│
   │   /data/*.json -> datos de contenido (versionados) │
   │   /data/version.json -> hashes (no-cache)     │
   │   /map/*.webp  -> imagen del plano             │
   └──────────────────────────────────────────────┘

   ── en build (no en funcionamiento) ──────────────
   scripts/import-from-source.ts lee content-sources.config.ts
   y obtiene por elemento de menú desde: manual | API Joomla | API WordPress
   -> normaliza -> public/data/*.json + version.json

   ── opcional (VPS propio) ──────────────────────────
   Web push (VAPID) — solo si se quieren recordatorios push reales.
```

El funcionamiento es **puramente estático**. La conexión con las fuentes ocurre
exclusivamente en el build.

---

## 5. Frescura de datos y estrategia de caché

Objetivo: online ~en vivo (**≤ 2 min**), offline el último estado conocido.

### 5.1 Clases de archivos y encabezados HTTP

| Ruta | Cache-Control | Estrategia SW |
|---|---|---|
| App shell (`/assets/*` con hash) | `max-age=31536000, immutable` | CacheFirst (precache) |
| `index.html` | `no-cache` | NetworkFirst |
| `/data/*.json` (contenidos) | `max-age=120` | NetworkFirst, timeout 3 s, respaldo caché |
| `/data/version.json` | `no-cache` | NetworkOnly (con respaldo de caché offline) |
| `/map/*.webp` | `max-age=86400` | StaleWhileRevalidate |

### 5.2 Sondeo de versión (near-live, ciclo de 2 minutos)

`version.json` contiene un hash de contenido por conjunto de datos:

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

- TanStack Query carga `version.json` con `refetchInterval: 120_000`
  (**2 min**), solo si `document.visibilityState === 'visible'` y hay conexión.
- Si cambia el hash de un conjunto de datos, **solo se invalida su query** → refetch dirigido.
- `version.json` se regenera automáticamente en el build de datos (§15).

> Cambio de contenido en el servidor → visible para todos los clientes online
> en ~2 min.

### 5.3 Comportamiento offline
- La primera carga pre-cachea todos los `/data/*.json` y la imagen del mapa.
- Si falla la red, NetworkFirst sirve desde la caché; la UI muestra
  «Offline / estado: HH:MM» (de `generatedAt` de la última carga correcta,
  guardado en IndexedDB).

---

## 6. Configuración de fuentes de datos (elegible por elemento de menú)

**Requisito central:** para **cada elemento de menú / dominio de contenido** se
elige individualmente si los datos se mantienen **manualmente** o se obtienen
por la API de **Joomla** o **WordPress**. La resolución ocurre en el build
mediante una configuración central y adaptadores intercambiables. El esquema de
ejecución (§7) es independiente de la fuente: vengan de donde vengan los datos,
acaban en el mismo JSON normalizado.

### 6.1 Archivo de configuración `content-sources.config.ts`

```ts
type Provider = "manual" | "joomla" | "wordpress";

interface JoomlaLocator {
  categoryId?: number;        // categoría de artículos (p. ej. cartel del viernes)
  ids?: number[];             // IDs de artículos explícitos
  customFields?: Record<string, string>; // campo del esquema -> nombre del custom field de Joomla
}

interface WordPressLocator {
  categorySlug?: string;      // slug de categoría
  postType?: string;          // "post" o custom post type
  acf?: Record<string, string>; // campo del esquema -> nombre del campo ACF
}

interface SourceBinding {
  provider: Provider;
  joomla?: JoomlaLocator;
  wordpress?: WordPressLocator;
  // provider === "manual" -> los datos vienen de content/<dominio>.json (mantenido en el repo)
}

interface ContentSourcesConfig {
  // valores de conexión por defecto (tokens SOLO desde ENV, nunca commitear):
  joomla?:    { baseUrl: string; tokenEnv: string };          // p. ej. "JOOMLA_API_TOKEN"
  wordpress?: { baseUrl: string; userEnv?: string; appPwEnv?: string };

  // un binding por dominio / elemento de menú:
  bindings: {
    festival: SourceBinding;
    stages:   SourceBinding;
    artists:  SourceBinding;
    slots:    SourceBinding & { format?: "csv" | "joomla-customfields" | "wordpress-acf" };
    pois:     SourceBinding;
    news:     SourceBinding;
    sponsors: SourceBinding;
    tickets:  SourceBinding;
    weather:  SourceBinding;     // normalmente "manual"
    // páginas de info sobreescribibles individualmente (cada página su fuente):
    info: {
      default: SourceBinding;
      overrides?: Record<string, SourceBinding>; // clave = InfoPage.id ("faq", "anreise", ...)
    };
  };
}
```

**Ejemplo** (artistas desde Joomla, FAQ manual, resto mixto):

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
    sponsors: { provider: "joomla" }, // componente weblinks, ver 6.3
    tickets:  { provider: "manual" },
    weather:  { provider: "manual" },
    info: {
      default: { provider: "joomla", joomla: { categoryId: 8 } },
      overrides: { faq: { provider: "manual" } },
    },
  },
};
```

### 6.2 Arquitectura del importador (patrón adaptador)

`scripts/import-from-source.ts` itera sobre `bindings`, llama al adaptador
correspondiente según `provider`, normaliza al esquema (§7), descarga
localmente las imágenes referenciadas (`public/img/...`, por offline +
same-origin) y escribe `public/data/<dominio>.json`. Después se recalculan los
hashes de `version.json`.

```
scripts/
├─ import-from-source.ts        # orquestación, lee config + ENV
├─ build-data.ts                # validación (esquema) + version.json (hashes)
└─ adapters/
   ├─ manual.ts                 # lee content/<dominio>.json, valida
   ├─ joomla.ts                 # API REST de web services de Joomla
   ├─ wordpress.ts              # API REST de WordPress (+ ACF)
   └─ csv.ts                    # parsea content/slots.csv (papaparse)
```

Cada adaptador implementa la misma interfaz:

```ts
interface SourceAdapter {
  fetchDomain(domain: string, binding: SourceBinding, cfg: ContentSourcesConfig): Promise<unknown[]>;
}
```

### 6.3 Adaptador de Joomla

- **Artículos** (artistas, noticias, info): `GET {baseUrl}/api/index.php/v1/content/articles?filter[category]={id}`
  con el encabezado `Authorization: Bearer {JOOMLA_API_TOKEN}`. Artículos individuales vía `/articles/{id}`.
- **Custom fields**: incluidos en la respuesta de la API (com_fields) o
  solicitables por parámetros de campos; mapeo mediante `joomla.customFields`.
- **Patrocinadores (weblinks)**: endpoint de web services del componente
  weblinks si el plugin está activo; respaldo = feed RSS de la categoría de
  weblinks correspondiente (presented by / powered by / partner).
- **Sanear** el cuerpo HTML de los artículos y convertirlo a Markdown (o HTML limpio).

### 6.4 Adaptador de WordPress

- **Posts/CPT**: `GET {baseUrl}/wp-json/wp/v2/{postType}?categories={id}`
  (auth vía application password, basic auth con `WP_USER`/`WP_APP_PW`).
- **ACF** (equivalente a los custom fields de Joomla): campos en la respuesta
  REST si «Show in REST» está activo o vía ACF-to-REST; mapeo mediante
  `wordpress.acf`.
- Resolver imágenes vía `_embed`/endpoint de media y descargarlas localmente.

### 6.5 Fuente de los horarios (conmutable)

`slots.format` determina de dónde vienen escenario + inicio/fin:

| `format` | Fuente | Campos |
|---|---|---|
| `csv` | `content/slots.csv` | `artistSlug,stageId,dayId,start,end,note` |
| `joomla-customfields` | custom fields de los artículos de artistas | según `joomla.customFields` |
| `wordpress-acf` | campos ACF de los posts de artistas | según `wordpress.acf` |

Con `csv`, las actuaciones se unen a los artistas (quizá obtenidos de otra
fuente) mediante `artistSlug`. Con las variantes de custom fields, las
actuaciones se derivan directamente de los registros de artistas.

`content/slots.csv` (ejemplo):

```csv
artistSlug,stageId,dayId,start,end,note
bibiza,main,sa,2026-08-01T22:00:00+02:00,2026-08-01T23:30:00+02:00,
greeen,main,fr,2026-07-31T21:30:00+02:00,2026-07-31T23:00:00+02:00,
paula-carolina,second,fr,2026-07-31T19:30:00+02:00,2026-07-31T20:30:00+02:00,
```

### 6.6 Seguridad de la conexión

- Tokens de API / contraseñas de aplicación **exclusivamente en `.env`** (en
  `.gitignore`), nunca commitear.
- Token de Joomla **de solo lectura**, acotado; si es posible, autorizar la
  máquina de build por IP.
- Como la importación corre **en el servidor** en tiempo de build, las
  credenciales **nunca llegan al navegador**.
- Sanear el HTML de fuentes CMS antes de guardarlo.
- Copiar las imágenes localmente en vez de hotlinkear (caché offline + sin
  problemas de CORS).

---

## 7. Modelo de datos / esquema JSON (objetivo normalizado)

Todos los archivos están bajo `/data/`. Tipos de TypeScript bajo `src/types/`.
Los IDs son cadenas cortas y estables (tipo slug). Marcas de tiempo **siempre
en ISO 8601 con offset** (`+02:00`). Este es el **objetivo independiente de la
fuente**: los adaptadores (§6) deben mapear hacia él.

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
  label: string;     // "Viernes 31.07."
  dayStart: string;  // inicio lógico del día
  dayEnd: string;    // fin lógico del día (¡desbordamiento de medianoche!)
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
  x: number; y: number;   // coordenadas en píxeles en el sistema CRS.Simple
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
  publishAt: string;      // el cliente la muestra solo a partir de este momento
  expiresAt?: string; pinned?: boolean;
  image?: string; link?: { label: LocalizedText; url: string };
}
```
> **Contenidos localizables**: `title`, `body` y `link.label` aceptan una cadena
> simple (monolingüe) o un mapa de idiomas como `{ "de": "…", "en": "…" }`.
> La app resuelve el idioma del usuario con la cadena de respaldo idioma → en → de →
> primer valor disponible (`src/lib/localized.ts`); los textos push se resuelven
> por idioma de la suscripción (`push/texts.php`).
> Las **entradas automáticas de inicio de concierto** se generan en tiempo de
> ejecución a partir de `slots.json` (ver §12.5) y se fusionan con las noticias
> editoriales.

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

## 8. Estructura del proyecto/directorios

```
rid-festival-app/
├─ public/
│  ├─ data/                 # generado por import + build-data
│  │  ├─ festival.json stages.json artists.json slots.json
│  │  ├─ pois.json map.json news.json sponsors.json
│  │  ├─ info.json tickets.json weather.json
│  │  └─ version.json       # generado (hashes)
│  ├─ map/gelaendeplan.webp
│  ├─ img/{artists,sponsors}/...   # depositado localmente por el importador
│  ├─ icons/                # iconos PWA (192,512,maskable)
│  └─ manifest.webmanifest
├─ content/                 # fuentes mantenidas a mano (provider:"manual")
│  ├─ festival.json stages.json pois.json tickets.json weather.json ...
│  └─ slots.csv             # si slots.format === "csv"
├─ content-sources.config.ts  # §6: fuente por elemento de menú
├─ .env.example             # JOOMLA_API_TOKEN, WP_USER, WP_APP_PW (valores de ejemplo)
├─ scripts/
│  ├─ import-from-source.ts
│  ├─ build-data.ts
│  └─ adapters/{manual,joomla,wordpress,csv}.ts
├─ src/
│  ├─ main.tsx App.tsx
│  ├─ routes/               # páginas (§9)
│  ├─ components/           # (§10)
│  ├─ features/{timetable,favorites,map,news}/
│  ├─ data/                 # hooks de query (useArtists, useSlots, useVersion, ...)
│  ├─ lib/                  # ics.ts time.ts search.ts sw-register.ts
│  ├─ store/                # stores de Zustand
│  ├─ types/                # tipos del esquema del §7
│  ├─ i18n/                 # de.json en.json config
│  └─ styles/
├─ CLAUDE.md CHANGELOG.md README.md LICENSE .gitignore
├─ vite.config.ts tailwind.config.ts package.json
```

> Un `backend/` opcional (FastAPI) solo si el web push (§13) llega a construirse.

---

## 9. Enrutado

Mobile-first, barra de pestañas inferior para las secciones principales.

| Ruta | Página | Contenido |
|---|---|---|
| `/` | Inicio | now/up next, noticias fijadas, próximo favorito, avance del tiempo |
| `/lineup` | Cartel | cuadrícula de artistas, filtro de género, cabezas de cartel primero |
| `/artist/:slug` | Página de artista | bio, embed de Spotify, horarios, favorito |
| `/timetable` | Horarios | vista cuadrícula/lista, pestañas de días, marcadores de solape, línea «now» |
| `/favorites` | Mi plan | actuaciones favoritas, exportación `.ics`, aviso de solape |
| `/map` | Mapa | Leaflet, filtro de POI, hoja de detalle |
| `/news` | Noticias e info | feed fusionado (editorial + inicio de concierto automático), seguridad arriba |
| `/info` + `/info/:id` | Información | resumen + detalle en Markdown |
| `/sponsors` | Patrocinadores | agrupados por nivel |
| `/tickets` | Entradas | iframe/enlace por proveedor |
| `/search` | Búsqueda | global (artistas/actuaciones/info/POIs) |

Barra de pestañas (5 huecos): **Inicio · Cartel · Horarios · Mapa · Más**.
«Más» abre una hoja: mi plan, noticias, información, patrocinadores, entradas,
búsqueda, idioma.

---

## 10. Estructura de componentes

```
App
├─ AppShell (TopBar, <Outlet/>, BottomNav, OfflineBadge)
├─ data/  useVersion() (poll de 2 min) · useFestival/useStages/useArtists/useSlots/usePois/...
├─ features/timetable/  TimetableGrid · TimetableList · DayTabs · SlotCard · NowLine · useClashes()
├─ features/favorites/  FavoriteButton · useFavorites() · IcsButton
├─ features/map/        FestivalMap (CRS.Simple) · PoiMarker · PoiFilterBar · PoiSheet
├─ features/news/       NewsFeed (fusión editorial+auto, filtro publishAt) · NewsItemCard · SafetyBanner
└─ components/  ArtistCard ArtistGrid GenreFilter SpotifyEmbed SponsorGrid
                InfoList InfoPage NowNextWidget SearchOverlay WeatherStrip
                TicketEmbed InstallHint
```

---

## 11. Estado y persistencia local

- **Estado de servidor** (todos los JSON): TanStack Query, `staleTime` 2 min,
  invalidado por el sondeo de versión.
- **Favoritos**: store de Zustand, persistido en IndexedDB (`idb-keyval`, clave
  `favorites`) como `Set<slotId>`.
- **Estado de UI** (día, filtro, idioma): Zustand + `localStorage`.
- **Último estado de datos** (`generatedAt`): IndexedDB, para la vista offline.

Generación de `.ics` (`src/lib/ics.ts`): VEVENT con VALARM (`-PT15M`); funciona
en iOS + Android. UX de recordatorio: estrella = favorito; el botón
«Recordatorio (.ics)» descarga el evento con 15 min de antelación.

---

## 12. Especificaciones de funcionalidades

### 12.1 Cartel + páginas de artistas
Cuadrícula de `ArtistCard` (cabezas de cartel primero, si no alfabético),
filtro de género (chips). Página de artista: cabecera (imagen 4:5, nombre,
género, país), bio (Markdown), `SpotifyEmbed`, horarios desde `slots`,
`FavoriteButton`.

### 12.2 Horarios (varias vistas)
- **Cuadrícula**: CSS Grid, columnas = escenarios (por `order`, color `stage.color`), filas = eje temporal.
- **Lista**: cronológica por día, filtro «solo favoritos».
- **DayTabs** según `FestivalDay`; desbordamiento de medianoche vía `dayStart/dayEnd` (Luxon).
- **NowLine**: hora actual; **indicador de solape** vía `useClashes()` sobre actuaciones favoritas.
- Fuente de datos de las actuaciones según §6.5 (csv / joomla-customfields / wordpress-acf).

### 12.3 Favoritos / mi plan + `.ics`
Estrella en actuación/artista; «Mi plan» muestra los favoritos
cronológicamente con aviso de solapes; `.ics` individual o «todos».

### 12.4 Mapa interactivo offline
Leaflet `L.CRS.Simple` + `L.imageOverlay` (bounds desde `map.json`). Marcadores
de POI por `type`, barra de filtros, `PoiSheet` con detalle. Imagen
pre-cacheada como `.webp` → completamente offline. La posición GPS propia se
omite por ahora.

### 12.5 Feed de noticias (programado) + inicio de concierto automático
Las entradas editoriales solo son visibles si `publishAt <= now` (y
`expiresAt > now`). Inicio de concierto automático: por actuación, una entrada
virtual `{category:"lineup", title:"Ahora: <artista> @ <escenario>", time:slot.start}`,
visible desde `start <= now`. Fusionar ambas, descendente por hora,
`pinned`/`safety` arriba. Seguridad destacada (banner). Preparación anticipada
mediante un `publishAt` futuro.

### 12.6 Now / up next
`NowNextWidget` en el inicio: por escenario «suena ahora» + «a continuación»
desde `slots` + `now`.

### 12.7 Búsqueda
Índice en el cliente sobre artistas/actuaciones/info/POIs; coincidencia por
subcadena/token (opcionalmente `match-sorter`).

### 12.8 Patrocinadores
`SponsorGrid` agrupado por `tier`; el logo enlaza a `url`.

### 12.9 Páginas de información
Renderizado Markdown; FAQ como `## pregunta` + respuesta (acordeón opcional).
Fuente configurable individualmente por página (§6.1, `info.overrides`).

### 12.10 El tiempo
`WeatherStrip` lee `weather.json`. Por día, símbolo + mín/máx.

### 12.11 Tiendas de entradas
`tickets.json` controla `embedType`. **iframe** con `sandbox` + lista `allow`;
**respaldo «link»** si la tienda prohíbe el framing vía `X-Frame-Options`/CSP.

---

## 13. PWA / offline / instalación

- `vite-plugin-pwa`. Precache del app shell; runtime caching según §5.1.
- `manifest.webmanifest`: nombre, nombre corto, color de tema/fondo
  (oscuro/amarillo), iconos (192/512 + maskable), `display:"standalone"`,
  `start_url:"/"`, `scope:"/"`.
- Avisos de instalación: botón `beforeinstallprompt` en Android/Chrome; en iOS
  `InstallHint` («Compartir → Añadir a pantalla de inicio»).
- El **web push** sigue siendo una ampliación opcional (VAPID, backend
  separado, iOS solo tras instalar en la pantalla de inicio). No es necesario
  para el MVP: los recordatorios van por `.ics`.

---

## 14. Internacionalización (i18n)

`react-i18next`, por defecto **de**, opcionalmente **en/fr/es**. Cadenas de UI
en `src/i18n/{de,en,fr,es}.json`. Datos de contenido monolingües (de); campos
`*_en` opcionales posibles más adelante, no en el MVP.

---

## 15. Build y despliegue (World4You)

1. Crear `.env` con las credenciales (desde `.env.example`).
2. `npm run import` → `import-from-source.ts` lee
   `content-sources.config.ts`, obtiene por elemento de menú desde
   manual/Joomla/WordPress, descarga las imágenes localmente, escribe
   `public/data/*`.
3. `npm run build:data` → valida el esquema, genera `version.json` (hashes).
4. `npm run build` → build de Vite hacia `dist/`.
5. Subir `dist/` por SFTP al docroot del subdominio (`demo.festivadget.com`).
   **HTTPS obligatorio.**
6. `.htaccess` (Apache): respaldo SPA + encabezados.

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

**Actualización de datos en funcionamiento:** ejecutar `import` + `build:data`,
subir solo los `data/*.json` modificados + `version.json`. Los clientes se
actualizan (≤ 2 min). No hace falta reconstruir el app shell.

---

## 16. Configuración del proyecto en GitHub y changelog

- **Repo**: empezar privado, luego público. **No** commitear credenciales ni tokens reales.
- **`.gitignore`**: `node_modules`, `dist`, `.env*`, (opcional) los
  `public/data/*` generados (seguir los archivos de ejemplo).
- **Branching**: flujo de trabajo con ramas de feature.
- **CLAUDE.md**: comentarios en alemán; confirmación necesaria para cambios en
  el esquema de datos, dependencias, lógica central (caché/horarios/favoritos)
  y en `content-sources.config.ts`.
- **README.md**: setup, `import`/`build:data`/`build`, despliegue, configuración de fuentes.
- **LICENSE**: **GNU AGPLv3** para el código (contenidos/logos/mapas excluidos).
- **CI (opcional)**: GitHub Action `build` (lint + typecheck + build).
- **Versionado**: **SemVer**; **changelog en formato «Keep a Changelog»** (§18);
  tag por release.

---

## 17. Hoja de ruta de desarrollo (fases y esfuerzo)

> Esfuerzo = tiempo de desarrollo concentrado con Claude Code.

**Fase 0 — andamiaje (~1 día)**
Vite+TS+Tailwind (tokens de diseño §2), enrutado, AppShell+BottomNav, TanStack
Query, `vite-plugin-pwa`, manifest/iconos, sondeo de 2 min de `version.json`. → `v0.1.0`.

**Fase 1 — pipeline de datos + contenidos de solo lectura (~4–5 días)**
`content-sources.config.ts`, adaptadores (manual/Joomla/WordPress/csv),
`import` + `build:data`, esquema/tipos; cartel, páginas de artistas (+embed de
Spotify), páginas de info, patrocinadores, franja del tiempo, entradas. → `v0.2.0`.

**Fase 2 — horarios y favoritos (~3 días)**
Cuadrícula + lista, pestañas de días, línea «now», favoritos (IndexedDB),
detector de solapes, recordatorio `.ics`, mi plan, now/up next; fuente de
horarios conmutable (§6.5). → `v0.3.0`.

**Fase 3 — mapa, noticias y búsqueda (~2–3 días)**
Mapa Leaflet + POIs + filtro, feed de noticias (publishAt + inicio de concierto
automático + seguridad), búsqueda global. → `v0.4.0`.

**Fase 4 — endurecimiento offline y pulido (~1–2 días)**
Ajustar la caché, indicador offline, avisos de instalación, tokens de diseño
exactos del CSS de T4, iconos/splash, comprobación PWA de Lighthouse. → `v1.0.0`.

**Fase 5 — web push (opcional, ~2–3 días)**
VAPID, backend de suscripciones, admin para enviar. → `v1.1.0`.

**Total MVP (fases 0–4): ~11–14 días.** La mayor partida no de código:
**mantenimiento de contenido** (bios, fotos, horarios, dibujar el mapa) —
planificar pronto.

---

## 18. Plantilla de CHANGELOG

Archivo `CHANGELOG.md` (Keep a Changelog + SemVer):

```markdown
# Changelog

Todos los cambios destacables de este proyecto se documentan aquí.
Formato según Keep a Changelog, versionado según SemVer.

## [Unreleased]
### Added
### Changed
### Fixed

## [0.1.0] - 2026-06-XX
### Added
- Andamiaje del proyecto (Vite, React, TS, Tailwind, enrutado, setup PWA)
- Sondeo de versión (version.json, ciclo de 2 minutos) y estrategia de caché
```

---

## Historial de cambios de este documento

### [1.1.0] - 2026-06-23
**Removed**
- Funcionalidad **Spotted** eliminada por completo (esquema, ruta, componentes,
  ruta de escritura del backend, fase).
- Micro-backend obligatorio eliminado — el funcionamiento es ahora puramente
  estático; el web push queda solo como ampliación opcional.

**Added**
- §6 **configuración de fuentes de datos**: elegible por elemento de menú entre
  `manual` / `joomla` / `wordpress` (incl. `content-sources.config.ts`,
  arquitectura de adaptadores, mapeo de Joomla y WordPress, seguridad).
- §6.5 **fuente de horarios conmutable**: `csv` | `joomla-customfields` | `wordpress-acf`.
- §2 **dirección de diseño** (oscuro/blanco/amarillo, imágenes de artistas 4:5,
  tokens a verificar desde el CSS de T4).

**Changed**
- Caché/sondeo unificados de 60 s/5 min a **2 minutos** (`max-age=120`,
  `refetchInterval 120_000`).
- Hoja de ruta ajustada (pipeline de datos en la fase 1, fase Spotted eliminada).

### [1.0.0] - 2026-06-22
- Primera versión.

---
