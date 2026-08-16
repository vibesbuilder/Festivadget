# Mantener y conectar datos

**🇩🇪 [Deutsch](DATEN.md) · 🇬🇧 [English](DATEN.en.md) · 🇫🇷 [Français](DATEN.fr.md)**

Esta guía explica dos cosas:

1. **Activar la conexión con Joomla**: traer secciones concretas automáticamente desde un sitio Joomla.
2. **Sustituir los datos de ejemplo por datos reales**: p. ej. horarios, cartel, información.

> Principio básico (IMPLEMENTATION.md §6): en funcionamiento, la app es
> **puramente estática**. Los datos se obtienen **en tiempo de build** desde
> las fuentes configuradas, se normalizan al esquema (§7) y se guardan como
> `public/data/*.json`. La app en ejecución solo lee esos archivos JSON.

El flujo es siempre:

```bash
npm run import      # trae datos según content-sources.config.ts -> public/data/*.json
npm run build:data  # valida + genera version.json (hashes)
npm run build       # build de producción a dist/  (solo para el despliegue)
```

---

## 1. Activar la conexión con Joomla

### 1.1 Requisitos en Joomla

En Joomla 5, activar los **Web Services / API** y crear un **token de API**:

- Activar *Sistema → Configuración global → API*.
- En el usuario (preferiblemente una cuenta de **solo lectura**), en
  *Editar → Token de API*, generar un token.
- Los contenidos necesarios (artículos) están en **categorías**: anota los
  **IDs de categoría** (p. ej. cartel = 12, noticias = 20). El ID aparece en la
  URL de la categoría en el backend (`&id=…`).

### 1.2 Poner el token en `.env` (¡nunca commitear!)

El `.env` es un archivo de texto simple en la **raíz del proyecto**. Copiar la
plantilla:

```bash
cp .env.example .env          # macOS/Linux/Git Bash
```
```bat
copy .env.example .env        :: Windows (cmd / PowerShell)
```

Después, introducir en `.env` el token real de Joomla (una línea, **sin**
espacios alrededor del `=`, sin comillas):

```ini
JOOMLA_API_TOKEN=tu-token-real
```

- El **nombre** `JOOMLA_API_TOKEN` debe coincidir con `tokenEnv` en
  `content-sources.config.ts` (por defecto: `tokenEnv: "JOOMLA_API_TOKEN"`).
- El **valor** es el token de API generado en Joomla en 1.1.
- `npm run import` carga el `.env` **automáticamente** (Node
  `process.loadEnvFile`) y pasa el token al adaptador de Joomla (como
  encabezado HTTP `Authorization: Bearer …`).

`.env` está en `.gitignore`: los tokens nunca llegan al repositorio ni al
navegador (la importación solo corre en local/en el build; el token queda en tu
máquina).

### 1.3 Cambiar la fuente por sección (`content-sources.config.ts`)

Aquí se elige **por dominio de contenido** de dónde vienen los datos
(`manual` | `joomla` | `wordpress`). Ejemplo: cartel y noticias desde Joomla,
el resto manual:

```ts
joomla: { baseUrl: "https://example.com", tokenEnv: "JOOMLA_API_TOKEN" },
bindings: {
  artists: { provider: "joomla", joomla: { categoryId: 12 } },
  news:    { provider: "joomla", joomla: { categoryId: 20 } },
  // todo lo demás sigue en "manual":
  festival: { provider: "manual" },
  stages:   { provider: "manual" },
  slots:    { provider: "manual", format: "csv" },
  // ...
}
```

> Según CLAUDE.md, `content-sources.config.ts` es un área que **requiere
> confirmación**: haz los cambios con intención.

#### Información: fuente **por subelemento**

Las páginas de información pueden vincularse a una fuente propia por entrada.
`info.default` aporta la **estructura + textos** (`content/info.json`: `id`,
`icon`, `order`, `hidden`, título/texto de respaldo). En `info.overrides` puede
elegirse otra fuente por **ID de entrada**: entonces solo aporta
**título/texto** y la estructura sigue viniendo de `default`:

