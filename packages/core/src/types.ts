// @rid/core — geteilte, app-agnostische Schedule-Domänentypen (Festivadget & CrewCare).
// Festivadget-spezifische Typen (Poi, News, Info, Tickets, Weather, …) bleiben in der App.
// Alle Zeitstempel: ISO 8601 mit Offset (z. B. "2026-07-31T22:00:00+02:00").

// Lokalisierbares Textfeld: einfacher String (einsprachig, kompatibel zum
// Altbestand) ODER Sprach-Map. Auflösung in den Apps (Festivadget lt(),
// CrewCare visitor-Helfer): Sprache -> en -> de -> erster Wert.
export type LocalizedText = string | Partial<Record<"de" | "en" | "fr" | "es", string>>;

export interface FestivalDay {
  id: string; // "fr" | "sa" | "so"
  label: LocalizedText; // "Freitag 31.07." oder { de: "Freitag", en: "Friday", … }
  dayStart: string; // logischer Tagesbeginn
  dayEnd: string; // logisches Tagesende (Mitternachtsüberlauf!)
}

export interface Festival {
  name: string;
  shortName?: string; // Kurzname (z. B. "ROCK IM DORF") – Home-Bildschirm-Label & Install-Popup
  timezone: string; // "Europe/Vienna"
  start: string;
  end: string;
  days: FestivalDay[];
  contact?: { email?: string; phone?: string; web?: string };
}

export interface Stage {
  id: string;
  name: string;
  shortName: string;
  color: string;
  order: number;
  poiId?: string;
}

export interface ArtistLinks {
  spotify?: string;
  appleMusic?: string;
  bandcamp?: string;
  youtube?: string;
  instagram?: string;
  facebook?: string;
  website?: string;
}

export interface Artist {
  id: string;
  slug: string;
  name: string;
  bio?: LocalizedText;
  genres?: string[]; // optional – darf leer ([]) oder weggelassen sein
  country?: string;
  isHeadliner?: boolean;
  isDj?: boolean; // true = DJ-Badge auf der Karte (keine Auswirkung auf die Reihenfolge)
  lineup?: boolean; // false = nicht im Line-Up zeigen (Standard: sichtbar)
  order?: number; // Sortierung im Line-Up (kleiner = weiter vorn); ohne Wert: auto
  image?: string;
  gallery?: string[];
  links?: ArtistLinks;
  // Spotify-Einbettung: Share-Link, Embed-URL, kompletter Embed-Code oder "artist/<id>".
  spotify?: string;
  spotifyEmbedId?: string; // veraltet – durch `spotify` ersetzt, weiterhin unterstützt
  // YouTube-Einbettung: Watch-/Kurz-/Embed-Link, Embed-Code oder Video-ID.
  youtube?: string;
}

export interface Slot {
  id: string;
  artistId: string;
  stageId: string;
  dayId: string;
  start: string;
  end: string;
  note?: string;
  cancelled?: boolean;
}
