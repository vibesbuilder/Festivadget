// Normalisiertes, quellenunabhängiges Datenschema (IMPLEMENTATION.md §7).
// Alle Zeitstempel: ISO 8601 mit Offset (z. B. "2026-07-31T22:00:00+02:00").
//
// Die geteilten Schedule-Typen (Festival/Day/Stage/Artist/Slot) liegen in @rid/core
// und werden hier re-exportiert, damit App-Importe `@/types` unverändert bleiben.
// Festivadget-spezifische Typen (POIs, News, Sponsoren, Infos, Tickets, Wetter,
// Versionsmanifest) stehen weiterhin hier.

export type { Festival, FestivalDay, Stage, Artist, ArtistLinks, Slot } from "@rid/core";

// §7.5 pois.json
// POI-Kategorie-Schlüssel = Poi.type. Datengetrieben über poi-categories.json
// (früher feste Union); daher offener String, damit eigene Kategorien möglich sind.
export type PoiType = string;

export interface Poi {
  id: string;
  type: PoiType; // verweist auf PoiCategory.id
  name: string;
  description?: string;
  x: number; // Pixelkoordinate (CRS.Simple)
  y: number;
  stageId?: string;
  icon?: string; // optionales Emoji je POI; überschreibt das Kategorie-Icon
}

// §7.5b poi-categories.json – Kategorien der Karten-Punkte (im Admin pflegbar)
export interface PoiCategory {
  id: string; // = Poi.type
  label: string;
  color: string; // Hex-Farbe des Markers
  icon: string; // Emoji-Marker
  hidden?: boolean; // true = komplett aus Karte UND Filter (Master-Schalter)
  order?: number; // Reihenfolge in der Filterleiste
}

// §7.6 map.json
export interface MapConfig {
  image: string;
  width: number;
  height: number;
  minZoom: number; // weitestes Heraus-Zoomen (maximum zoom-out)
  maxZoom: number; // weitestes Hinein-Zoomen
  startZoom?: number; // Anfangs-Zoom (ohne Wert: Bild einpassen). Liegt typ. über minZoom.
}

// §7.7 news.json
export type NewsCategory = "info" | "safety" | "lineup" | "general";

export interface NewsItem {
  id: string;
  title: string;
  body: string;
  category: NewsCategory;
  publishAt: string; // erst ab diesem Zeitpunkt sichtbar
  expiresAt?: string;
  pinned?: boolean;
  // Ausblenden X Minuten nach dem ersten App-Öffnen dieses Geräts (z. B. Willkommen-News).
  hideAfterFirstOpenMin?: number;
  image?: string;
  link?: { label: string; url: string };
}

// §7.8 sponsors.json
export type SponsorTier = "main" | "premium" | "partner" | "supporter";

export interface Sponsor {
  id: string;
  name: string;
  logo: string;
  tier: SponsorTier;
  url?: string;
  order: number;
}

// §7.9 info.json
export interface InfoPage {
  id: string; // "anreise" | "gelaende" | "camping" | ...
  title: string;
  icon?: string;
  order: number;
  body: string; // Markdown
  hidden?: boolean; // true = im Menü/Suche ausgeblendet (Struktur bleibt erhalten)
  faq?: boolean; // true = Body als Frage/Antwort-Accordion (## = Frage); Text vor der 1. Frage = Intro
}

// §7.10 tickets.json
export interface TicketProvider {
  id: string;
  name: string;
  embedType: "iframe" | "link";
  url: string;
  note?: string;
}

export interface TicketsConfig {
  providers: TicketProvider[];
}

// §7.11 weather.json (von RastaWeather befüllt)
export interface WeatherDay {
  dayId: string;
  date: string;
  tempMin: number;
  tempMax: number;
  symbolCode: string;
  precipitationProb?: number;
  summary?: string;
}

export interface Weather {
  generatedAt: string;
  source: "open-meteo" | "geosphere";
  days: WeatherDay[];
}

// version.json (§5.2)
export type DatasetKey =
  | "festival"
  | "artists"
  | "stages"
  | "slots"
  | "pois"
  | "map"
  | "news"
  | "sponsors"
  | "info"
  | "tickets"
  | "weather"
  | "poi-categories";

export interface VersionManifest {
  generatedAt: string;
  datasets: Record<DatasetKey, string>;
}