```ts
info: {
  default: { provider: "manual" }, // content/info.json
  overrides: {
    // texto de la página "parken" desde el artículo 42 de Joomla; orden/icono/hidden siguen de content/info.json:
    parken: { provider: "joomla", joomla: { ids: [42] } },
    // "platzordnung" desde WordPress:
    platzordnung: { provider: "wordpress", wordpress: { postType: "page", acf: { body: "inhalt" } } },
  },
},
```

**Visibilidad:** cada entrada de información puede ocultarse del menú **y** de
la búsqueda con `"hidden": true` (en `content/info.json`); la página sigue
accesible por enlace directo (`/info/<id>`) (práctico para preparar/previsualizar).

### 1.4 Importar

```bash
npm run import && npm run build:data
```

El adaptador de Joomla (`scripts/adapters/joomla.ts`) llama a
`{baseUrl}/api/index.php/v1/content/articles?filter[category]={id}` con el
token `Bearer`, limpia el HTML (`scripts/lib/normalize.ts`) y lo mapea al
esquema.

### 1.5 Asignar campos (custom fields)

Los **custom fields** de Joomla (p. ej. horarios en el artículo del artista) se
mapean a campos del esquema mediante `customFields`:

```ts
artists: {
  provider: "joomla",
  joomla: {
    categoryId: 12,
    customFields: { country: "land", spotifyEmbedId: "spotify" },
  },
},
```

Si las **actuaciones del horario** deben venir directamente de los artículos de
artistas (en vez del CSV), ver §6.5 de IMPLEMENTATION.md: poner `slots.format`
en `"joomla-customfields"` (esta vía aún se está refinando en el adaptador;
actualmente el camino más robusto para los horarios es el CSV, ver abajo).

> **Nota sobre el estado actual:** el adaptador Joomla/WordPress ya ofrece un
> mapeo genérico (id, slug, name, body, imagen, custom fields). El mapeo fino
> *específico de dominio* (qué campo de Joomla se convierte exactamente en qué
> campo de artista/noticia) se mantiene deliberadamente simple y puede
> adaptarse por proyecto en `scripts/adapters/joomla.ts`.

---

## 2. Sustituir los datos de ejemplo por datos reales

Mientras una sección esté en `provider: "manual"`, los datos vienen de la
carpeta [`content/`](../content/). Basta con rellenar allí los archivos de
ejemplo con contenidos reales y ejecutar
`npm run import && npm run build:data`.

| Sección | Archivo | Formato |
|---|---|---|
| Festival/días | `content/festival.json` | objeto |
| Escenarios | `content/stages.json` | array |
| Artistas/cartel | `content/artists.json` | array |
| **Horarios** | `content/slots.csv` | CSV |
| POIs del mapa | `content/pois.json` | array |
| Categorías de POI | `content/poi-categories.json` | array |
| Plano del recinto | `content/map.json` (+ `public/map/…`) | objeto + imagen |
| Noticias | `content/news.json` | array |
| Patrocinadores | `content/sponsors.json` (+ `public/img/sponsors/…`) | array |
| Información | `content/info.json` | array (Markdown en `body`) |
| Entradas | `content/tickets.json` | objeto |
| Tiempo | `content/weather.json` | objeto |

Las descripciones exactas de los campos están en `src/types/index.ts` o en
IMPLEMENTATION.md §7.

### 2.0 Artistas (`content/artists.json`)

- **`spotify`** (opcional): incrusta un reproductor de Spotify en la página del
  artista. Puedes poner con flexibilidad lo que copies de Spotify:
  - el **enlace de compartir** (`Compartir → Copiar enlace`), p. ej. `https://open.spotify.com/artist/XXXX?si=…`
  - el **código de inserción** completo (`Compartir → Insertar → Copiar código`, todo el `<iframe …>`)
  - o en corto `artist/XXXX`, `track/XXXX`, `album/XXXX`, `playlist/XXXX`

  ```json
  { "slug": "greeen", "name": "GReeeN",
    "spotify": "https://open.spotify.com/artist/4LM5wjVbpvUS6kU5dejdMS" }
  ```
