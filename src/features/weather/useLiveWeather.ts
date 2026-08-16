import { useQuery } from "@tanstack/react-query";

// Antwortformat von push/weather.php (GeoSphere-Daten, serverseitig gecacht).
export interface LiveWeatherSection {
  icon: string;
  maxTemp: number | null;
  precipitation: number;
  windSpeed: number | null;
  windDirectionText: string | null;
  noData: boolean;
}

export interface LiveWeatherDay {
  date: string; // YYYY-MM-DD
  icon: string; // Tages-Gesamtsymbol (Tag-Variante)
  max: number | null;
  min: number | null;
  precip: number;
  noData: boolean;
  // „night" ist die FOLGENDE Nacht (0–6 h des Folgetags), wie in CrewCare.
  sections: Record<"morning" | "noon" | "evening" | "night", LiveWeatherSection>;
}

export interface LiveWeather {
  ok: boolean;
  location: string;
  fetchedAt: string;
  stale: boolean;
  current: { temp: number | null; icon: string } | null;
  days: LiveWeatherDay[];
  attribution: string;
}

// Im Dev-Modus lokaler PHP-Server (php -S 127.0.0.1:8787 im Ordner push/),
// im Build same-origin – wie die übrigen push/-Endpoints.
const WEATHER_URL = import.meta.env.DEV
  ? "http://127.0.0.1:8787/weather.php"
  : `${import.meta.env.BASE_URL}push/weather.php`;

export function useLiveWeather() {
  return useQuery<LiveWeather>({
    queryKey: ["live-weather"],
    queryFn: async ({ signal }) => {
      const res = await fetch(WEATHER_URL, { signal });
      if (!res.ok) throw new Error(`Wetter: HTTP ${res.status}`);
      return (await res.json()) as LiveWeather;
    },
    staleTime: 10 * 60_000, // Server cached 15 min – Client muss nicht öfter fragen.
    refetchInterval: 15 * 60_000,
    retry: 1,
  });
}
