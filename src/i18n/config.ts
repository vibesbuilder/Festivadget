import i18n from "i18next";
import { initReactI18next } from "react-i18next";
import de from "./de.json";
import en from "./en.json";
import fr from "./fr.json";
import es from "./es.json";

// Verfügbare App-Sprachen mit nativem Anzeigenamen (Sprachwahl unter „Mehr").
export const LANGUAGES = {
  de: "Deutsch",
  en: "English",
  fr: "Français",
  es: "Español",
} as const;

export type AppLanguage = keyof typeof LANGUAGES;

// Standard-Sprache ohne gespeicherte Wahl: Instanz-Wert aus der Build-Env
// (RID: VITE_DEFAULT_LANGUAGE=de), sonst Englisch (neutraler Release-Build).
// Zur Laufzeit kann zusätzlich app-config.json → languageDefault übersteuern
// (CMS → Einstellungen), solange der Gast nicht selbst gewählt hat (AppShell).
const envDefault = import.meta.env.VITE_DEFAULT_LANGUAGE as string | undefined;
export const DEFAULT_LANGUAGE: AppLanguage =
  envDefault && envDefault in LANGUAGES ? (envDefault as AppLanguage) : "en";

// Zuletzt gewählte Sprache aus dem persistierten UI-Store (localStorage) lesen –
// der Store selbst hydratisiert erst nach dem i18n-Init, daher direkt lesen.
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

// react-i18next, Quellsprache de, Standard siehe DEFAULT_LANGUAGE (§14).
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