- **`youtube`** (opcional): incrusta un vídeo de YouTube bajo el reproductor de
  Spotify. Se permite el enlace watch (`https://www.youtube.com/watch?v=…`), el
  enlace corto (`https://youtu.be/…`), la URL de embed, el código `<iframe>`
  completo **o** el ID de vídeo de 11 caracteres a secas.

  ```json
  { "slug": "greeen", "name": "GReeeN", "youtube": "https://youtu.be/dQw4w9WgXcQ" }
  ```
- **`genres`** (opcional): puede estar vacío (`[]`) **o directamente omitirse**;
  entonces no se muestra la línea de géneros.
- **`lineup`** (opcional): controla si el artista aparece en el **cartel**. Por
  defecto es visible; con `"lineup": false` se oculta allí (p. ej. actividades
  como yoga o un quiz que solo deben salir en los horarios). Horarios/tiempos
  de actuación no se ven afectados.

  ```json
  { "id": "yoga", "slug": "yoga", "name": "Yoga", "lineup": false }
  ```
- **`order`** (opcional, número): define el **orden en el cartel**; número
  menor = más adelante. Los artistas **con** `order` van primero
  (ascendente), después todos los que **no** tienen `order` automáticamente
  (cabezas de cartel primero, luego alfabético). No hace falta numerar todo:
  basta con fijar los que quieras colocar a propósito.

  ```json
  { "id": "bibiza", "slug": "bibiza", "name": "Bibiza", "order": 1 }
  ```
- **`isHeadliner`** (opcional, true): muestra una insignia **«Headliner»** en
  la tarjeta **y** adelanta al artista en el cartel (antes de los artistas sin
  `order`).
- **`isDj`** (opcional, true): muestra una insignia **«DJ»** (color secundario)
  en la tarjeta, **sin** efecto en el orden. Combinable con `isHeadliner`
  (entonces ambas insignias).

### 2.05 Noticias (`content/news.json`)

Obligatorio: `id`, `title`, `body`, `category` (`info`/`safety`/`lineup`/`general`), `publishAt`.
Opcional:
- **`expiresAt`** (ISO con offset): la noticia desaparece en ese momento **absoluto** (igual para todos).
- **`hideAfterFirstOpenMin`** (número): oculta la noticia **X minutos después
  de la primera apertura de la app en ese dispositivo** (individual por
  dispositivo; ideal para la noticia de bienvenida).
- **`pinned`** (true) → arriba del feed. **`link`** → botón: `{ "label": "…", "url": "…" }`.
- Enlaces **dentro del texto** vía Markdown: `[texto](https://…)` o interno `[Mi plan](/favorites)`.

```json
{
  "id": "news-welcome", "title": "¡Bienvenido!", "body": "Qué bien que estés aquí.",
  "category": "general", "publishAt": "2026-05-31T10:00:00+02:00",
  "pinned": true, "hideAfterFirstOpenMin": 10
}
```

### 2.06 Categorías de POI (`content/poi-categories.json`)

Categorías de los puntos del mapa: color, icono y visibilidad. Un POI apunta
con `type` al `id` de una categoría.

- **`id`** (obligatorio): clave a la que apunta `Poi.type` (p. ej. `parking`).
  **No cambiar a posteriori**: los POIs existentes quedarían apuntando al vacío
  (representación de respaldo).
- **`label`** (obligatorio): nombre visible en filtro/detalle. **`color`**
  (obligatorio): color hex del marcador.
