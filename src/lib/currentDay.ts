// "Today's" festival day with a 04:00 day boundary: times before 04:00 count
// towards the previous day (night program). Outside the festival: null (callers
// fall back to the first day). Same behavior as in CrewCare.
export function currentDayId(
  days: { id: string; dayStart: string }[],
  now: Date = new Date(),
): string | null {
  const shifted = new Date(now.getTime() - 4 * 60 * 60 * 1000);
  const sameLocalDate = (a: Date, b: Date) =>
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate();
  return days.find((d) => sameLocalDate(new Date(d.dayStart), shifted))?.id ?? null;
}
