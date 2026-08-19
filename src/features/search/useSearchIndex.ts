import { useMemo } from "react";
import { useTranslation } from "react-i18next";
import { useArtists, usePois, usePoiCategories, useSlots, useStages } from "@/data/queries";
import { useInfo } from "@/data/useInfo";
import { formatDateTime } from "@/lib/time";
import { resolvePoiMeta } from "@/features/map/poiMeta";

export type SearchKind = "artist" | "slot" | "info" | "poi";

export interface SearchEntry {
  kind: SearchKind;
  label: string;
  sublabel: string;
  to: string;
  haystack: string; // vorgerechneter Suchtext (lowercase)
}

export interface SearchResult extends SearchEntry {
  score: number;
}

// Baut einen clientseitigen Suchindex über Artists/Slots/Info/POIs (§12.7).
export function useSearchIndex(): SearchEntry[] {
  const { i18n } = useTranslation();
  const { data: artists } = useArtists();
  const { data: slots } = useSlots();
  const { data: stages } = useStages();
  const { data: info } = useInfo();
  const { data: pois } = usePois();
  const { data: categories } = usePoiCategories();

  return useMemo(() => {
    const entries: SearchEntry[] = [];
    const artistById = new Map((artists ?? []).map((a) => [a.id, a]));
    const stageById = new Map((stages ?? []).map((s) => [s.id, s]));
    const catMap = new Map((categories ?? []).map((c) => [c.id, c]));

    for (const a of artists ?? []) {
      entries.push({
        kind: "artist",
        label: a.name,
        sublabel: (a.genres ?? []).join(" · "),
        to: `/artist/${a.slug}`,
        haystack: `${a.name} ${(a.genres ?? []).join(" ")} ${a.country ?? ""}`.toLowerCase(),
      });
    }

    for (const s of slots ?? []) {
      const artist = artistById.get(s.artistId);
      const stage = stageById.get(s.stageId);
      if (!artist) continue;
      entries.push({
        kind: "slot",
        label: `${artist.name} – ${stage?.name ?? s.stageId}`,
        sublabel: formatDateTime(s.start, undefined, i18n.language),
        to: `/artist/${artist.slug}`,
        haystack: `${artist.name} ${stage?.name ?? ""}`.toLowerCase(),
      });
    }

    for (const p of (info ?? []).filter((x) => !x.hidden)) {
      entries.push({
        kind: "info",
        label: p.title,
        sublabel: "Info",
        to: `/info/${p.id}`,
        haystack: `${p.title} ${p.body}`.toLowerCase(),
      });
    }

    for (const poi of pois ?? []) {
      const catLabel = resolvePoiMeta(poi.type, catMap).label;
      entries.push({
        kind: "poi",
        label: poi.name,
        sublabel: catLabel,
        to: "/map",
        haystack: `${poi.name} ${catLabel} ${poi.description ?? ""}`.toLowerCase(),
      });
    }

    return entries;
  }, [artists, slots, stages, info, pois, categories, i18n.language]);
}

// Token-/Substring-Match mit einfachem Scoring.
export function runSearch(index: SearchEntry[], query: string): SearchResult[] {
  const tokens = query.trim().toLowerCase().split(/\s+/).filter(Boolean);
  if (tokens.length === 0) return [];

  const results: SearchResult[] = [];
  for (const entry of index) {
    let score = 0;
    let matchedAll = true;
    for (const token of tokens) {
      const idx = entry.haystack.indexOf(token);
      if (idx === -1) {
        matchedAll = false;
        break;
      }
      // Treffer am Wortanfang höher gewichten.
      score += idx === 0 || entry.haystack[idx - 1] === " " ? 3 : 1;
    }
    if (matchedAll) results.push({ ...entry, score });
  }

  return results.sort((a, b) => b.score - a.score).slice(0, 30);
}
