// Data source configuration (IMPLEMENTATION.md §6).
// Selectable per content domain: "manual" | "joomla" | "wordpress".
// The runtime schema (§7) is source-independent - adapters map onto it.

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
  // Connection defaults (tokens ONLY from ENV, never commit, §6.6):
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

// MVP default: everything "manual" (from content/*.json + content/slots.csv),
// so the build works offline and without a CMS connection.
// Switch individual domains to "joomla"/"wordpress" as needed.
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
    // info: source per submenu entry (§6.4). `default` delivers structure + texts
    // (content/info.json: id, icon, order, hidden, fallback title/text).
    // In `overrides` a different source can be chosen per entry ID -
    // it then delivers only title/text, the structure stays from `default`.
    // Example (text of the "parken" page from a Joomla article):
    //   overrides: { parken: { provider: "joomla", joomla: { ids: [42] } } }
    info: {
      default: { provider: "manual" },
      overrides: {},
    },
  },
};

export default config;
