# Interfaz de administración (CMS)

**🇩🇪 [Deutsch](ADMIN.md) · 🇬🇧 [English](ADMIN.en.md) · 🇫🇷 [Français](ADMIN.fr.md)**

Interfaz web protegida con contraseña, alojada en el mismo hosting, para
controlar la app **sin redesplegar**. Vive en `push/cms/` y reutiliza la
autenticación existente de `push/`.

## Arquitectura

```
push/cms/index.php  ──(escribe)──►  data/app-config.json   ──(la app lee en vivo, poll de 2 min)──►  React
   │                                data/app-info.json   (fase 2)
   └─ login: adminPasswordHash      data/live-news.json  (fase 4, ya existente)
      de push/config.php
```

- **Auth:** sesión + `password_verify` contra `adminPasswordHash` de
  `push/config.php` (misma contraseña que `push/admin.php`). Token CSRF en cada
  guardado.
- **Persistencia:** archivos JSON propios del servidor en la carpeta `data/`
  (= `dataDir` de `push/config.php`). La app los lee en vivo, igual que las
  noticias en vivo de Telegram. Estos archivos pertenecen al servidor y
  **nunca** se construyen/commitean localmente (están en `.gitignore`).
- **Cliente:** `src/data/useAppConfig.ts` carga `data/app-config.json`
  (poll de 2 min; si falta el archivo, valores por defecto).

## Acceso

`https://app.rockimdorf.at/push/cms/` → iniciar sesión con la contraseña de
administrador. Tras el login se abre la pestaña **«Ajustes»** (pestaña
inicial).

## Idioma de la interfaz

El CMS está disponible en **alemán, inglés, francés y español**. El idioma se
cambia en la pestaña **«Ajustes»** → «Idioma del CMS» y aplica en el servidor
para todos los administradores (guardado en `push/cms-settings.json`, bloqueado
por `.htaccess`, nunca en el repositorio). El alemán es el idioma fuente; la
tabla de traducción está en `push/cms/i18n.php` (función `cms_t()`), las claves
que falten recaen en alemán. El **idioma de la app** lo elige cada visitante de
forma independiente dentro de la propia app (alemán/inglés/francés/español).

## Despliegue

Subir `push/cms/` por FTP a la carpeta `push/` (como el resto de `push/`).
Requisito: `push/config.php` con `adminPasswordHash` definido
(`php -r "echo password_hash('TU_CONTRASENA', PASSWORD_DEFAULT);"`) y una
carpeta `data/` **escribible** (allí ya vive `live-news.json`).

## Override en vivo genérico (fundamento)

`useDataset` (capa de consultas) carga además, para **cada** dominio,
`data/app-<dominio>.json` (poll de 2 min). Si el archivo existe, **sustituye**
el estado del build `data/<dominio>.json`. Sobre esto se apoyan tanto los
editores del admin como el importador del servidor: ambos escriben
`app-<dominio>.json`. Si falta, rige sin cambios el estado del build.
(Noticias: la pestaña «Noticias» escribe `admin-news.json`; si el archivo
existe, **sustituye** a `news.json` en el feed; el `live-news.json` de Telegram
se sigue mezclando **adicionalmente**.)

## Etapas funcionales

1. **Fundamento + menú Más** ✅ — login; visibilidad de los elementos del menú
   Más (`moreHidden[]` en `app-config.json`).
