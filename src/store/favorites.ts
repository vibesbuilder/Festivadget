import { create } from "zustand";
import { get as idbGet, set as idbSet } from "idb-keyval";

// Favorites as Set<slotId>, persisted in IndexedDB (§11).
const IDB_KEY = "favorites";

interface FavoritesState {
  favorites: Set<string>;
  hydrated: boolean;
  hydrate: () => Promise<void>;
  toggle: (slotId: string) => void;
  isFavorite: (slotId: string) => boolean;
  clear: () => void;
}

// IndexedDB may be missing or drop out (Safari private mode, iOS lockdown,
// "Connection to Indexed Database server lost") - favorites then only apply
// for the running session instead of throwing unhandled rejections.
async function persist(set: Set<string>): Promise<void> {
  try {
    await idbSet(IDB_KEY, Array.from(set));
  } catch {
    // deliberately silent - see above
  }
}

export const useFavorites = create<FavoritesState>((set, getState) => ({
  favorites: new Set<string>(),
  hydrated: false,

  hydrate: async () => {
    let stored: string[] = [];
    try {
      stored = (await idbGet<string[]>(IDB_KEY)) ?? [];
    } catch {
      // IndexedDB unavailable -> start empty (session favorites possible).
    }
    set({ favorites: new Set(stored), hydrated: true });
  },

  toggle: (slotId) => {
    const next = new Set(getState().favorites);
    if (next.has(slotId)) next.delete(slotId);
    else next.add(slotId);
    set({ favorites: next });
    void persist(next);
  },

  isFavorite: (slotId) => getState().favorites.has(slotId),

  clear: () => {
    const empty = new Set<string>();
    set({ favorites: empty });
    void persist(empty);
  },
}));
