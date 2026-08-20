/// <reference types="vite/client" />
/// <reference types="vite-plugin-pwa/client" />

interface ImportMetaEnv {
  /** Public VAPID key for web push - optional build fallback
   * (runtime source via /push/vapid.php, see src/lib/push.ts). */
  readonly VITE_VAPID_PUBLIC_KEY?: string;
  /** Default app language of the instance (de/en/fr/es); empty/unset = en. */
  readonly VITE_DEFAULT_LANGUAGE?: string;
  /** Footer text under "More"; empty/unset = "Festivadget by vibesbuilder". */
  readonly VITE_FOOTER_CREDIT?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
