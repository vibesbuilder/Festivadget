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

// 2-minute poll only while the tab is visible and online (like live news/app config).
const livePoll = () =>
  typeof document !== "undefined" &&
  document.visibilityState === "visible" &&
  navigator.onLine
    ? 120_000
    : false;

// Generic dataset hook. Loads the build state (data/<file>, selectively invalidated
// via useVersionSync, §5.2) AND live-checks a server-side override
// (data/app-<file>, written by the admin/import importer). When the override
// exists it replaces the build state - so every domain can be maintained without
// a redeploy (admin UI or server importer).
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
