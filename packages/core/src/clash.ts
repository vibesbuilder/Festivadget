import type { Slot } from "./types";
import { slotsOverlap } from "./time";

// Reiner Clash-Finder (§12.2/§12.3): Liefert die IDs der favorisierten Slots, die
// sich zeitlich mit einem anderen Favoriten überschneiden. Ohne React/Fetch –
// die UI (z. B. useClashes) reicht nur die Daten herein.
export function findClashes(slots: Slot[], favoriteIds: Set<string>): Set<string> {
  const clashing = new Set<string>();
  const favSlots = slots.filter((s) => favoriteIds.has(s.id) && !s.cancelled);
  for (let i = 0; i < favSlots.length; i++) {
    for (let j = i + 1; j < favSlots.length; j++) {
      if (slotsOverlap(favSlots[i]!, favSlots[j]!)) {
        clashing.add(favSlots[i]!.id);
        clashing.add(favSlots[j]!.id);
      }
    }
  }
  return clashing;
}