- **`icon`** (obligatorio): tres formas posibles:
  1. **Emoji** (p. ej. `🅿️`).
  2. **Ruta de imagen/URL** (p. ej. `/data/uploads/zelt.svg`, subir en la
     pestaña «Imágenes»). Los valores que empiezan por `/`, `http(s):`, `data:`
     o terminan en `.svg/.png/.webp/.jpg/.gif` se renderizan como imagen.
  3. **Nombre de icono Lucide** (monocromo, color con contraste automático
     respecto al marcador). Nombres disponibles:
     `ambulance`, `first-aid`, `cross`, `plus`, `utensils` (`food`), `beer`, `coffee`, `pizza`, `wine`,
     `cooking-pot`, `car`, `bus`, `train-front` (`train`), `bike`, `square-parking` (`parking`),
     `circle-parking`, `tent`, `caravan`, `music`, `mic`, `guitar`, `disc-3` (`dj`), `info`,
     `badge-info`, `ticket`, `tickets`,
     `shower-head` (`shower`), `bath`, `baby`, `dog`, `accessibility`, `credit-card`, `shopping-bag`,
     `box`, `shirt`, `wifi`, `phone`, `map-pin`, `flag`, `star`, `heart`, `flame`, `trees`, `sun`, `umbrella`,
     `door-open`, `log-out` (`exit`), `square-arrow-right` (`square-arrow-right-exit`),
     `square-arrow-out-up-right`, `shield`, `droplet`, `zap`, `anchor`, `cigarette`.

  > **Font Awesome:** las clases FA (`<i class="fa-…">`) **no** se soportan
  > directamente (la app no incluye Font Awesome). Si quieres un icono FA:
  > «Download SVG» en fontawesome.com, subirlo en la pestaña «Imágenes» e
  > introducirlo como ruta de imagen (forma 2), o usar un icono Lucide
  > equivalente (forma 3).
- **`order`** (número): orden en la barra de filtros.
- **`hidden`** (true): oculta la categoría **por completo**, del mapa Y del
  filtro, para **todos** los visitantes (interruptor maestro).

Cada POI puede fijar su **propio** icono de marcador con **`icon`** (emoji
**o** ruta de imagen); vacío = icono de la categoría.

```json
{ "id": "parking", "label": "Parking", "color": "#9aa0a6", "icon": "🅿️", "order": 15 }
```

### 2.1 Horarios (`content/slots.csv`)

Columnas: `artistSlug,stageId,dayId,start,end,note`

```csv
artistSlug,stageId,dayId,start,end,note
greeen,main,fr,2026-07-31T21:30:00+02:00,2026-07-31T23:00:00+02:00,
bibiza,main,sa,2026-08-01T22:00:00+02:00,2026-08-01T23:30:00+02:00,
```

Importante:

- `artistSlug` debe coincidir con un `slug` de `artists.json` (si no, error al importar).
- `stageId` debe corresponder a un `id` de `stages.json`.
- `dayId` es el `id` de un día de `festival.json` (`fr`/`sa`/`so`).
  Las actuaciones **después de medianoche** (p. ej. 00:30) reciben el `dayId`
  del **día anterior**; así cuentan correctamente para el día de festival
  correcto (desbordamiento de medianoche).
- Marcas de tiempo **siempre en ISO 8601 con offset** (`+02:00` = horario de verano de Viena).

### 2.2 Imágenes

Colocar las imágenes localmente en `public/img/...` y el plano del recinto en
`public/map/gelaendeplan.webp`, y referenciarlas por ruta en los archivos JSON
(p. ej. `"image": "/img/artists/bibiza.webp"`). En local en vez de hotlink por
la caché offline y el CORS (§6.6). El logo de la cabecera está en
`public/img/logo.svg`.

### 2.3 Actualización de datos en funcionamiento

Tras `import` + `build:data`, subir al servidor solo los `dist/data/*.json`
modificados **y** `version.json`. La app consulta `version.json` cada 2 minutos
y solo recarga los conjuntos de datos modificados: **no** hace falta
reconstruir/subir la app completa (IMPLEMENTATION.md §15).
