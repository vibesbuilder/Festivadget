import { useMemo } from "react";
import { useTranslation } from "react-i18next";
import { lt } from "@/lib/localized";
import { useArtists, useFestival, useSlots } from "@/data/queries";
import { useUi } from "@/store/ui";
import { ArtistCard } from "@/components/ArtistCard";
import { LoadingState, ErrorState, EmptyState } from "@/components/states";
import { LINEUP_IMAGE_LIMIT } from "@/config";
import { useAppConfig } from "@/data/useAppConfig";
import type { Artist } from "@/types";

// Sorting: first acts with `order` set (ascending), then the rest -
// headliners first, otherwise alphabetical (§12.1).
function byLineupOrder(a: Artist, b: Artist): number {
  const ao = a.order ?? Infinity;
  const bo = b.order ?? Infinity;
  if (ao !== bo) return ao - bo;
  if (!!a.isHeadliner !== !!b.isHeadliner) return a.isHeadliner ? -1 : 1;
  return a.name.localeCompare(b.name, "de");
}

export default function Lineup() {
  const { t, i18n } = useTranslation();
  const { data: artists, isLoading, isError, refetch } = useArtists();
  const { data: slots } = useSlots();
  const { data: festival } = useFestival();
  const lineupDayId = useUi((s) => s.lineupDayId);
  const setLineupDay = useUi((s) => s.setLineupDay);

  // Mapping artist -> days they play on. The day comes from slot.dayId, which
  // already assigns the midnight overflow (shows after midnight, before ~8 am)
  // to the previous festival day (§7.1, §12.2).
  const daysByArtist = useMemo(() => {
    const map = new Map<string, Set<string>>();
    for (const s of slots ?? []) {
      const set = map.get(s.artistId) ?? new Set<string>();
      set.add(s.dayId);
      map.set(s.artistId, set);
    }
    return map;
  }, [slots]);

  const days = festival?.days ?? [];

  // Full, sorted line-up list (without day filter) - basis for rank/image limit.
  const shownSorted = useMemo(() => {
    if (!artists) return [];
    // Only show acts with lineup !== false (activities/program items can be hidden).
    return artists.filter((a) => a.lineup !== false).slice().sort(byLineupOrder);
  }, [artists]);

  // The first N acts (by global order) get an image, all further ones render
  // without an image - independent of the day filter. N comes from the admin
  // (lineupImageLimit), fallback is the constant.
  const { lineupImageLimit } = useAppConfig();
  const imageLimit = lineupImageLimit ?? LINEUP_IMAGE_LIMIT;
  const imageArtistIds = useMemo(
    () => new Set(shownSorted.slice(0, imageLimit).map((a) => a.id)),
    [shownSorted, imageLimit],
  );

  const filtered = useMemo(
    () =>
      lineupDayId
        ? shownSorted.filter((a) => daysByArtist.get(a.id)?.has(lineupDayId))
        : shownSorted,
    [shownSorted, lineupDayId, daysByArtist],
  );

  if (isLoading) return <LoadingState />;
  if (isError) return <ErrorState onRetry={() => void refetch()} />;
  if (!artists || artists.length === 0) return <EmptyState />;

  return (
    <section>
      <h1 className="mb-4 text-2xl font-bold">{t("lineup.title")}</h1>

      {/* Filter by festival day */}
      <div className="mb-4 flex gap-2 overflow-x-auto pb-1">
        <button
          onClick={() => setLineupDay(null)}
          className={lineupDayId === null ? "rid-chip rid-chip-active" : "rid-chip"}
        >
          {t("lineup.allGenres")}
        </button>
        {days.map((day) => (
          <button
            key={day.id}
            onClick={() => setLineupDay(day.id)}
            className={lineupDayId === day.id ? "rid-chip rid-chip-active" : "rid-chip"}
          >
            {lt(day.label, i18n.language)}
          </button>
        ))}
      </div>

      {filtered.length === 0 ? (
        <EmptyState label={t("lineup.noActs")} />
      ) : (
        <div className="grid grid-cols-2 gap-3">
          {filtered.map((artist) => (
            <ArtistCard
              key={artist.id}
              artist={artist}
              showImage={imageArtistIds.has(artist.id)}
            />
          ))}
        </div>
      )}
    </section>
  );
}
