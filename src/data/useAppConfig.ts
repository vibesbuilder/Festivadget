import { useQuery } from "@tanstack/react-query";
import { fetchJson } from "./fetchJson";
import type { Branding } from "@/lib/branding";

// Server-side app configuration (data/app-config.json), written by the PHP admin
// and read live (2-minute poll, like the Telegram live news). If the file is
// missing (e.g. locally/dev or before the admin saved anything), defaults apply.
export interface AppConfig {
  // Keys of the entries hidden in the MORE menu (e.g. ["map", "tickets"]).
  moreHidden: string[];
  // Global settings (phase 3) - optional, otherwise client defaults apply.
  lineupImageLimit?: number; // acts with an image in the line-up (else LINEUP_IMAGE_LIMIT)
  background?: boolean; // background artwork on/off (default: on)
  backgroundImage?: string; // eigenes Hintergrundbild (/data/uploads/…, leer = Build-Grafik)
  homeHeader?: boolean; // Home-Kopf: Festivalname + Datum (Default: an)
  themeDefault?: "dark" | "light"; // default theme while the user has not chosen themselves
  languageDefault?: string; // default language (de/en/fr/es) while the user has not chosen themselves
  branding?: Branding; // Kunden-Branding (CMS-Tab „Branding", Paket Y)
  // Intro video on home (CMS tab "Branding"): link/FTP or Microsoft cloud.
  homeVideo?: { url: string; source?: "link" | "mscloud"; enabled?: boolean };
  // Targets of the MORE entries contact and legal notice (CMS -> settings).
  // Without a value the entry stays hidden: every instance has its own legal
  // notice, a hardwired link would simply be wrong there.
  contactUrl?: string;
  impressumUrl?: string;
}

const DEFAULT_CONFIG: AppConfig = { moreHidden: [] };

export function useAppConfig(): AppConfig {
  const { data } = useQuery<AppConfig>({
    queryKey: ["app-config"],
    queryFn: async ({ signal }) => {
      try {
        const raw = await fetchJson<Partial<AppConfig>>("app-config.json", signal);
        return { ...DEFAULT_CONFIG, ...raw, moreHidden: raw.moreHidden ?? [] };
      } catch {
        // File missing / offline -> defaults (everything visible).
        return DEFAULT_CONFIG;
      }
    },
    refetchInterval: () =>
      typeof document !== "undefined" &&
      document.visibilityState === "visible" &&
      navigator.onLine
        ? 120_000
        : false,
    staleTime: 0,
  });
  return data ?? DEFAULT_CONFIG;
}
