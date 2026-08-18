import { create } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";
import { DEFAULT_LANGUAGE, type AppLanguage } from "@/i18n/config";

export type TimetableView = "grid" | "list";
export type Theme = "dark" | "light";

// UI-State (Tag-Auswahl, Filter, Ansicht, Sprache, Theme), persistiert in localStorage (§11).
interface UiState {
  selectedDayId: string | null;
  timetableView: TimetableView;
  favoritesOnly: boolean;
  lineupDayId: string | null; // Line-Up-Filter nach Tag (null = alle)
  hiddenStageIds: string[]; // im Timetable ausgeblendete Bühnen
  language: AppLanguage;
  languageExplicit: boolean; // true = User hat selbst gewählt (Admin-Default greift dann nicht mehr)
  theme: Theme; // Hell-/Dunkel-Modus
  themeExplicit: boolean; // true = User hat selbst gewählt (Admin-Default greift dann nicht mehr)
  setSelectedDay: (dayId: string | null) => void;
  setTimetableView: (view: TimetableView) => void;
  setFavoritesOnly: (only: boolean) => void;
  setLineupDay: (dayId: string | null) => void;
  toggleStageHidden: (stageId: string) => void;
  setLanguage: (lang: AppLanguage) => void;
  setTheme: (theme: Theme) => void;
  toggleTheme: () => void;
  applyServerThemeDefault: (theme: Theme) => void; // nur wirksam, solange !themeExplicit
  applyServerLanguageDefault: (lang: AppLanguage) => void; // nur wirksam, solange !languageExplicit
}

export const useUi = create<UiState>()(
  persist(
    (set) => ({
      selectedDayId: null,
      timetableView: "grid",
      favoritesOnly: false,
      lineupDayId: null,
      hiddenStageIds: [],
      language: DEFAULT_LANGUAGE,
      languageExplicit: false,
      theme: "dark",
      themeExplicit: false,
      setSelectedDay: (selectedDayId) => set({ selectedDayId }),
      setTimetableView: (timetableView) => set({ timetableView }),
      setFavoritesOnly: (favoritesOnly) => set({ favoritesOnly }),
      setLineupDay: (lineupDayId) => set({ lineupDayId }),
      toggleStageHidden: (stageId) =>
        set((state) => ({
          hiddenStageIds: state.hiddenStageIds.includes(stageId)
            ? state.hiddenStageIds.filter((id) => id !== stageId)
            : [...state.hiddenStageIds, stageId],
        })),
      setLanguage: (language) => set({ language, languageExplicit: true }),
      setTheme: (theme) => set({ theme, themeExplicit: true }),
      toggleTheme: () =>
        set((state) => ({ theme: state.theme === "dark" ? "light" : "dark", themeExplicit: true })),
      // Admin-Standard-Theme anwenden – aber nur, solange der User nicht selbst gewählt hat.
      applyServerThemeDefault: (theme) =>
        set((state) => (state.themeExplicit ? {} : { theme })),
      // Admin-Standard-Sprache anwenden – aber nur, solange der User nicht selbst gewählt hat.
      applyServerLanguageDefault: (language) =>
        set((state) => (state.languageExplicit ? {} : { language })),
    }),
    {
      name: "festivadget:ui",
      storage: createJSONStorage(() => localStorage),
    },
  ),
);
