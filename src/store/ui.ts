import { create } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";
import { DEFAULT_LANGUAGE, type AppLanguage } from "@/i18n/config";

export type TimetableView = "grid" | "list";
export type Theme = "dark" | "light";

// UI state (day selection, filters, view, language, theme), persisted in localStorage (§11).
interface UiState {
  selectedDayId: string | null;
  timetableView: TimetableView;
  favoritesOnly: boolean;
  lineupDayId: string | null; // Line-Up-Filter nach Tag (null = alle)
  hiddenStageIds: string[]; // stages hidden in the timetable
  language: AppLanguage;
  languageExplicit: boolean; // true = user chose themselves (admin default no longer applies)
  theme: Theme; // Hell-/Dunkel-Modus
  themeExplicit: boolean; // true = user chose themselves (admin default no longer applies)
  setSelectedDay: (dayId: string | null) => void;
  setTimetableView: (view: TimetableView) => void;
  setFavoritesOnly: (only: boolean) => void;
  setLineupDay: (dayId: string | null) => void;
  toggleStageHidden: (stageId: string) => void;
  setLanguage: (lang: AppLanguage) => void;
  setTheme: (theme: Theme) => void;
  toggleTheme: () => void;
  applyServerThemeDefault: (theme: Theme) => void; // only effective while !themeExplicit
  applyServerLanguageDefault: (lang: AppLanguage) => void; // only effective while !languageExplicit
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
      // Apply the admin default theme - but only while the user has not chosen themselves.
      applyServerThemeDefault: (theme) =>
        set((state) => (state.themeExplicit ? {} : { theme })),
      // Apply the admin default language - but only while the user has not chosen themselves.
      applyServerLanguageDefault: (language) =>
        set((state) => (state.languageExplicit ? {} : { language })),
    }),
    {
      name: "festivadget:ui",
      storage: createJSONStorage(() => localStorage),
    },
  ),
);
