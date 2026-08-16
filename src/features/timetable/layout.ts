import { DateTime } from "luxon";
import type { FestivalDay } from "@/types";
import { parse } from "@/lib/time";

// Pixel pro Minute für die Grid-Höhe (kompakt, mobiltauglich).
export const PX_PER_MIN = 1.5;

export interface DayRange {
  start: DateTime;
  end: DateTime;
  totalMinutes: number;
  heightPx: number;
}

/** Zeitspanne eines Festivaltags aus dayStart/dayEnd (Mitternachtsüberlauf, §12.2). */
export function dayRange(day: FestivalDay): DayRange {
  const start = parse(day.dayStart);
  const end = parse(day.dayEnd);
  const totalMinutes = Math.max(0, end.diff(start, "minutes").minutes);
  return { start, end, totalMinutes, heightPx: totalMinutes * PX_PER_MIN };
}

/** Vertikaler Offset (px) eines ISO-Zeitpunkts relativ zum Tagesbeginn. */
export function offsetPx(iso: string, range: DayRange): number {
  return parse(iso).diff(range.start, "minutes").minutes * PX_PER_MIN;
}

/** Stundenmarken für die Zeitachse (volle Stunden innerhalb der Tagesspanne). */
export function hourMarks(range: DayRange): { label: string; topPx: number }[] {
  const marks: { label: string; topPx: number }[] = [];
  let cursor = range.start.startOf("hour");
  if (cursor < range.start) cursor = cursor.plus({ hours: 1 });
  while (cursor <= range.end) {
    marks.push({
      label: cursor.toFormat("HH:mm"),
      topPx: cursor.diff(range.start, "minutes").minutes * PX_PER_MIN,
    });
    cursor = cursor.plus({ hours: 1 });
  }
  return marks;
}
