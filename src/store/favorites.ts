import { create } from "zustand";
import { get as idbGet, set as idbSet } from "idb-keyval";

// Favoriten als Set<slotId>, persistiert in IndexedDB (§11).
const IDB_KEY = "favorites";

interface FavoritesState {
  favorites: Set<string>;
  hydrated: boolean;
  hydrate: () => Promise<void>;
  toggle: (slotId: string) => void;
  isFavorite: (slotId: string) => boolean;
  clear: () => void;
}

async function persist(set: Set<string>): Promise<void> {
  await idbSet(IDB_KEY, Array.from(set));
}

export const useFavorites = create<FavoritesState>((set, getState) => ({
  favorites: new Set<string>(),
  hydrated: false,

  hydrate: async () => {
    const stored = (await idbGet<string[]>(IDB_KEY)) ?? [];
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
