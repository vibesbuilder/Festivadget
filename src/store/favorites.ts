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

// IndexedDB kann fehlen oder wegbrechen (Safari-Privatmodus, iOS-Lockdown,
// „Connection to Indexed Database server lost") – Favoriten gelten dann nur
// für die laufende Sitzung, statt Unhandled Rejections zu werfen.
async function persist(set: Set<string>): Promise<void> {
  try {
    await idbSet(IDB_KEY, Array.from(set));
  } catch {
    // bewusst still – siehe oben
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
      // IndexedDB nicht verfügbar → leer starten (Sitzungs-Favoriten möglich).
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
