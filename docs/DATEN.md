# Daten pflegen & anbinden

Diese Anleitung erklärt zwei Dinge:

1. **Joomla-Anbindung aktivieren** – einzelne Bereiche automatisch von einer Joomla-Website holen.
2. **Beispieldaten durch echte Daten ersetzen** – z. B. Timetable, Line-Up, Infos.

> Grundprinzip (IMPLEMENTATION.md §6): Im Betrieb ist die App **rein statisch**. Daten werden
> **zur Build-Zeit** aus den konfigurierten Quellen geholt, auf das Schema (§7) normalisiert und
> als `public/data/*.json` abgelegt. Die laufende App liest nur noch diese JSON-Dateien.

Der Ablauf ist immer:

```bash
npm run import      # holt Daten laut content-sources.config.ts -> public/data/*.json
npm run build:data  # validiert + erzeugt version.json (Hashes)
npm run build       # Produktions-Build nach dist/  (nur fürs Deployment)
```

---

## 1. Joomla-Anbindung aktivieren

### 1.1 Voraussetzung in Joomla

In Joomla 5 die **Web Services / API** aktivieren und einen **API-Token** anlegen:

- *System → Globale Konfiguration → API* aktivieren.
- Beim Benutzer (möglichst **read-only**-Account) unter *Bearbeiten → API-Token* einen Token erzeugen.
- Die benötigten Inhalte (Artikel) liegen in **Kategorien** – notiere dir die **Kategorie-IDs**
  (z. B. Line-Up = 12, News = 20). Die ID steht in der URL der Kategorie im Backend (`&id=…`).

### 1.2 Token in `.env` eintragen (niemals committen!)

Die `.env` ist eine einfache Textdatei im **Projektwurzel-Ordner** (`C:\Festivadget\.env`).
Vorlage kopieren:

```bash
cp .env.example .env          # macOS/Linux/Git-Bash
```
```bat
copy .env.example .env        :: Windows (cmd / PowerShell)
```

Dann in `.env` den echten Joomla-Token eintragen (eine Zeile, **kein** Leerzeichen ums `=`,
keine Anführungszeichen):

```ini
JOOMLA_API_TOKEN=dein-echter-token
```

- Der **Name** `JOOMLA_API_TOKEN` muss zum `tokenEnv` in `content-sources.config.ts` passen
  (Standard: `tokenEnv: "JOOMLA_API_TOKEN"`).
- Der **Wert** ist der in 1.1 in Joomla erzeugte API-Token.
- `npm run import` lädt die `.env` **automatisch** (Node `process.loadEnvFile`) und reicht den
  Token an den Joomla-Adapter weiter (als HTTP-Header `Authorization: Bearer …`).

`.env` ist in `.gitignore` – Tokens gelangen nie ins Repo und nie in den Browser
(der Import läuft nur lokal/beim Build, der Token bleibt auf deiner Maschine).

### 1.3 Quelle pro Bereich umstellen (`content-sources.config.ts`)

Hier wird **pro Inhaltsdomäne** gewählt, woher die Daten kommen (`manual` | `joomla` | `wordpress`).
Beispiel: Line-Up und News aus Joomla, Rest weiter manuell:

```ts
joomla: { baseUrl: "https://rockimdorf.at", tokenEnv: "JOOMLA_API_TOKEN" },
bindings: {
  artists: { provider: "joomla", joomla: { categoryId: 12 } },
  news:    { provider: "joomla", joomla: { categoryId: 20 } },
  // alles andere bleibt "manual":
  festival: { provider: "manual" },
  stages:   { provider: "manual" },
  slots:    { provider: "manual", format: "csv" },
  // ...
}
```

> `content-sources.config.ts` ist laut CLAUDE.md ein **bestätigungspflichtiger** Bereich –
> Änderungen also bewusst vornehmen.

#### Infos: Quelle **je Untermenüpunkt**

Die Info-Seiten lassen sich pro Eintrag an eine eigene Quelle binden. `info.default`
liefert die **Struktur + Texte** (`content/info.json`: `id`, `icon`, `order`, `hidden`,
Fallback-Titel/-Text). In `info.overrides` kann pro **Eintrag-ID** eine andere Quelle
gewählt werden – diese liefert dann nur **Titel/Text**, die Struktur bleibt aus `default`:

```ts
info: {
  default: { provider: "manual" }, // content/info.json
  overrides: {
    // Text der Seite "parken" aus Joomla-Artikel 42, Reihenfolge/Icon/hidden bleiben aus content/info.json:
    parken: { provider: "joomla", joomla: { ids: [42] } },
    // "platzordnung" aus WordPress:
    platzordnung: { provider: "wordpress", wordpress: { postType: "page", acf: { body: "inhalt" } } },
  },
},
```

**Sichtbarkeit:** Jeder Info-Eintrag kann mit `"hidden": true` (in `content/info.json`)
aus Menü **und** Suche ausgeblendet werden – die Seite bleibt per Direkt-Link
(`/info/<id>`) erreichbar (praktisch zum Vorbereiten/Vorschauen).

### 1.4 Importieren

```bash
npm run import && npm run build:data
```

Der Joomla-Adapter (`scripts/adapters/joomla.ts`) ruft
`{baseUrl}/api/index.php/v1/content/articles?filter[category]={id}` mit `Bearer`-Token,
bereinigt das HTML (`scripts/lib/normalize.ts`) und mappt auf das Schema.

### 1.5 Felder zuordnen (Custom Fields)

Joomla-**Custom-Fields** (z. B. Spielzeiten am Artist-Artikel) werden über `customFields`
auf Schema-Felder gemappt:

```ts
artists: {
  provider: "joomla",
  joomla: {
    categoryId: 12,
    customFields: { country: "land", spotifyEmbedId: "spotify" },
  },
},
```

Sollen die **Timetable-Slots** direkt aus Artist-Artikeln kommen (statt CSV), siehe §6.5 im
IMPLEMENTATION.md – `slots.format` auf `"joomla-customfields"` stellen (dieser Pfad wird im
Adapter noch verfeinert; aktuell ist der robusteste Weg für die Timetable die CSV, siehe unten).

> **Hinweis zum aktuellen Stand:** Der Joomla-/WordPress-Adapter liefert bereits ein
> generisches Mapping (id, slug, name, body, Bild, Custom Fields). Die *domänenspezifische*
> Feinabbildung (welches Joomla-Feld wird genau welches Artist-/News-Feld) ist bewusst schlank
> gehalten und kann pro Projekt in `scripts/adapters/joomla.ts` angepasst werden.

---

## 2. Beispieldaten durch echte Daten ersetzen

Solange ein Bereich auf `provider: "manual"` steht, kommen die Daten aus dem Ordner
[`content/`](../content/). Dort die Beispieldateien einfach mit echten Inhalten füllen und
`npm run import && npm run build:data` ausführen.

| Bereich | Datei | Format |
|---|---|---|
| Festival/Tage | `content/festival.json` | Objekt |
| Bühnen | `content/stages.json` | Array |
| Acts/Line-Up | `content/artists.json` | Array |
| **Timetable** | `content/slots.csv` | CSV |
| Karten-POIs | `content/pois.json` | Array |
| POI-Kategorien | `content/poi-categories.json` | Array |
| Geländeplan | `content/map.json` (+ `public/map/…`) | Objekt + Bild |
| News | `content/news.json` | Array |
| Sponsoren | `content/sponsors.json` (+ `public/img/sponsors/…`) | Array |
| Infos | `content/info.json` | Array (Markdown im `body`) |
| Tickets | `content/tickets.json` | Objekt |
| Wetter | `content/weather.json` | Objekt (von RastaWeather) |

Die genauen Feldbeschreibungen stehen in `src/types/index.ts` bzw. IMPLEMENTATION.md §7.

### 2.0 Acts (`content/artists.json`)

- **`spotify`** (optional): bettet einen Spotify-Player auf der Artist-Seite ein. Du kannst
  flexibel eintragen, was du aus Spotify kopierst:
  - den **Teilen-Link** (`Teilen → Link kopieren`), z. B. `https://open.spotify.com/artist/XXXX?si=…`
  - den kompletten **Embed-Code** (`Teilen → Einbetten → Code kopieren`, das ganze `<iframe …>`)
  - oder kurz `artist/XXXX` bzw. `track/XXXX`, `album/XXXX`, `playlist/XXXX`

  ```json
  { "slug": "greeen", "name": "GReeeN",
    "spotify": "https://open.spotify.com/artist/4LM5wjVbpvUS6kU5dejdMS" }
  ```
