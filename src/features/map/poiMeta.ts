import i18n from "@/i18n/config";
import type { PoiCategory, PoiType } from "@/types";

export interface PoiMeta {
  label: string;
  color: string;
  icon: string;
}

// Built-in fallback presentation per POI type (§12.4): color, short label, emoji.
// The categories from poi-categories.json (maintainable in the admin) take
// precedence; this table only applies when a category is not (yet) in the data.
// label = i18n key (poi.*), resolved in resolvePoiMeta - so the built-in
// fallback labels follow the app language; the data remains authoritative.
export const POI_META: Record<string, PoiMeta> = {
  stage: { label: "poi.stage", color: "#ffb300", icon: "🎤" },
  wc: { label: "poi.wc", color: "#5b8def", icon: "🚻" },
  food: { label: "poi.food", color: "#e4572e", icon: "🍔" },
  drink: { label: "poi.drink", color: "#e4a72e", icon: "🍺" },
  firstaid: { label: "poi.firstaid", color: "#e23b3b", icon: "➕" },
  atm: { label: "poi.atm", color: "#4caf50", icon: "🏧" },
  info: { label: "poi.info", color: "#5b8def", icon: "ℹ️" },
  entrance: { label: "poi.entrance", color: "#4caf50", icon: "🚪" },
  exit: { label: "poi.exit", color: "#9aa0a6", icon: "🚪" },
  camping: { label: "poi.camping", color: "#7da57a", icon: "⛺" },
  caravan: { label: "poi.caravan", color: "#7da57a", icon: "🚐" },
  cashless: { label: "poi.cashless", color: "#b06ef2", icon: "💳" },
  shuttle: { label: "poi.shuttle", color: "#5b8def", icon: "🚌" },
  merch: { label: "poi.merch", color: "#e4572e", icon: "👕" },
  parking: { label: "poi.parking", color: "#9aa0a6", icon: "🅿️" },
};

const GENERIC: PoiMeta = { label: "poi.generic", color: "#9aa0a6", icon: "📍" };

// Resolve a category's presentation: category data > built-in fallback > generic.
export function resolvePoiMeta(type: PoiType, categories?: Map<string, PoiCategory>): PoiMeta {
  const c = categories?.get(type);
  if (c) return { label: c.label, color: c.color, icon: c.icon };
  const builtin = POI_META[type];
  if (builtin) return { ...builtin, label: i18n.t(builtin.label) };
  return { ...GENERIC, label: type || i18n.t(GENERIC.label) };
}

// Marker icon of a POI: its own icon (poi.icon) takes precedence over the category icon.
// The value is either an emoji OR an image path/URL (e.g. /data/uploads/zelt.svg).
export function poiIcon(poiIconValue: string | undefined, meta: PoiMeta): string {
  return poiIconValue?.trim() || meta.icon;
}

// Detects whether an icon value is an image (path/URL) instead of an emoji.
export function isImageIcon(icon?: string): boolean {
  if (!icon) return false;
  const v = icon.trim();
  return /^(\/|https?:\/\/|data:)/.test(v) || /\.(svg|png|webp|jpe?g|gif|avif)$/i.test(v);
}

// Minimal escaping for insertion into Leaflet divIcon HTML.
export function escapeHtml(s: string): string {
  return s.replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

// Legible foreground color (dark/white) for a given background color.
// Used for monochrome icons (Lucide) on the colored marker circle.
export function contrastColor(hex: string): string {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
  if (!m) return "#121212";
  const n = parseInt(m[1], 16);
  const lum = (0.299 * ((n >> 16) & 255) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255;
  return lum > 0.6 ? "#121212" : "#ffffff";
}