2. **Información** ✅ — activar/desactivar, renombrar, añadir/eliminar entradas
   (incl. texto, orden, icono) → `data/app-info.json`. Si el archivo existe,
   **sustituye** en el cliente el estado del build (`info.json`); el editor se
   rellena la primera vez desde `info.json` (seed). Las entradas ocultas
   (`hidden`) desaparecen del menú **y** de la búsqueda, pero siguen accesibles
   por enlace directo.
   **Acordeón de preguntas y respuestas:** casilla «Mostrar como acordeón de
   preguntas y respuestas» (`faq: true`) por entrada. Cada `## título` del
   texto se convierte en una pregunta desplegable y el texto inferior en la
   respuesta; el texto **anterior** a la primera `## pregunta` aparece como
   bloque de introducción normal. Sin `## …` en el texto sigue siendo Markdown
   normal. Utilizable en cualquier entrada (p. ej. «Cashless»).
   **Fuente por entrada:** campo `source` (`manual`/`joomla`/`wordpress`) +
   `sourceLocator` (ID de artículo de Joomla o slug/ID de WP). «Importar desde
   Joomla/WordPress» trae título/texto del artículo **solo** para las entradas
   así marcadas; la estructura y las entradas manuales se conservan. (Primero
   guardar, luego importar.)
3. **Ajustes globales** ✅ — `lineupImageLimit` (artistas con imagen),
   `background` (gráfico de fondo on/off), `themeDefault` (`dark`/`light`,
   solo aplica mientras el visitante no elija por sí mismo). En
   `app-config.json`.
4. **Noticias y push** ✅ — editor de noticias (título, texto Markdown,
   categoría, fijar, publicación/caducidad, enlace opcional) →
   `data/admin-news.json`. **Única** gestión de noticias: se rellena la primera
   vez desde `news.json` y después **sustituye** el estado del build en el feed
   (`useNewsFeed`); el `live-news.json` de Telegram se mezcla adicionalmente.
   El cron envía push automáticamente con las entradas nuevas (filtro por
   categoría, ver `docs/PUSH.es.md`). Las **categorías de push automático** se
   eligen en «Ajustes» (`pushNewsCategories`). La pestaña Push envía de
   inmediato a todas las suscripciones (`push_broadcast` de `sender.php`).
5. **Override en vivo para todos los dominios** ✅ — `useDataset` prefiere
   `data/app-<dominio>.json` (ver arriba). Fundamento para 6/7.
6. **Editores de contenido por dominio**
   - 6a ✅ Pestaña genérica **«Contenido»**: cada dominio (festival, stages,
     artists, slots, pois, map, sponsors, tickets, weather, info, news) como
     editor JSON validado (prellenado con el estado actual, comprobación
     lista/objeto, «Quitar el override» → estado del build) →
     `data/app-<dominio>.json`.
   - 6a-POI ✅ **Categorías de POI** como dominio propio («Contenido» →
     «Categorías de POI», `app-poi-categories.json`):
     `id`/`label`/`icon`(emoji)/`color`/`order`/`hidden`. Crear categorías
     propias, renombrar, **mostrar/ocultar** (`hidden` = interruptor maestro,
     desaparece por completo del mapa y del filtro). En el formulario de POI,
     `type` es un desplegable de estas categorías; por POI, opcionalmente un
     **icono propio** (`icon`, sobrescribe el de la categoría). Los **iconos**
     pueden ser: **emoji**, **ruta de imagen** (subir gráficos propios en la
     pestaña «Imágenes» → `/data/uploads/…`) o un **nombre de icono Lucide**
     (p. ej. `ambulance`, `utensils`, `parking`; lista completa en
     `docs/DATEN.es.md`) – vale para categorías y POIs individuales. Las clases
     de Font Awesome (`<i class="fa-…">`) **no** funcionan directamente; subir
     el icono FA como SVG en su lugar.
   - 6b ✅ **Subida de imágenes** (pestaña «Imágenes») → `data/uploads/`,
     servido en `/data/uploads/<nombre>`; usar la ruta en «Contenido» como
     `image`/`logo`.
   - 6c ✅ **Formularios cómodos** (en vez de JSON) para `stages`, `sponsors`,
     `pois`, `artists` (guiados por esquema) + **editor tabular de
     actuaciones** (artista/escenario/día como desplegables, horas como
     datetime-local). En la pestaña «Contenido» se alterna entre formulario y
     «Editar como JSON»; el resto de dominios sigue en JSON.
