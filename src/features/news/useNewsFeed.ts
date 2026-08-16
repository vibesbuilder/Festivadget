import { useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import type { Artist, NewsItem, Slot, Stage } from "@/types";
import { useArtists, useNews, useSlots, useStages } from "@/data/queries";
import { fetchJson, fetchJsonOrNull } from "@/data/fetchJson";
import { getFirstOpenAt } from "@/lib/firstOpen";
import { useNow } from "@/lib/useNow";
import { parse } from "@/lib/time";

export interface FeedItem extends NewsItem {
  auto?: boolean;
}

const polling = () =>
  typeof document !== "undefined" &&
  document.visibilityState === "visible" &&
  navigator.onLine
    ? 120_000
    : false;

// Live-News (server-seitig via Telegram): fehlende Datei → [] (nur zusätzlich gemischt).
function useLiveNews() {
  return useQuery<NewsItem[]>({
    queryKey: ["live-news"],
    queryFn: async ({ signal }) => {
      try {
        return await fetchJson<NewsItem[]>("live-news.json", signal);
      } catch {
        return [];
      }
    },
    refetchInterval: polling,
    staleTime: 0,
  });
}

// Admin-News (über die Admin-UI gepflegt): die maßgebliche News-Quelle. Liegt die
// Datei vor (auch leer []), ersetzt sie den Build-Stand (news.json); fehlt sie
// (null), gilt der Build-Stand.
function useAdminNews() {
  return useQuery<NewsItem[] | null>({
    queryKey: ["admin-news"],
    queryFn: async ({ signal }) => {
      try {
        return await fetchJsonOrNull<NewsItem[]>("admin-news.json", signal);
      } catch {
        return null;
      }
    },
    refetchInterval: polling,
    staleTime: 0,
  });
}

// Virtuelle Auto-Konzertstart-Items aus slots erzeugen (§12.5).
function autoConcertItems(
  slots: Slot[],
  artistById: Map<string, Artist>,
  stageById: Map<string, Stage>,
): FeedItem[] {
  return slots
    .filter((s) => !s.cancelled)
    .map((s) => {
      const artist = artistById.get(s.artistId);
      const stage = stageById.get(s.stageId);
      return {
        id: `auto-${s.id}`,
        title: `Jetzt: ${artist?.name ?? s.artistId} @ ${stage?.name ?? s.stageId}`,
        body: "",
        category: "lineup" as const,
        publishAt: s.start, // sichtbar ab Slot-Beginn
        auto: true,
      };
    });
}

/**
 * Gemergter Feed (§12.5): redaktionelle Items (nur wenn publishAt <= now und
 * expiresAt > now) + Auto-Konzertstart-Items (ab slot.start). Absteigend nach
 * Zeit; nur explizit gepinnte Items zuerst (auch Sicherheit reiht sich sonst
 * normal ein, User-Wunsch 08/2026). `safety` = gepinnte Sicherheits-Items
 * für das hervorgehobene Banner.
 */
export function useNewsFeed(): { items: FeedItem[]; safety: FeedItem[]; isLoading: boolean } {
  const { data: news, isLoading: l1 } = useNews();
  const { data: slots, isLoading: l2 } = useSlots();
  const { data: artists } = useArtists();
  const { data: stages } = useStages();
  const { data: liveNews } = useLiveNews();
  const { data: adminNews } = useAdminNews();
  const now = useNow(60_000);

  return useMemo(() => {
    const artistById = new Map((artists ?? []).map((a) => [a.id, a]));
    const stageById = new Map((stages ?? []).map((s) => [s.id, s]));

    // Admin-News ist die maßgebliche Quelle: liegt sie vor (auch []), ersetzt sie
    // den Build-Stand (news.json); fehlt sie (null/undefined), gilt der Build-Stand.
    // Live-News (Telegram) werden stets zusätzlich gemischt.
    const newsBase = adminNews ?? news ?? [];
    const firstOpenMs = getFirstOpenAt();
    const editorial: FeedItem[] = [...newsBase, ...(liveNews ?? [])].filter((n) => {
      const published = parse(n.publishAt) <= now;
      const notExpired = !n.expiresAt || parse(n.expiresAt) > now;
      // Ausblenden X Minuten nach erstem App-Öffnen (pro Gerät).
      const notFirstOpenHidden =
        typeof n.hideAfterFirstOpenMin !== "number" ||
        now.toMillis() < firstOpenMs + n.hideAfterFirstOpenMin * 60_000;
      return published && notExpired && notFirstOpenHidden;
    });

    const autos = autoConcertItems(slots ?? [], artistById, stageById).filter(
      (a) => parse(a.publishAt) <= now,
    );

    const merged = [...editorial, ...autos];

    // Sortierung: nur gepinnte zuerst, dann absteigend nach Zeit.
    const weight = (i: FeedItem) => (i.pinned ? 1 : 0);
    merged.sort((a, b) => {
      const w = weight(b) - weight(a);
      if (w !== 0) return w;
      return parse(b.publishAt).toMillis() - parse(a.publishAt).toMillis();
    });

    // Banner nur für GEPINNTE Sicherheits-Items; ungepinnte laufen als
    // normale Karten im Feed mit.
    const safety = editorial.filter((i) => i.category === "safety" && i.pinned);

    return { items: merged, safety, isLoading: l1 || l2 };
  }, [news, liveNews, adminNews, slots, artists, stages, now, l1, l2]);
}
