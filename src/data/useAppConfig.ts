import { useQuery } from "@tanstack/react-query";
import { fetchJson } from "./fetchJson";

// Server-eigene App-Konfiguration (data/app-config.json), vom PHP-Admin geschrieben
// und live eingelesen (2-Minuten-Poll, wie die Telegram-Live-News). Fehlt die Datei
// (z. B. lokal/Dev oder bevor der Admin etwas gespeichert hat), gelten die Defaults.
export interface AppConfig {
  // Schlüssel der im MEHR-Menü ausgeblendeten Punkte (z. B. ["map", "tickets"]).
  moreHidden: string[];
  // Globale Einstellungen (Phase 3) – optional, sonst gelten Client-Defaults.
  lineupImageLimit?: number; // Acts mit Bild im Line-Up (sonst LINEUP_IMAGE_LIMIT)
  background?: boolean; // Hintergrundgrafik an/aus (Default: an)
  backgroundImage?: string; // eigenes Hintergrundbild (/data/uploads/…, leer = Build-Grafik)
  homeHeader?: boolean; // Home-Kopf: Festivalname + Datum (Default: an)
  themeDefault?: "dark" | "light"; // Standard-Theme, solange der User nicht selbst wählt
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
        // Datei fehlt / offline → Defaults (alles sichtbar).
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
