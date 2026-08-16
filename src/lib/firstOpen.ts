// Zeitpunkt des ersten App-Öffnens auf diesem Gerät (einmalig, in localStorage).
// Wird für News genutzt, die X Minuten nach dem ersten Öffnen verschwinden sollen
// (z. B. die Willkommen-News).
const KEY = "festivadget:first-open";

export function getFirstOpenAt(): number {
  try {
    const stored = localStorage.getItem(KEY);
    if (stored) return Number(stored);
    const now = Date.now();
    localStorage.setItem(KEY, String(now));
    return now;
  } catch {
    // localStorage nicht verfügbar → „jetzt" (kein Ausblenden)
    return Date.now();
  }
}
