import { useMemo } from "react";
import { useTranslation } from "react-i18next";
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

// Live news (server-side via Telegram): missing file -> [] (only mixed in additionally).
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

// Admin news (maintained via the admin UI): the authoritative news source. When the
// file exists (even empty []) it replaces the build state (news.json); when it is
// missing (null), the build state applies.
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

// Generate virtual auto concert-start items from slots (§12.5).
function autoConcertItems(
  slots: Slot[],
  artistById: Map<string, Artist>,
  stageById: Map<string, Stage>,
  makeTitle: (artist: string, stage: string) => string,
): FeedItem[] {
  return slots
    .filter((s) => !s.cancelled)
    .map((s) => {
      const artist = artistById.get(s.artistId);
      const stage = stageById.get(s.stageId);
      return {
        id: `auto-${s.id}`,
        title: makeTitle(artist?.name ?? s.artistId, stage?.name ?? s.stageId),
        body: "",
        category: "lineup" as const,
        publishAt: s.start, // sichtbar ab Slot-Beginn
        auto: true,
      };
    });
}

/**
 * Merged feed (§12.5): editorial items (only when publishAt <= now and
 * expiresAt > now) + auto concert-start items (from slot.start). Descending by
 * time; only explicitly pinned items first (safety also queues in normally
 * otherwise, user request 08/2026). `safety` = pinned safety items
 * for the highlighted banner.
 */
export function useNewsFeed(): { items: FeedItem[]; safety: FeedItem[]; isLoading: boolean } {
  const { t } = useTranslation();
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

    // Admin news is the authoritative source: when present (even []) it replaces
    // the build state (news.json); when missing (null/undefined) the build state applies.
    // Live news (Telegram) is always mixed in additionally.
    const newsBase = adminNews ?? news ?? [];
    const firstOpenMs = getFirstOpenAt();
    const editorial: FeedItem[] = [...newsBase, ...(liveNews ?? [])].filter((n) => {
      const published = parse(n.publishAt) <= now;
      const notExpired = !n.expiresAt || parse(n.expiresAt) > now;
      // Hide X minutes after the first app open (per device).
      const notFirstOpenHidden =
        typeof n.hideAfterFirstOpenMin !== "number" ||
        now.toMillis() < firstOpenMs + n.hideAfterFirstOpenMin * 60_000;
      return published && notExpired && notFirstOpenHidden;
    });

    const autos = autoConcertItems(slots ?? [], artistById, stageById, (artist, stage) =>
      t("news.now", { artist, stage }),
    ).filter(
      (a) => parse(a.publishAt) <= now,
    );

    const merged = [...editorial, ...autos];

    // Sorting: only pinned first, then descending by time.
    const weight = (i: FeedItem) => (i.pinned ? 1 : 0);
    merged.sort((a, b) => {
      const w = weight(b) - weight(a);
      if (w !== 0) return w;
      return parse(b.publishAt).toMillis() - parse(a.publishAt).toMillis();
    });

    // Banner only for PINNED safety items; unpinned ones run as normal
    // cards in the feed.
    const safety = editorial.filter((i) => i.category === "safety" && i.pinned);

    return { items: merged, safety, isLoading: l1 || l2 };
  }, [news, liveNews, adminNews, slots, artists, stages, now, l1, l2, t]);
}
