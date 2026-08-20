// Time of the first app open on this device (once, in localStorage).
// Used for news that should disappear X minutes after the first open
// (e.g. the welcome news).
const KEY = "festivadget:first-open";

export function getFirstOpenAt(): number {
  try {
    const stored = localStorage.getItem(KEY);
    if (stored) return Number(stored);
    const now = Date.now();
    localStorage.setItem(KEY, String(now));
    return now;
  } catch {
    // localStorage unavailable -> "now" (no hiding)
    return Date.now();
  }
}
