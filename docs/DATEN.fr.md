# Gérer & connecter les données

**🇩🇪 [Deutsch](DATEN.de.md) · 🇬🇧 [English](DATEN.md) · 🇪🇸 [Español](DATEN.es.md)**

Ce guide explique deux choses :

1. **Activer la connexion Joomla** – récupérer automatiquement certaines sections depuis un site Joomla.
2. **Remplacer les données d'exemple par de vraies données** – p. ex. programme, line-up, infos.

> Principe de base (IMPLEMENTATION.de.md §6) : en fonctionnement, l'app est
> **purement statique**. Les données sont récupérées **au moment du build**
> depuis les sources configurées, normalisées vers le schéma (§7) et déposées
> en `public/data/*.json`. L'app en cours d'exécution ne lit plus que ces
> fichiers JSON.

Le déroulé est toujours :

```bash
npm run import      # récupère les données selon content-sources.config.ts -> public/data/*.json
npm run build:data  # valide + génère version.json (hashes)
npm run build       # build de production vers dist/  (seulement pour le déploiement)
```

---

## 1. Activer la connexion Joomla

### 1.1 Prérequis dans Joomla

Dans Joomla 5, activer les **Web Services / API** et créer un **jeton d'API** :

- Activer *Système → Configuration globale → API*.
- Sur l'utilisateur (de préférence un compte **en lecture seule**), sous
  *Modifier → Jeton d'API*, générer un jeton.
- Les contenus requis (articles) se trouvent dans des **catégories** – noter
  les **IDs de catégories** (p. ex. line-up = 12, actus = 20). L'ID figure dans
  l'URL de la catégorie dans le backend (`&id=…`).

### 1.2 Mettre le jeton dans `.env` (ne jamais commiter !)

Le `.env` est un simple fichier texte à la **racine du projet**. Copier le
modèle :

```bash
cp .env.example .env          # macOS/Linux/Git Bash
```
```bat
copy .env.example .env        :: Windows (cmd / PowerShell)
```

Puis y saisir le vrai jeton Joomla (une ligne, **pas** d'espace autour du `=`,
pas de guillemets) :

```ini
JOOMLA_API_TOKEN=ton-vrai-jeton
```

- Le **nom** `JOOMLA_API_TOKEN` doit correspondre au `tokenEnv` de
  `content-sources.config.ts` (défaut : `tokenEnv: "JOOMLA_API_TOKEN"`).
- La **valeur** est le jeton d'API généré dans Joomla en 1.1.
- `npm run import` charge le `.env` **automatiquement** (Node
  `process.loadEnvFile`) et transmet le jeton à l'adaptateur Joomla (en-tête
  HTTP `Authorization: Bearer …`).

`.env` est dans `.gitignore` – les jetons n'arrivent jamais dans le dépôt ni
dans le navigateur (l'import ne tourne qu'en local/au build, le jeton reste sur
ta machine).

### 1.3 Changer la source par section (`content-sources.config.ts`)

On choisit ici **par domaine de contenu** d'où viennent les données
(`manual` | `joomla` | `wordpress`). Exemple : line-up et actus depuis Joomla,
le reste manuel :

```ts
joomla: { baseUrl: "https://example.com", tokenEnv: "JOOMLA_API_TOKEN" },
bindings: {
  artists: { provider: "joomla", joomla: { categoryId: 12 } },
  news:    { provider: "joomla", joomla: { categoryId: 20 } },
  // tout le reste reste "manual" :
  festival: { provider: "manual" },
  stages:   { provider: "manual" },
  slots:    { provider: "manual", format: "csv" },
  // ...
}
```

> Selon CLAUDE.md, `content-sources.config.ts` est une zone **soumise à
> confirmation** – modifier en connaissance de cause.

#### Infos : source **par sous-élément**

Les pages d'infos peuvent être liées à une source propre par entrée.
`info.default` fournit la **structure + les textes** (`content/info.json` :
`id`, `icon`, `order`, `hidden`, titre/texte de repli). Dans `info.overrides`,
une autre source peut être choisie par **ID d'entrée** – elle ne fournit alors
que le **titre/texte**, la structure reste celle de `default` :

