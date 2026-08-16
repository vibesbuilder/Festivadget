import type { PoiCategory, PoiType } from "@/types";

export interface PoiMeta {
  label: string;
  color: string;
  icon: string;
}

// Eingebaute Fallback-Darstellung je POI-Typ (§12.4): Farbe, Kurzlabel und Emoji.
// Maßgeblich sind die Kategorien aus poi-categories.json (im Admin pflegbar);
// diese Tabelle greift nur, wenn eine Kategorie (noch) nicht in den Daten steht.
export const POI_META: Record<string, PoiMeta> = {
  stage: { label: "Bühne", color: "#ffb300", icon: "🎤" },
  wc: { label: "WC", color: "#5b8def", icon: "🚻" },
  food: { label: "Essen", color: "#e4572e", icon: "🍔" },
  drink: { label: "Getränke", color: "#e4a72e", icon: "🍺" },
  firstaid: { label: "Sanitäter", color: "#e23b3b", icon: "➕" },
  atm: { label: "Bankomat", color: "#4caf50", icon: "🏧" },
  info: { label: "Info", color: "#5b8def", icon: "ℹ️" },
  entrance: { label: "Eingang", color: "#4caf50", icon: "🚪" },
  exit: { label: "Ausgang", color: "#9aa0a6", icon: "🚪" },
  camping: { label: "Camping", color: "#7da57a", icon: "⛺" },
  caravan: { label: "Caravan", color: "#7da57a", icon: "🚐" },
  cashless: { label: "Cashless", color: "#b06ef2", icon: "💳" },
  shuttle: { label: "Shuttle", color: "#5b8def", icon: "🚌" },
  merch: { label: "Merch", color: "#e4572e", icon: "👕" },
  parking: { label: "Parken", color: "#9aa0a6", icon: "🅿️" },
};

const GENERIC: PoiMeta = { label: "Punkt", color: "#9aa0a6", icon: "📍" };

// Darstellung einer Kategorie auflösen: Kategorien-Daten > eingebauter Fallback > generisch.
export function resolvePoiMeta(type: PoiType, categories?: Map<string, PoiCategory>): PoiMeta {
  const c = categories?.get(type);
  if (c) return { label: c.label, color: c.color, icon: c.icon };
  return POI_META[type] ?? { ...GENERIC, label: type || GENERIC.label };
}

// Marker-Icon eines POIs: eigenes Icon (poi.icon) hat Vorrang vor dem Kategorie-Icon.
// Wert ist entweder ein Emoji ODER ein Bildpfad/URL (z. B. /data/uploads/zelt.svg).
export function poiIcon(poiIconValue: string | undefined, meta: PoiMeta): string {
  return poiIconValue?.trim() || meta.icon;
}

// Erkennt, ob ein Icon-Wert ein Bild (Pfad/URL) statt eines Emojis ist.
export function isImageIcon(icon?: string): boolean {
  if (!icon) return false;
  const v = icon.trim();
  return /^(\/|https?:\/\/|data:)/.test(v) || /\.(svg|png|webp|jpe?g|gif|avif)$/i.test(v);
}

// Minimal-Escaping für das Einsetzen in Leaflet-divIcon-HTML.
export function escapeHtml(s: string): string {
  return s.replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

// Gut lesbare Vordergrundfarbe (dunkel/weiß) für eine gegebene Hintergrundfarbe.
// Genutzt für einfarbige Icons (Lucide) auf dem farbigen Marker-Kreis.
export function contrastColor(hex: string): string {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
  if (!m) return "#121212";
  const n = parseInt(m[1], 16);
  const lum = (0.299 * ((n >> 16) & 255) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255;
  return lum > 0.6 ? "#121212" : "#ffffff";
}
