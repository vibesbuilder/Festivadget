// Localized content fields (info pages, news): a field is either a plain
// string (single-language dataset, backward compatible) or a language map
// like { de: "...", en: "..." }. Resolution order: requested language →
// English → German → any remaining value (organizers usually author in
// de or en; a partial map must never render an empty page).

export type LocalizedText = string | Partial<Record<string, string>>;

const FALLBACK_ORDER = ["en", "de", "fr", "es"];

export function lt(value: LocalizedText | null | undefined, lang: string): string {
  if (value == null) return "";
  if (typeof value === "string") return value;
  const direct = value[lang];
  if (direct) return direct;
  for (const fallback of FALLBACK_ORDER) {
    const text = value[fallback];
    if (text) return text;
  }
  for (const text of Object.values(value)) {
    if (text) return text;
  }
  return "";
}
