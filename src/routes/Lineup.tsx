import { useMemo } from "react";
import { useTranslation } from "react-i18next";
import { useArtists, useFestival, useSlots } from "@/data/queries";
import { useUi } from "@/store/ui";
import { ArtistCard } from "@/components/ArtistCard";
import { LoadingState, ErrorState, EmptyState } from "@/components/states";
import { LINEUP_IMAGE_LIMIT } from "@/config";
import { useAppConfig } from "@/data/useAppConfig";
import type { Artist } from "@/types";

// Sortierung: zuerst Acts mit gesetztem `order` (aufsteigend), danach der Rest –
// Headliner zuerst, sonst alphabetisch (§12.1).
function byLineupOrder(a: Artist, b: Artist): number {
  const ao = a.order ?? Infinity;
  const bo = b.order ?? Infinity;
  if (ao !== bo) return ao - bo;
  if (!!a.isHeadliner !== !!b.isHeadliner) return a.isHeadliner ? -1 : 1;
  return a.name.localeCompare(b.name, "de");
}

export default function Lineup() {
  const { t } = useTranslation();
  const { data: artists, isLoading, isError, refetch } = useArtists();
  const { data: slots } = useSlots();
  const { data: festival } = useFestival();
  const lineupDayId = useUi((s) => s.lineupDayId);
  const setLineupDay = useUi((s) => s.setLineupDay);

  // Zuordnung Artist -> Tage, an denen er spielt. Der Tag stammt aus slot.dayId,
  // das den Mitternachtsüberlauf (Auftritte nach 0 Uhr, vor ~8 Uhr) bereits dem
  // vorherigen Festivaltag zuordnet (§7.1, §12.2).
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

  // Vollständige, sortierte Line-Up-Liste (ohne Tagesfilter) – Basis für Rang/Bild-Limit.
  const shownSorted = useMemo(() => {
    if (!artists) return [];
    // Nur Acts mit lineup !== false anzeigen (Aktivitäten/Programmpunkte ausblendbar).
    return artists.filter((a) => a.lineup !== false).slice().sort(byLineupOrder);
  }, [artists]);

  // Die ersten N Acts (nach globaler Reihenfolge) erhalten ein Bild, alle weiteren
  // werden ohne Bild dargestellt – unabhängig vom Tagesfilter. N kommt aus dem
  // Admin (lineupImageLimit), Fallback ist die Konstante.
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

      {/* Filter nach Festivaltag */}
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
            {day.label}
          </button>
        ))}
      </div>

      {filtered.length === 0 ? (
        <EmptyState label="Keine Acts an diesem Tag." />
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