> **URL de la API de Joomla:** el importador usa la forma SEF
> `…/api/v1/content/articles` (sin `index.php`). En algunos servidores (p. ej.
> World4You) la ruta tras `index.php/` se pierde (PATH_INFO) → la forma con
> `index.php` devuelve entonces 404 en cada llamada. Requisito: `.htaccess` SEF
> activo con la línea de paso del encabezado Authorization
> (`RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`).

7. **Importador del servidor** ✅ — pestaña «Fuentes»: por dominio
   `manual`/`joomla`/`wordpress` + locator (Joomla: ID de categoría, WP: slug
   de categoría) → `data/source-config.json`. «Importar ahora»
   (`importer.php`) descarga vía curl y escribe `app-<dominio>.json`.
   Conexión/token en `push/config.php` → `sources`. **Mapeo genérico
   best-effort** (id/slug/title/name/body): adecuado para contenidos de texto;
   retocar los dominios estructurados (artistas/actuaciones/noticias) en la
   pestaña «Contenido» si hace falta. Solo controla la importación del
   servidor, **independiente** de `content-sources.config.ts` (importación del
   build). El `body` conserva **HTML saneado**: títulos, imágenes e iframes de
   hosts permitidos (YouTube/Spotify/Google Maps); imágenes `data:`, `script` e
   iframes ajenos se eliminan (`cms_clean_html`). La app lo renderiza de forma
   segura (`rehype-raw`+`rehype-sanitize`, lista blanca adicional de hosts de
   iframe en el cliente).

## Obtener un token Bearer de la API de Joomla (para el importador)

El token se **genera en Joomla** (por usuario), no se «encuentra» en ninguna
parte:

1. **Activar plugins** (Sistema → Plugins): *Web Services - Content* (habilita
   `/v1/content/articles`) y *User - Joomla API Token* (genera + verifica el
   token Bearer). *La autenticación básica* **no** es necesaria.
2. **Conceder el permiso de login de API** (Sistema → Configuración global →
   Permisos): para el grupo del usuario de API, **«Inicio de sesión de
   servicios web» (`core.login.api`) = Permitido**. Por defecto **solo** lo
   tiene el Super User → si falta, se obtiene **403 «Forbidden»**.
   Recomendación: un grupo «API» propio y mínimo (padre Public), solo con este
   permiso.
3. **Generar el token** (Usuarios → Gestionar → editar el usuario de API):
   pestaña **«Joomla API Token»** → mostrar/regenerar → copiar.
4. Introducirlo en `push/config.php` → `sources.joomla.token` (SOLO el token,
   comillas simples, SIN `Authorization: Bearer`; secreto, nunca commitear).
5. **Locator:** por entrada de información, el **ID del artículo** (Contenido →
   Artículos, columna «ID»); para la importación por dominio, el **ID de
   categoría** (Contenido → Categorías).
6. Prueba (URL **sin** `index.php`, si no puede dar 404):
   `curl -g -H "Authorization: Bearer <TOKEN>" -H "Accept: application/vnd.api+json" "https://rockimdorf.at/api/v1/content/articles"` → JSON con artículos = ok.

**Patrones de error:** 404 en todo = URL con `index.php` (PATH_INFO) o
plugin/`.htaccess` (ver arriba). 403 = grupo sin `core.login.api`. 401 = token
inválido/ausente.

## app-config.json – campos

| Campo              | Tipo                | Significado                                                 |
|--------------------|---------------------|-------------------------------------------------------------|
| `moreHidden`       | `string[]`          | Elementos ocultos del menú Más (claves, ver abajo).         |
| `lineupImageLimit` | `number?`           | Artistas con imagen en el cartel (si no, 20).               |
| `background`       | `boolean?`          | Gráfico de fondo on/off (por defecto: on).                  |
| `themeDefault`     | `"dark"\|"light"?`  | Tema por defecto mientras el visitante no elija él mismo.   |

Claves del menú Más: `news`, `map`, `info`, `sponsors`, `tickets`, `contact`,
`impressum`, `theme`, `language` (deben coincidir con `src/routes/More.tsx`).
