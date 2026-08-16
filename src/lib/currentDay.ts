// „Heutiger" Festivaltag mit 04:00-Tagesgrenze: Zeiten vor 04:00 zählen zum
// Vortag (Nachtprogramm). Außerhalb des Festivals: null (Aufrufer fällt auf
// den ersten Tag zurück). Gleiches Verhalten wie in CrewCare.
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
