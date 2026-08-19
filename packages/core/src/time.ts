import { DateTime, Interval } from "luxon";
import type { FestivalDay, Slot } from "./types";

// Zeit-Helfer. Zwingend Luxon wegen Europe/Vienna + Mitternachtsüberlauf (§3, §12.2).

export const DEFAULT_TZ = "Europe/Vienna";

export function parse(iso: string, tz: string = DEFAULT_TZ): DateTime {
  return DateTime.fromISO(iso, { setZone: true }).setZone(tz);
}

export function now(tz: string = DEFAULT_TZ): DateTime {
  return DateTime.now().setZone(tz);
}

/** "HH:mm" für UI-Anzeige. */
export function formatTime(iso: string, tz: string = DEFAULT_TZ): string {
  return parse(iso, tz).toFormat("HH:mm");
}

/** "Fr 31.07. · 22:00" o. ä. – Wochentagskürzel in der übergebenen Sprache. */
export function formatDateTime(
  iso: string,
  tz: string = DEFAULT_TZ,
  locale: string = "de",
): string {
  return parse(iso, tz).setLocale(locale).toFormat("ccc dd.LL. · HH:mm");
}

/** Datums-Bereich für den Home-Kopf, z. B. "29. Juni – 1. Juli 2026" (de). */
export function formatDateRange(
  startIso: string,
  endIso: string,
  tz: string = DEFAULT_TZ,
  locale: string = "de",
): string {
  const s = parse(startIso, tz).setLocale(locale);
  const e = parse(endIso, tz).setLocale(locale);
  if (!s.isValid || !e.isValid) return "";
  if (s.hasSame(e, "day")) return e.toFormat("d. LLLL yyyy");
  if (s.hasSame(e, "month") && s.hasSame(e, "year")) {
    return `${s.toFormat("d.")}–${e.toFormat("d. LLLL yyyy")}`;
  }
  if (s.hasSame(e, "year")) {
    return `${s.toFormat("d. LLLL")} – ${e.toFormat("d. LLLL yyyy")}`;
  }
  return `${s.toFormat("d. LLLL yyyy")} – ${e.toFormat("d. LLLL yyyy")}`;
}

/** Läuft der Slot zum Zeitpunkt `at` gerade? */
export function isLive(slot: Slot, at: DateTime = now()): boolean {
  const start = parse(slot.start);
  const end = parse(slot.end);
  return at >= start && at < end;
}

/** Ordnet einen Zeitpunkt einem Festivaltag zu (Mitternachtsüberlauf via dayStart/dayEnd). */
export function dayForInstant(days: FestivalDay[], at: DateTime = now()): FestivalDay | undefined {
  return days.find((d) => {
    const span = Interval.fromDateTimes(parse(d.dayStart), parse(d.dayEnd));
    return span.contains(at);
  });
}

/** Überschneiden sich zwei Slots zeitlich? (Clash-Erkennung, §12.3) */
export function slotsOverlap(a: Slot, b: Slot): boolean {
  const ia = Interval.fromDateTimes(parse(a.start), parse(a.end));
  const ib = Interval.fromDateTimes(parse(b.start), parse(b.end));
  return ia.overlaps(ib);
}

/** Minuten-Dauer eines Slots. */
export function durationMinutes(slot: Slot): number {
  return parse(slot.end).diff(parse(slot.start), "minutes").minutes;
}
