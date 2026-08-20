import { useMemo } from "react";
import { useTranslation } from "react-i18next";
import { lt } from "@/lib/localized";
import { AlertTriangle, Star } from "lucide-react";
import { useArtists, useFestival, useSlots, useStages } from "@/data/queries";
import { useFavorites } from "@/store/favorites";
import { useClashes } from "@/features/timetable/useClashes";
import { SlotCard } from "@/features/timetable/SlotCard";
import { IcsButton } from "@/features/favorites/IcsButton";
import { NotificationsToggle } from "@/components/NotificationsToggle";
import { BackLink } from "@/components/BackLink";
import { LoadingState } from "@/components/states";
import { parse } from "@/lib/time";
import type { Artist, Stage } from "@/types";

export default function Favorites() {
  const { t, i18n } = useTranslation();
  const { data: festival, isLoading: l1 } = useFestival();
  const { data: stages } = useStages();
  const { data: artists } = useArtists();
  const { data: slots, isLoading: l4 } = useSlots();
  const favorites = useFavorites((s) => s.favorites);
  const clashes = useClashes(slots);

  const artistById = useMemo(() => new Map((artists ?? []).map((a) => [a.id, a])), [artists]);
  const stageById = useMemo(() => new Map((stages ?? []).map((s) => [s.id, s])), [stages]);

  // Favorited slots chronologically, grouped by day (§12.3).
  const byDay = useMemo(() => {
    const favSlots = (slots ?? [])
      .filter((s) => favorites.has(s.id))
      .sort((a, b) => parse(a.start).toMillis() - parse(b.start).toMillis());

    const groups = new Map<string, typeof favSlots>();
    for (const s of favSlots) {
      const list = groups.get(s.dayId) ?? [];
      list.push(s);
      groups.set(s.dayId, list);
    }
    return groups;
  }, [slots, favorites]);

  // Entries for the bulk .ics export.
  const allEntries = useMemo(() => {
    return (slots ?? [])
      .filter((s) => favorites.has(s.id))
      .map((s) => ({
        slot: s,
        artist: artistById.get(s.artistId) as Artist,
        stage: stageById.get(s.stageId) as Stage,
      }))
      .filter((e) => e.artist);
  }, [slots, favorites, artistById, stageById]);

  if (l1 || l4) return <LoadingState />;

  const dayOrder = festival?.days ?? [];
  const hasClash = allEntries.some((e) => clashes.has(e.slot.id));

  if (favorites.size === 0 || allEntries.length === 0) {
    return (
      <section className="space-y-3">
        <BackLink to="/more" label={t("nav.more")} />
        <h1 className="text-2xl font-bold">{t("favorites.title")}</h1>
        <NotificationsToggle />
        {/* Explanation of the feature where the favorites would otherwise be. */}
        <div className="rid-card flex flex-col items-center gap-3 p-6 text-center">
          <Star size={32} className="text-rid-accent" />
          <p className="font-semibold">{t("favorites.emptyTitle")}</p>
          <p className="text-sm text-rid-muted">{t("favorites.explain")}</p>
        </div>
      </section>
    );
  }

  return (
    <section className="space-y-5">
      <div className="flex items-center justify-between gap-3">
        <BackLink to="/more" label={t("nav.more")} />
        <IcsButton
          entries={allEntries}
          label={t("favorites.exportAll")}
          filename="mein-plan"
          variant="primary"
        />
      </div>
      <h1 className="text-2xl font-bold">{t("favorites.title")}</h1>

      <NotificationsToggle />

      {hasClash && (
        <div className="flex items-center gap-2 rounded-xl border border-rid-accent-2 bg-rid-surface p-3 text-sm">
          <AlertTriangle size={16} className="text-rid-accent-2" />
          <span>{t("favorites.clashWarning")}</span>
        </div>
      )}

      {dayOrder.map((day) => {
        const list = byDay.get(day.id);
        if (!list || list.length === 0) return null;
        return (
          <div key={day.id} className="space-y-2">
            <h2 className="text-sm font-semibold uppercase tracking-wide text-rid-muted">
              {lt(day.label, i18n.language)}
            </h2>
            {list.map((slot) => (
              <SlotCard
                key={slot.id}
                slot={slot}
                artist={artistById.get(slot.artistId)}
                stage={stageById.get(slot.stageId)}
                isClash={clashes.has(slot.id)}
              />
            ))}
          </div>
        );
      })}
    </section>
  );
}
