import { DateTime } from "luxon";
import type { FestivalDay } from "@/types";
import { parse } from "@/lib/time";

// Pixels per minute for the grid height (compact, mobile-friendly).
export const PX_PER_MIN = 1.5;

export interface DayRange {
  start: DateTime;
  end: DateTime;
  totalMinutes: number;
  heightPx: number;
}

/** Time span of a festival day from dayStart/dayEnd (midnight overflow, §12.2). */
export function dayRange(day: FestivalDay): DayRange {
  const start = parse(day.dayStart);
  const end = parse(day.dayEnd);
  const totalMinutes = Math.max(0, end.diff(start, "minutes").minutes);
  return { start, end, totalMinutes, heightPx: totalMinutes * PX_PER_MIN };
}

/** Vertical offset (px) of an ISO timestamp relative to the day start. */
export function offsetPx(iso: string, range: DayRange): number {
  return parse(iso).diff(range.start, "minutes").minutes * PX_PER_MIN;
}

/** Hour marks for the time axis (full hours within the day span). */
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