```ts
info: {
  default: { provider: "manual" }, // content/info.json
  overrides: {
    // texte de la page "parken" depuis l'article Joomla 42, ordre/icône/hidden restent de content/info.json :
    parken: { provider: "joomla", joomla: { ids: [42] } },
    // "platzordnung" depuis WordPress :
    platzordnung: { provider: "wordpress", wordpress: { postType: "page", acf: { body: "inhalt" } } },
  },
},
```

**Visibilité :** chaque entrée d'info peut être retirée du menu **et** de la
recherche avec `"hidden": true` (dans `content/info.json`) – la page reste
accessible par lien direct (`/info/<id>`) (pratique pour préparer/prévisualiser).

### 1.4 Importer

```bash
npm run import && npm run build:data
```

L'adaptateur Joomla (`scripts/adapters/joomla.ts`) appelle
`{baseUrl}/api/index.php/v1/content/articles?filter[category]={id}` avec le
jeton `Bearer`, nettoie le HTML (`scripts/lib/normalize.ts`) et mappe vers le
schéma.

### 1.5 Associer les champs (custom fields)

Les **custom fields** Joomla (p. ex. horaires de scène sur l'article artiste)
sont mappés vers les champs du schéma via `customFields` :

```ts
artists: {
  provider: "joomla",
  joomla: {
    categoryId: 12,
    customFields: { country: "land", spotifyEmbedId: "spotify" },
  },
},
```

Si les **créneaux du programme** doivent venir directement des articles
artistes (au lieu du CSV), voir §6.5 dans IMPLEMENTATION.de.md – mettre
`slots.format` sur `"joomla-customfields"` (ce chemin est encore affiné dans
l'adaptateur ; actuellement, la voie la plus robuste pour le programme est le
CSV, voir plus bas).

> **Note sur l'état actuel :** l'adaptateur Joomla/WordPress fournit déjà un
> mapping générique (id, slug, name, body, image, custom fields). Le mapping
> fin *spécifique au domaine* (quel champ Joomla devient exactement quel champ
> artiste/actu) est volontairement resté simple et peut être adapté par projet
> dans `scripts/adapters/joomla.ts`.

---

## 2. Remplacer les données d'exemple par de vraies données

Tant qu'une section est sur `provider: "manual"`, les données viennent du
dossier [`content/`](../content/). Il suffit d'y remplir les fichiers
d'exemple avec de vrais contenus et d'exécuter
`npm run import && npm run build:data`.

| Section | Fichier | Format |
|---|---|---|
| Festival/jours | `content/festival.json` | objet |
| Scènes | `content/stages.json` | tableau |
| Artistes/line-up | `content/artists.json` | tableau |
| **Programme** | `content/slots.csv` | CSV |
| POI de la carte | `content/pois.json` | tableau |
| Catégories de POI | `content/poi-categories.json` | tableau |
| Plan du site | `content/map.json` (+ `public/map/…`) | objet + image |
| Actus | `content/news.json` | tableau |
| Sponsors | `content/sponsors.json` (+ `public/img/sponsors/…`) | tableau |
| Infos | `content/info.json` | tableau (Markdown dans `body`) |
| Billets | `content/tickets.json` | objet |
| Météo | `content/weather.json` | objet |

Les descriptions exactes des champs sont dans `src/types/index.ts` ou
IMPLEMENTATION.de.md §7.

### 2.0 Artistes (`content/artists.json`)

- **`spotify`** (optionnel) : intègre un lecteur Spotify sur la page artiste.
  Tu peux saisir librement ce que tu copies depuis Spotify :
  - le **lien de partage** (`Partager → Copier le lien`), p. ex. `https://open.spotify.com/artist/XXXX?si=…`
  - le **code d'intégration** complet (`Partager → Intégrer → Copier le code`, tout l'`<iframe …>`)
  - ou en abrégé `artist/XXXX` resp. `track/XXXX`, `album/XXXX`, `playlist/XXXX`

  ```json
  { "slug": "greeen", "name": "GReeeN",
    "spotify": "https://open.spotify.com/artist/4LM5wjVbpvUS6kU5dejdMS" }
  ```
