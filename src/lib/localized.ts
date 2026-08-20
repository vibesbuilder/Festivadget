// Localized content fields (info pages, news): a field is either a plain
// string (single-language dataset, backward compatible) or a language map
// like { de: "...", en: "..." }. Resolution order: requested language →
// English → German → any remaining value (organizers usually author in
// de or en; a partial map must never render an empty page).

import type { AppLanguage } from "@/i18n/config";

export type LocalizedText = string | Partial<Record<AppLanguage, string>>;

const FALLBACK_ORDER: AppLanguage[] = ["en", "de", "fr", "es"];

export function lt(value: LocalizedText | null | undefined, lang: string): string {
  if (value == null) return "";
  if (typeof value === "string") return value;
  const direct = value[lang as AppLanguage];
  if (direct) return direct;
  for (const fallback of FALLBACK_ORDER) {
    const text = value[fallback];
    if (text) return text;
  }
  return "";
}