- **`youtube`** (optional): bettet unterhalb des Spotify-Players ein YouTube-Video ein. Erlaubt
  ist der Watch-Link (`https://www.youtube.com/watch?v=…`), der Kurz-Link (`https://youtu.be/…`),
  die Embed-URL, der komplette `<iframe>`-Embed-Code **oder** die nackte 11-stellige Video-ID.

  ```json
  { "slug": "greeen", "name": "GReeeN", "youtube": "https://youtu.be/dQw4w9WgXcQ" }
  ```
- **`genres`** (optional): darf leer (`[]`) sein **oder ganz weggelassen** werden – dann wird
  einfach keine Genre-Zeile angezeigt.
- **`lineup`** (optional): steuert, ob der Act im **Line-Up** erscheint. Standard ist sichtbar;
  mit `"lineup": false` blendet man ihn dort aus (z. B. Programmpunkte wie Yoga oder Pub-Quiz,
  die nur im Timetable stehen sollen). Timetable/Spielzeiten sind davon unberührt.

  ```json
  { "id": "yoga", "slug": "yoga", "name": "Yoga", "lineup": false }
  ```
- **`order`** (optional, Zahl): legt die **Sortierreihenfolge im Line-Up** fest – kleinere Zahl
  steht weiter vorn. Acts **mit** `order` kommen zuerst (aufsteigend), danach alle **ohne** `order`
  automatisch (Headliner zuerst, dann alphabetisch). Du musst also nicht alle nummerieren –
  es reicht, die zu setzen, die du gezielt platzieren willst.

  ```json
  { "id": "bibiza", "slug": "bibiza", "name": "Bibiza", "order": 1 }
  ```
- **`isHeadliner`** (optional, true): zeigt ein **„Headliner"-Badge** auf der Karte **und** sortiert
  den Act im Line-Up nach vorn (vor die Acts ohne `order`).
- **`isDj`** (optional, true): zeigt ein **„DJ"-Badge** (Sekundärfarbe) auf der Karte – **ohne**
  Auswirkung auf die Reihenfolge. Kombinierbar mit `isHeadliner` (dann beide Badges).

### 2.05 News (`content/news.json`)

Pflicht: `id`, `title`, `body`, `category` (`info`/`safety`/`lineup`/`general`), `publishAt`.
Optional:
- **`expiresAt`** (ISO mit Offset): News verschwindet ab diesem **absoluten** Zeitpunkt (für alle gleich).
- **`hideAfterFirstOpenMin`** (Zahl): blendet die News **X Minuten nach dem ersten App-Öffnen
  dieses Geräts** aus (pro Gerät individuell – ideal für die Willkommen-News).
- **`pinned`** (true) → oben im Feed. **`link`** → Button: `{ "label": "…", "url": "…" }`.
- Links **im Text** via Markdown: `[Text](https://…)` oder intern `[Mein Plan](/favorites)`.

```json
{
  "id": "news-welcome", "title": "Willkommen!", "body": "Schön, dass du da bist.",
  "category": "general", "publishAt": "2026-05-31T10:00:00+02:00",
  "pinned": true, "hideAfterFirstOpenMin": 10
}
```

### 2.06 POI-Kategorien (`content/poi-categories.json`)

Kategorien der Karten-Punkte – Farbe, Icon und Sichtbarkeit. Ein POI verweist über
`type` auf die `id` einer Kategorie.

- **`id`** (Pflicht): Schlüssel, auf den `Poi.type` zeigt (z. B. `parking`). **Nicht nachträglich
  ändern** – sonst zeigen bestehende POIs ins Leere (Fallback-Darstellung).