- **`youtube`** (optionnel) : intègre une vidéo YouTube sous le lecteur
  Spotify. Autorisés : le lien watch (`https://www.youtube.com/watch?v=…`), le
  lien court (`https://youtu.be/…`), l'URL d'embed, le code `<iframe>` complet
  **ou** l'ID vidéo nu à 11 caractères.

  ```json
  { "slug": "greeen", "name": "GReeeN", "youtube": "https://youtu.be/dQw4w9WgXcQ" }
  ```
- **`genres`** (optionnel) : peut être vide (`[]`) **ou totalement omis** –
  alors aucune ligne de genre n'est affichée.
- **`lineup`** (optionnel) : contrôle si l'artiste apparaît dans le
  **line-up**. Visible par défaut ; `"lineup": false` l'y masque (p. ex. des
  points de programme comme le yoga ou un quiz qui ne doivent figurer que dans
  le programme). Programme/horaires de scène non affectés.

  ```json
  { "id": "yoga", "slug": "yoga", "name": "Yoga", "lineup": false }
  ```
- **`order`** (optionnel, nombre) : définit l'**ordre de tri dans le line-up**
  – plus petit = plus en avant. Les artistes **avec** `order` viennent d'abord
  (croissant), puis tous ceux **sans** `order` automatiquement (têtes
  d'affiche d'abord, puis alphabétique). Pas besoin de tout numéroter – il
  suffit de placer ceux que tu veux positionner.

  ```json
  { "id": "bibiza", "slug": "bibiza", "name": "Bibiza", "order": 1 }
  ```
- **`isHeadliner`** (optionnel, true) : affiche un badge **« Headliner »** sur
  la carte **et** trie l'artiste vers l'avant du line-up (avant les artistes
  sans `order`).
- **`isDj`** (optionnel, true) : affiche un badge **« DJ »** (couleur
  secondaire) sur la carte – **sans** effet sur l'ordre. Combinable avec
  `isHeadliner` (alors les deux badges).

### 2.05 Actus (`content/news.json`)

Obligatoire : `id`, `title`, `body`, `category` (`info`/`safety`/`lineup`/`general`), `publishAt`.
Optionnel :
- **`expiresAt`** (ISO avec offset) : l'actu disparaît à ce moment **absolu** (identique pour tous).
- **`hideAfterFirstOpenMin`** (nombre) : masque l'actu **X minutes après la
  première ouverture de l'app sur cet appareil** (individuel par appareil –
  idéal pour l'actu de bienvenue).
- **`pinned`** (true) → en haut du fil. **`link`** → bouton : `{ "label": "…", "url": "…" }`.
- Liens **dans le texte** via Markdown : `[texte](https://…)` ou interne `[Mon planning](/favorites)`.

```json
{
  "id": "news-welcome", "title": "Bienvenue !", "body": "Ravi de te voir ici.",
  "category": "general", "publishAt": "2026-05-31T10:00:00+02:00",
  "pinned": true, "hideAfterFirstOpenMin": 10
}
```

### 2.06 Catégories de POI (`content/poi-categories.json`)

Catégories des points de la carte – couleur, icône et visibilité. Un POI
référence l'`id` d'une catégorie via `type`.

- **`id`** (obligatoire) : clé vers laquelle pointe `Poi.type` (p. ex.
  `parking`). **Ne pas changer après coup** – sinon les POI existants pointent
  dans le vide (rendu de repli).
- **`label`** (obligatoire) : nom affiché dans le filtre/détail. **`color`**
  (obligatoire) : couleur hex du marqueur.
