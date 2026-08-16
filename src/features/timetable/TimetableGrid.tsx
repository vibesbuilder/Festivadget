import { Link } from "react-router-dom";
import { Star } from "lucide-react";
import type { Artist, FestivalDay, Slot, Stage } from "@/types";
import { formatTime } from "@/lib/time";
import { useNow } from "@/lib/useNow";
import { useFavorites } from "@/store/favorites";
import { dayRange, hourMarks, offsetPx, PX_PER_MIN } from "./layout";

interface Props {
  day: FestivalDay;
  stages: Stage[];
  slots: Slot[];
  artistById: Map<string, Artist>;
  clashes: Set<string>;
}

const GUTTER = 36; // px Breite der Zeitachse links

// Grid-Ansicht: Spalten = Stages (nach order), Reihen = Zeitachse (§12.2).
// Spalten teilen sich die volle Breite (flex-1) → passt auf 360 px ohne Scrollen;
// ausgeblendete Bühnen geben ihre Breite an die übrigen ab.
export function TimetableGrid({ day, stages, slots, artistById, clashes }: Props) {
  const range = dayRange(day);
  const marks = hourMarks(range);
  const current = useNow();
  const favorites = useFavorites((s) => s.favorites);
  const toggle = useFavorites((s) => s.toggle);

  const sortedStages = stages.slice().sort((a, b) => a.order - b.order);

  // NowLine nur zeigen, wenn die aktuelle Zeit in der Tagesspanne liegt.
  const nowMin = current.diff(range.start, "minutes").minutes;
  const showNow = nowMin >= 0 && nowMin <= range.totalMinutes;

  return (
    <div className="rid-card w-full p-2">
      {/* Stage-Kopfzeile */}
      <div className="flex" style={{ paddingLeft: GUTTER }}>
        {sortedStages.map((stage) => (
          <div
            key={stage.id}
            className="min-w-0 truncate border-b-2 px-0.5 py-1 text-center text-[11px] font-semibold"
            style={{ flex: "1 1 0", borderColor: stage.color }}
          >
            {stage.shortName}
          </div>
        ))}
      </div>

      {/* Zeitachse + Spalten */}
      <div className="relative" style={{ height: range.heightPx }}>
        {/* Stundenlinien + Zeitlabels */}
        {marks.map((m) => (
          <div key={m.label} className="absolute left-0 right-0" style={{ top: m.topPx }}>
            <div className="border-t border-rid-muted/25" style={{ marginLeft: GUTTER }} />
            <span
              className="absolute -top-1.5 left-0 text-right text-[10px] text-rid-muted"
              style={{ width: GUTTER - 3 }}
            >
              {m.label}
            </span>
          </div>
        ))}

        {/* NowLine */}
        {showNow && (
          <div
            className="pointer-events-none absolute right-0 z-20"
            style={{ left: GUTTER, top: nowMin * PX_PER_MIN }}
          >
            <div className="h-0.5 bg-rid-accent-2" />
            <span className="absolute -top-2 left-0 rounded bg-rid-accent-2 px-1 text-[9px] font-bold text-white">
              {current.toFormat("HH:mm")}
            </span>
          </div>
        )}

        {/* Spalten je Stage (flex-1 = gleiche Breite, füllt die volle Breite) */}
        <div className="absolute inset-y-0 right-0 flex" style={{ left: GUTTER }}>
          {sortedStages.map((stage) => (
            <div
              key={stage.id}
              className="relative min-w-0 border-l border-rid-border/40"
              style={{ flex: "1 1 0" }}
            >
              {slots
                .filter((s) => s.stageId === stage.id)
                .map((slot) => {
                  const top = offsetPx(slot.start, range);
                  const height = Math.max(18, offsetPx(slot.end, range) - top - 2);
                  const isFav = favorites.has(slot.id);
                  const isClash = clashes.has(slot.id);
                  const artist = artistById.get(slot.artistId);
                  return (
                    <Link
                      key={slot.id}
                      to={`/artist/${artist?.slug ?? ""}`}
                      className="absolute left-0.5 right-0.5 block overflow-hidden rounded-md border p-0.5 pr-5 text-left text-[10px] leading-tight transition-colors"
                      style={{
                        top,
                        height,
                        backgroundColor: isFav ? stage.color : "rgb(var(--rid-surface-2))",
                        color: isFav ? "#000" : "rgb(var(--rid-text))",
                        borderColor: isClash ? "rgb(var(--rid-accent-2))" : stage.color,
                        borderWidth: isClash ? 2 : 1,
                      }}
                      title={`${artist?.name ?? ""} · ${formatTime(slot.start)}–${formatTime(slot.end)}`}
                    >
                      <span className="block truncate opacity-90">{formatTime(slot.start)}</span>
                      {height > 24 && (
                        <span className="block break-words font-semibold">
                          {artist?.name ?? slot.artistId}
                        </span>
                      )}
                      {/* Stern oben-bündig zum Text: zu „Mein Plan" hinzufügen, ohne zu navigieren. */}
                      <button
                        type="button"
                        onClick={(e) => {
                          e.preventDefault();
                          e.stopPropagation();
                          toggle(slot.id);
                        }}
                        aria-label={isFav ? "Aus Mein Plan entfernen" : "Zu Mein Plan hinzufügen"}
                        aria-pressed={isFav}
                        className="absolute right-0 top-0 flex h-full w-5 items-start justify-center pt-0.5 hover:bg-black/10"
                      >
                        <Star size={12} fill={isFav ? "currentColor" : "none"} strokeWidth={2} />
                      </button>
                    </Link>
                  );
                })}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
