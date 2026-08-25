import i18n from "i18next";
import { initReactI18next } from "react-i18next";
import de from "./de.json";
import en from "./en.json";
import fr from "./fr.json";
import es from "./es.json";
import it from "./it.json";
import nl from "./nl.json";
import cs from "./cs.json";
import pl from "./pl.json";
import pt from "./pt.json";
import fi from "./fi.json";
import hu from "./hu.json";
import sk from "./sk.json";
import hr from "./hr.json";
import da from "./da.json";
import sv from "./sv.json";
import uk from "./uk.json";
import ro from "./ro.json";
import sl from "./sl.json";
import tr from "./tr.json";
import ptBR from "./pt-BR.json";
import zhCN from "./zh-CN.json";
import jaJP from "./ja-JP.json";
import koKR from "./ko-KR.json";
import sq from "./sq.json";
import af from "./af.json";
import el from "./el.json";
import hi from "./hi.json";
import idID from "./id.json";
import isIS from "./is.json";
import nb from "./nb.json";
import ru from "./ru.json";
import th from "./th.json";

// Available app languages with native display names (language picker under "More").
export const LANGUAGES = {
  de: "Deutsch",
  en: "English",
  fr: "Français",
  es: "Español",
  it: "Italiano",
  nl: "Nederlands",
  cs: "Čeština",
  pl: "Polski",
  pt: "Português",
  fi: "Suomi",
  hu: "Magyar",
  sk: "Slovenčina",
  hr: "Hrvatski",
  da: "Dansk",
  sv: "Svenska",
  uk: "Українська",
  ro: "Română",
  sl: "Slovenščina",
  tr: "Türkçe",
  "pt-BR": "Português (Brasil)",
  "zh-CN": "简体中文",
  "ja-JP": "日本語",
  "ko-KR": "한국어",
  sq: "Shqip",
  af: "Afrikaans",
  el: "Ελληνικά",
  hi: "हिन्दी",
  id: "Bahasa Indonesia",
  is: "Íslenska",
  nb: "Norsk (bokmål)",
  ru: "Русский",
  th: "ไทย",
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
    it: { translation: it },
    nl: { translation: nl },
    cs: { translation: cs },
    pl: { translation: pl },
    pt: { translation: pt },
    fi: { translation: fi },
    hu: { translation: hu },
    sk: { translation: sk },
    hr: { translation: hr },
    da: { translation: da },
    sv: { translation: sv },
    uk: { translation: uk },
    ro: { translation: ro },
    sl: { translation: sl },
    tr: { translation: tr },
    "pt-BR": { translation: ptBR },
    "zh-CN": { translation: zhCN },
    "ja-JP": { translation: jaJP },
    "ko-KR": { translation: koKR },
    sq: { translation: sq },
    af: { translation: af },
    el: { translation: el },
    hi: { translation: hi },
    id: { translation: idID },
    is: { translation: isIS },
    nb: { translation: nb },
    ru: { translation: ru },
    th: { translation: th },
  },
  lng: storedLanguage(),
  fallbackLng: "en",
  interpolation: { escapeValue: false },
});

export default i18n;
