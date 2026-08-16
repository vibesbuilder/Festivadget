// Datenquellen-Konfiguration (IMPLEMENTATION.md §6).
// Pro Inhaltsdomäne wählbar: "manual" | "joomla" | "wordpress".
// Das Laufzeit-Schema (§7) ist quellenunabhängig – Adapter mappen darauf.

export type Provider = "manual" | "joomla" | "wordpress";

export interface JoomlaLocator {
  categoryId?: number;
  ids?: number[];
  customFields?: Record<string, string>;
}

export interface WordPressLocator {
  categorySlug?: string;
  postType?: string;
  acf?: Record<string, string>;
}

export interface SourceBinding {
  provider: Provider;
  joomla?: JoomlaLocator;
  wordpress?: WordPressLocator;
}

export type SlotsBinding = SourceBinding & {
  format?: "csv" | "joomla-customfields" | "wordpress-acf";
};

export interface ContentSourcesConfig {
  // Verbindungs-Defaults (Tokens NUR aus ENV, nie committen, §6.6):
  joomla?: { baseUrl: string; tokenEnv: string };
  wordpress?: { baseUrl: string; userEnv?: string; appPwEnv?: string };

  bindings: {
    festival: SourceBinding;
    stages: SourceBinding;
    artists: SourceBinding;
    slots: SlotsBinding;
    pois: SourceBinding;
    map: SourceBinding;
    news: SourceBinding;
    sponsors: SourceBinding;
    tickets: SourceBinding;
    weather: SourceBinding;
    info: {
      default: SourceBinding;
      overrides?: Record<string, SourceBinding>;
    };
  };
}

// MVP-Default: alles "manual" (aus content/*.json + content/slots.csv),
// damit der Build offline und ohne CMS-Anbindung funktioniert.
// Einzelne Domänen bei Bedarf auf "joomla"/"wordpress" umstellen.
export const config: ContentSourcesConfig = {
  joomla: { baseUrl: "https://rockimdorf.at", tokenEnv: "JOOMLA_API_TOKEN" },
  wordpress: { baseUrl: "https://example.org", userEnv: "WP_USER", appPwEnv: "WP_APP_PW" },
  bindings: {
    festival: { provider: "manual" },
    stages: { provider: "manual" },
    artists: { provider: "manual" },
    slots: { provider: "manual", format: "csv" },
    pois: { provider: "manual" },
    map: { provider: "manual" },
    news: { provider: "manual" },
    sponsors: { provider: "manual" },
    tickets: { provider: "manual" },
    weather: { provider: "manual" },
    // info: Quelle je Untermenüpunkt (§6.4). `default` liefert Struktur + Texte
    // (content/info.json: id, icon, order, hidden, Fallback-Titel/-Text).
    // In `overrides` kann pro Eintrag-ID eine andere Quelle gewählt werden –
    // diese liefert dann nur Titel/Text, die Struktur bleibt aus `default`.
    // Beispiel (Text der Seite "parken" aus einem Joomla-Artikel):
    //   overrides: { parken: { provider: "joomla", joomla: { ids: [42] } } }
    info: {
      default: { provider: "manual" },
      overrides: {},
    },
  },
};

export default config;