- **`icon`** (obligatoire) : trois formes possibles –
  1. **Emoji** (p. ex. `🅿️`).
  2. **Chemin d'image/URL** (p. ex. `/data/uploads/zelt.svg`, téléverser via
     l'onglet « Images »). Les valeurs commençant par `/`, `http(s):`, `data:`
     ou finissant par `.svg/.png/.webp/.jpg/.gif` sont rendues comme image.
  3. **Nom d'icône Lucide** (monochrome, couleur automatiquement contrastée
     avec le marqueur). Noms disponibles :
     `ambulance`, `first-aid`, `cross`, `plus`, `utensils` (`food`), `beer`, `coffee`, `pizza`, `wine`,
     `cooking-pot`, `car`, `bus`, `train-front` (`train`), `bike`, `square-parking` (`parking`),
     `circle-parking`, `tent`, `caravan`, `music`, `mic`, `guitar`, `disc-3` (`dj`), `info`,
     `badge-info`, `ticket`, `tickets`,
     `shower-head` (`shower`), `bath`, `baby`, `dog`, `accessibility`, `credit-card`, `shopping-bag`,
     `box`, `shirt`, `wifi`, `phone`, `map-pin`, `flag`, `star`, `heart`, `flame`, `trees`, `sun`, `umbrella`,
     `door-open`, `log-out` (`exit`), `square-arrow-right` (`square-arrow-right-exit`),
     `square-arrow-out-up-right`, `shield`, `droplet`, `zap`, `anchor`, `cigarette`.

  > **Font Awesome :** les classes FA (`<i class="fa-…">`) ne sont **pas**
  > prises en charge directement (l'app n'embarque pas Font Awesome). Pour une
  > icône FA : « Download SVG » sur fontawesome.com, téléverser dans l'onglet
  > « Images » et saisir comme chemin d'image (forme 2) – ou utiliser une icône
  > Lucide adaptée (forme 3).
- **`order`** (nombre) : ordre dans la barre de filtres.
- **`hidden`** (true) : masque la catégorie **complètement** – de la carte ET
  du filtre, pour **tous** les visiteurs (interrupteur maître).

Chaque POI peut définir sa **propre** icône de marqueur avec **`icon`** (emoji
**ou** chemin d'image) ; vide = icône de la catégorie.

```json
{ "id": "parking", "label": "Parking", "color": "#9aa0a6", "icon": "🅿️", "order": 15 }
```

### 2.1 Programme (`content/slots.csv`)

Colonnes : `artistSlug,stageId,dayId,start,end,note`

```csv
artistSlug,stageId,dayId,start,end,note
greeen,main,fr,2026-07-31T21:30:00+02:00,2026-07-31T23:00:00+02:00,
bibiza,main,sa,2026-08-01T22:00:00+02:00,2026-08-01T23:30:00+02:00,
```

Important :

- `artistSlug` doit correspondre à un `slug` de `artists.json` (sinon erreur à l'import).
- `stageId` doit correspondre à un `id` de `stages.json`.
- `dayId` est l'`id` d'un jour de `festival.json` (`fr`/`sa`/`so`).
  Les passages **après minuit** (p. ex. 00:30) reçoivent le `dayId` du **jour
  précédent** – ils comptent ainsi correctement pour le bon jour de festival
  (débordement de minuit).
- Horodatages **toujours en ISO 8601 avec offset** (`+02:00` = heure d'été de Vienne).

### 2.2 Images

Déposer les images localement sous `public/img/...` et le plan du site sous
`public/map/gelaendeplan.webp`, puis les référencer par chemin dans les
fichiers JSON (p. ex. `"image": "/img/artists/bibiza.webp"`). En local plutôt
qu'en hotlink à cause du cache hors-ligne et du CORS (§6.6). Le logo d'en-tête
se trouve dans `public/img/logo.svg`.

### 2.3 Mise à jour des données en exploitation

Après `import` + `build:data`, ne téléverser que les `dist/data/*.json`
modifiés **et** `version.json` sur le serveur. L'app interroge `version.json`
toutes les 2 minutes et ne recharge que les jeux de données modifiés –
**aucun** rebuild/upload complet de l'app nécessaire (IMPLEMENTATION.de.md §15).
