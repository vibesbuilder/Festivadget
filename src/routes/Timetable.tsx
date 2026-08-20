import { useEffect, useMemo, useRef } from "react";
import { useTranslation } from "react-i18next";
import { LayoutGrid, List } from "lucide-react";
import { useArtists, useFestival, useSlots, useStages } from "@/data/queries";
import { currentDayId } from "@/lib/currentDay";
import { useUi } from "@/store/ui";
import { LoadingState, ErrorState, EmptyState } from "@/components/states";
import { DayTabs } from "@/features/timetable/DayTabs";
import { TimetableGrid } from "@/features/timetable/TimetableGrid";
import { TimetableList } from "@/features/timetable/TimetableList";
import { useClashes } from "@/features/timetable/useClashes";
import { useFavorites } from "@/store/favorites";

export default function Timetable() {
  const { t } = useTranslation();
  const { data: festival, isLoading: l1, isError, refetch } = useFestival();
  const { data: stages, isLoading: l2 } = useStages();
  const { data: artists, isLoading: l3 } = useArtists();
  const { data: slots, isLoading: l4 } = useSlots();

  const selectedDayId = useUi((s) => s.selectedDayId);
  const setSelectedDay = useUi((s) => s.setSelectedDay);
  const view = useUi((s) => s.timetableView);
  const setView = useUi((s) => s.setTimetableView);
  const favoritesOnly = useUi((s) => s.favoritesOnly);
  const setFavoritesOnly = useUi((s) => s.setFavoritesOnly);
  const hiddenStageIds = useUi((s) => s.hiddenStageIds);
  const toggleStageHidden = useUi((s) => s.toggleStageHidden);
  const favorites = useFavorites((s) => s.favorites);

  // On open, always select today's festival day (04:00 boundary);
  // outside the festival the first day. Free selection afterwards.
  const days = festival?.days ?? [];
  const dayApplied = useRef(false);
  useEffect(() => {
    if (days.length === 0) return;
    if (!dayApplied.current) {
      dayApplied.current = true;
      setSelectedDay(currentDayId(days) ?? days[0].id);
      return;
    }
    // Safeguard: correct a selection that became invalid (e.g. data change).
    if (!days.some((d) => d.id === selectedDayId)) {
      setSelectedDay(days[0].id);
    }
  }, [days, selectedDayId, setSelectedDay]);

  const activeDay = days.find((d) => d.id === selectedDayId) ?? days[0];

  const clashes = useClashes(slots);

  const hidden = useMemo(() => new Set(hiddenStageIds), [hiddenStageIds]);
  const visibleStages = useMemo(
    () => (stages ?? []).filter((s) => !hidden.has(s.id)),
    [stages, hidden],
  );

  const { artistById, stageById, daySlots } = useMemo(() => {
    const aById = new Map((artists ?? []).map((a) => [a.id, a]));
    const sById = new Map((stages ?? []).map((s) => [s.id, s]));
    let ds = (slots ?? []).filter((s) => s.dayId === activeDay?.id && !hidden.has(s.stageId));
    if (favoritesOnly) ds = ds.filter((s) => favorites.has(s.id));
    return { artistById: aById, stageById: sById, daySlots: ds };
  }, [artists, stages, slots, activeDay, favoritesOnly, favorites, hidden]);

  if (l1 || l2 || l3 || l4) return <LoadingState />;
  if (isError || !festival || !stages) return <ErrorState onRetry={() => void refetch()} />;
  if (!activeDay) return <ErrorState />;

  return (
    <section className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">{t("timetable.title")}</h1>
        <div className="flex gap-1">
          <button
            onClick={() => setView("grid")}
            aria-label={t("timetable.grid")}
            className={`rounded-lg p-2 ${view === "grid" ? "bg-rid-accent text-black" : "bg-rid-surface-2 text-rid-muted"}`}
          >
            <LayoutGrid size={18} />
          </button>
          <button
            onClick={() => setView("list")}
            aria-label={t("timetable.list")}
            className={`rounded-lg p-2 ${view === "list" ? "bg-rid-accent text-black" : "bg-rid-surface-2 text-rid-muted"}`}
          >
            <List size={18} />
          </button>
        </div>
      </div>

      <DayTabs days={days} selectedId={activeDay.id} onSelect={setSelectedDay} />

      {/* Show/hide stages */}
      <div className="flex flex-wrap gap-2">
        {stages.map((stage) => {
          const isVisible = !hidden.has(stage.id);
          return (
            <button
              key={stage.id}
              onClick={() => toggleStageHidden(stage.id)}
              aria-pressed={isVisible}
              className={`rid-chip ${isVisible ? "" : "opacity-40 line-through"}`}
            >
              <span
                className="mr-1.5 inline-block h-2.5 w-2.5 rounded-full align-middle"
                style={{ backgroundColor: stage.color }}
              />
              {stage.shortName}
            </button>
          );
        })}
      </div>

      <label className="ml-2 flex items-center gap-2 text-sm text-rid-muted">
        <input
          type="checkbox"
          checked={favoritesOnly}
          onChange={(e) => setFavoritesOnly(e.target.checked)}
          className="accent-rid-accent"
        />
        {t("timetable.favoritesOnly")}
      </label>

      {visibleStages.length === 0 ? (
        <EmptyState label={t("timetable.allHidden")} />
      ) : view === "grid" ? (
        <TimetableGrid
          day={activeDay}
          stages={visibleStages}
          slots={daySlots}
          artistById={artistById}
          clashes={clashes}
        />
      ) : (
        <TimetableList
          slots={daySlots}
          artistById={artistById}
          stageById={stageById}
          clashes={clashes}
        />
      )}
    </section>
  );
}
