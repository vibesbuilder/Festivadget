// Normalized, source-independent data schema (IMPLEMENTATION.md §7).
// All timestamps: ISO 8601 with offset (e.g. "2026-07-31T22:00:00+02:00").
//
// The shared schedule types (Festival/Day/Stage/Artist/Slot) live in @rid/core
// and are re-exported here so app imports of `@/types` stay unchanged.
// Festivadget-specific types (POIs, news, sponsors, infos, tickets, weather,
// version manifest) remain here.

export type { Festival, FestivalDay, Stage, Artist, ArtistLinks, Slot } from "@rid/core";

// §7.5 pois.json
// POI category key = Poi.type. Data-driven via poi-categories.json
// (previously a fixed union); hence an open string so custom categories are possible.
export type PoiType = string;

export interface Poi {
  id: string;
  type: PoiType; // verweist auf PoiCategory.id
  name: LocalizedText;
  description?: LocalizedText;
  x: number; // Pixelkoordinate (CRS.Simple)
  y: number;
  stageId?: string;
  icon?: string; // optional emoji per POI; overrides the category icon
}

// §7.5b poi-categories.json - categories of the map points (maintainable in the admin)
export interface PoiCategory {
  id: string; // = Poi.type
  label: LocalizedText;
  color: string; // Hex-Farbe des Markers
  icon: string; // Emoji-Marker
  hidden?: boolean; // true = removed from map AND filter entirely (master toggle)
  order?: number; // order in the filter bar
}

// §7.6 map.json
export interface MapConfig {
  image: string;
  width: number;
  height: number;
  minZoom: number; // weitestes Heraus-Zoomen (maximum zoom-out)
  maxZoom: number; // weitestes Hinein-Zoomen
  startZoom?: number; // initial zoom (without a value: fit the image). Typically above minZoom.
}

// §7.7 news.json
export type NewsCategory = "info" | "safety" | "lineup" | "general";

import type { LocalizedText } from "@/lib/localized";

export interface NewsItem {
  id: string;
  title: LocalizedText;
  body: LocalizedText;
  category: NewsCategory;
  publishAt: string; // visible only from this point in time
  expiresAt?: string;
  pinned?: boolean;
  // Hide X minutes after this device's first app open (e.g. the welcome news).
  hideAfterFirstOpenMin?: number;
  image?: string;
  link?: { label: LocalizedText; url: string };
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
  title: LocalizedText;
  icon?: string;
  order: number;
  body: LocalizedText; // Markdown
  hidden?: boolean; // true = hidden from menu/search (structure is preserved)
  faq?: boolean; // true = body as Q&A accordion (## = question); text before the 1st question = intro
}

// §7.10 tickets.json
export interface TicketProvider {
  id: string;
  name: string;
  embedType: "iframe" | "link";
  url: string;
  note?: LocalizedText;
}

export interface TicketsConfig {
  providers: TicketProvider[];
}

// §7.11 weather.json (filled by RastaWeather)
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
