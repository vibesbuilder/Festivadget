import i18n from "i18next";
import { initReactI18next } from "react-i18next";
import de from "./de.json";
import en from "./en.json";
import fr from "./fr.json";
import es from "./es.json";

// Available app languages with native display names (language picker under "More").
export const LANGUAGES = {
  de: "Deutsch",
  en: "English",
  fr: "Français",
  es: "Español",
} as const;

export type AppLanguage = keyof typeof LANGUAGES;

// Default language without a stored choice: instance value from the build env
// (RID: VITE_DEFAULT_LANGUAGE=de), otherwise English (neutral release build).
// At runtime app-config.json -> languageDefault can additionally override
// (CMS -> settings), as long as the guest has not chosen themselves (AppShell).
const envDefault = import.meta.env.VITE_DEFAULT_LANGUAGE as string | undefined;
export const DEFAULT_LANGUAGE: AppLanguage =
  envDefault && envDefault in LANGUAGES ? (envDefault as AppLanguage) : "en";

// Read the last chosen language from the persisted UI store (localStorage) -
// the store itself hydrates only after the i18n init, hence the direct read.
function storedLanguage(): AppLanguage {
  try {
    const raw = localStorage.getItem("festivadget:ui");
    const lang = raw
      ? (JSON.parse(raw) as { state?: { language?: string } }).state?.language
      : null;
    return lang && lang in LANGUAGES ? (lang as AppLanguage) : DEFAULT_LANGUAGE;
  } catch {
    return DEFAULT_LANGUAGE;
  }
}

// react-i18next, source language de, default see DEFAULT_LANGUAGE (§14).
void i18n.use(initReactI18next).init({
  resources: {
    de: { translation: de },
    en: { translation: en },
    fr: { translation: fr },
    es: { translation: es },
  },
  lng: storedLanguage(),
  fallbackLng: "de",
  interpolation: { escapeValue: false },
});

export default i18n;
