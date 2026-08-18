/// <reference types="vite/client" />
/// <reference types="vite-plugin-pwa/client" />

interface ImportMetaEnv {
  /** Öffentlicher VAPID-Key für Web-Push – optionaler Build-Fallback
   * (Laufzeitbezug via /push/vapid.php, siehe src/lib/push.ts). */
  readonly VITE_VAPID_PUBLIC_KEY?: string;
  /** Standard-App-Sprache der Instanz (de/en/fr/es); leer/ungesetzt = en. */
  readonly VITE_DEFAULT_LANGUAGE?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