- **`label`** (Pflicht): Anzeigename in Filter/Detail. **`color`** (Pflicht): Hex-Farbe des Markers.
- **`icon`** (Pflicht): drei Formen möglich –
  1. **Emoji** (z. B. `🅿️`).
  2. **Bildpfad/URL** (z. B. `/data/uploads/zelt.svg`, via Tab „Bilder" hochladen). Werte, die mit
     `/`, `http(s):`, `data:` beginnen oder auf `.svg/.png/.webp/.jpg/.gif` enden, werden als Bild gerendert.
  3. **Lucide-Icon-Name** (einfarbig, Farbe automatisch kontrastreich zum Marker). Verfügbare Namen:
     `ambulance`, `first-aid`, `cross`, `plus`, `utensils` (`food`), `beer`, `coffee`, `pizza`, `wine`,
     `cooking-pot`, `car`, `bus`, `train-front` (`train`), `bike`, `square-parking` (`parking`),
     `circle-parking`, `tent`, `caravan`, `music`, `mic`, `guitar`, `disc-3` (`dj`), `info`,
     `badge-info`, `ticket`, `tickets`,
     `shower-head` (`shower`), `bath`, `baby`, `dog`, `accessibility`, `credit-card`, `shopping-bag`,
     `box`, `shirt`, `wifi`, `phone`, `map-pin`, `flag`, `star`, `heart`, `flame`, `trees`, `sun`, `umbrella`,
     `door-open`, `log-out` (`exit`), `square-arrow-right` (`square-arrow-right-exit`),
     `square-arrow-out-up-right`, `shield`, `droplet`, `zap`, `anchor`, `cigarette`.

  > **Font Awesome:** FA-Klassen (`<i class="fa-…">`) werden **nicht** direkt unterstützt (die App
  > bündelt Font Awesome nicht). Wer ein FA-Icon will: auf fontawesome.com „Download SVG", im Tab
  > „Bilder" hochladen und als Bildpfad (Form 2) eintragen – oder ein passendes Lucide-Icon (Form 3) nutzen.
- **`order`** (Zahl): Reihenfolge in der Filterleiste.
- **`hidden`** (true): blendet die Kategorie **komplett** aus – von der Karte UND aus dem Filter,
  für **alle** Besucher (Master-Schalter).

Einzelne POIs können mit **`icon`** (Emoji **oder** Bildpfad) ein **eigenes** Marker-Icon setzen;
leer = Kategorie-Icon.

```json
{ "id": "parking", "label": "Parken", "color": "#9aa0a6", "icon": "🅿️", "order": 15 }
```

### 2.1 Timetable (`content/slots.csv`)

Spalten: `artistSlug,stageId,dayId,start,end,note`

```csv
artistSlug,stageId,dayId,start,end,note
greeen,main,fr,2026-07-31T21:30:00+02:00,2026-07-31T23:00:00+02:00,
bibiza,main,sa,2026-08-01T22:00:00+02:00,2026-08-01T23:30:00+02:00,
```

Wichtig:

- `artistSlug` muss zu einem `slug` in `artists.json` passen (sonst Fehler beim Import).
- `stageId` muss einer `id` in `stages.json` entsprechen.
- `dayId` ist die `id` eines Tages aus `festival.json` (`fr`/`sa`/`so`).
  Auftritte **nach Mitternacht** (z. B. 00:30) bekommen den `dayId` des **Vortags** – dadurch
  zählen sie korrekt zum richtigen Festivaltag (Mitternachtsüberlauf).
- Zeitstempel **immer ISO 8601 mit Offset** (`+02:00` = Sommerzeit Wien).

### 2.2 Bilder

Bilder lokal unter `public/img/...` bzw. den Geländeplan unter `public/map/gelaendeplan.webp`
ablegen und in den JSON-Dateien per Pfad referenzieren (z. B. `"image": "/img/artists/bibiza.webp"`).
Lokal statt Hotlink wegen Offline-Cache und CORS (§6.6). Das Header-Logo liegt in
`public/img/logo.svg`.

### 2.3 Daten-Update im laufenden Betrieb

Nach `import` + `build:data` nur die geänderten `dist/data/*.json` **und** `version.json` auf den
Server laden. Die App pollt `version.json` alle 2 Minuten und lädt nur geänderte Datensätze nach –
**kein** kompletter Neu-Build/Upload der App nötig (IMPLEMENTATION.md §15).
