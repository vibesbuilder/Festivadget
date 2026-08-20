import { useMemo } from "react";
import type { Slot } from "@/types";
import { findClashes } from "@rid/core";
import { useFavorites } from "@/store/favorites";

/**
 * Determines slot IDs of favorited acts that overlap in time with another
 * favorite (§12.2/§12.3). Returns a Set for fast lookups.
 * The pure clash logic lives in @rid/core; this is only the React binding.
 */
export function useClashes(slots: Slot[] | undefined): Set<string> {
  const favorites = useFavorites((s) => s.favorites);
  return useMemo(() => findClashes(slots ?? [], favorites), [slots, favorites]);
}
