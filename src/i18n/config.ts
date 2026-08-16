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

// Zuletzt gewählte Sprache aus dem persistierten UI-Store (localStorage) lesen –
// der Store selbst hydratisiert erst nach dem i18n-Init, daher direkt lesen.
function storedLanguage(): AppLanguage {
  try {
    const raw = localStorage.getItem("festivadget:ui");
    const lang = raw
      ? (JSON.parse(raw) as { state?: { language?: string } }).state?.language
      : null;
    return lang && lang in LANGUAGES ? (lang as AppLanguage) : "de";
  } catch {
    return "de";
  }
}

// react-i18next, Standard de, optional en/fr/es (§14).
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
