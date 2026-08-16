import { useQuery, type UseQueryResult } from "@tanstack/react-query";
import type {
  Artist,
  DatasetKey,
  Festival,
  InfoPage,
  MapConfig,
  NewsItem,
  Poi,
  PoiCategory,
  Slot,
  Sponsor,
  Stage,
  TicketsConfig,
  Weather,
} from "@/types";
import { fetchJson } from "./fetchJson";

// 2-Minuten-Poll nur, wenn Tab sichtbar und online (wie Live-News/App-Config).
const livePoll = () =>
  typeof document !== "undefined" &&
  document.visibilityState === "visible" &&
  navigator.onLine
    ? 120_000
    : false;

// Generischer Dataset-Hook. Lädt den Build-Stand (data/<file>, via useVersionSync
// gezielt invalidiert, §5.2) UND prüft live einen server-eigenen Override
// (data/app-<file>, vom Admin/Import-Importer geschrieben). Liegt der Override
// vor, ersetzt er den Build-Stand – so lässt sich jede Domäne ohne Neu-Deploy
// pflegen (Admin-UI bzw. Server-Importer).
function useDataset<T>(key: DatasetKey, file: string): UseQueryResult<T> {
  const base = useQuery<T>({
    queryKey: ["data", key],
    queryFn: ({ signal }) => fetchJson<T>(file, signal),
  });
  const managed = useQuery<T | null>({
    queryKey: ["managed", key],
    queryFn: async ({ signal }) => {
      try {
        return await fetchJson<T>(`app-${file}`, signal);
      } catch {
        return null; // Override fehlt → Build-Stand
      }
    },
    refetchInterval: livePoll,
    staleTime: 0,
  });
  return { ...base, data: managed.data ?? base.data } as UseQueryResult<T>;
}

export const useFestival = () => useDataset<Festival>("festival", "festival.json");
export const useStages = () => useDataset<Stage[]>("stages", "stages.json");
export const useArtists = () => useDataset<Artist[]>("artists", "artists.json");
export const useSlots = () => useDataset<Slot[]>("slots", "slots.json");
export const usePois = () => useDataset<Poi[]>("pois", "pois.json");
export const usePoiCategories = () =>
  useDataset<PoiCategory[]>("poi-categories", "poi-categories.json");
export const useMapConfig = () => useDataset<MapConfig>("map", "map.json");
export const useNews = () => useDataset<NewsItem[]>("news", "news.json");
export const useSponsors = () => useDataset<Sponsor[]>("sponsors", "sponsors.json");
export const useInfoPages = () => useDataset<InfoPage[]>("info", "info.json");
export const useTickets = () => useDataset<TicketsConfig>("tickets", "tickets.json");
export const useWeather = () => useDataset<Weather>("weather", "weather.json");
