import { useMemo } from "react";
import type { Slot } from "@/types";
import { findClashes } from "@rid/core";
import { useFavorites } from "@/store/favorites";

/**
 * Ermittelt Slot-IDs favorisierter Acts, die sich zeitlich mit einem anderen
 * Favoriten überschneiden (§12.2/§12.3). Liefert ein Set zur schnellen Abfrage.
 * Die reine Clash-Logik steckt in @rid/core; hier nur die React-Anbindung.
 */
export function useClashes(slots: Slot[] | undefined): Set<string> {
  const favorites = useFavorites((s) => s.favorites);
  return useMemo(() => findClashes(slots ?? [], favorites), [slots, favorites]);
}
