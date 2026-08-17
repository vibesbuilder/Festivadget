// Kunden-Branding zur Laufzeit (Paket Y): Farben, Schrift-Set, Titel und
// Manifest/Favicon kommen aus data/app-config.json (CMS-Tab „Branding") und
// überschreiben die Build-Standards aus src/styles/index.css. Ohne Branding
// (oder je fehlendem Feld) gilt unverändert der Build-Stand.

export interface BrandingColors {
  accent?: string;
  accent2?: string;
  dark?: Partial<Record<"bg" | "surface" | "surface2" | "text" | "muted" | "border", string>>;
  light?: Partial<Record<"bg" | "surface" | "surface2" | "text" | "muted" | "border", string>>;
}

export interface Branding {
  colors?: BrandingColors;
  font?: string; // Schlüssel aus FONT_SETS
  logo?: string; // z. B. /data/uploads/branding-logo.png (leer = Build-Logo)
  title?: string; // document.title + Manifest-Name
  shortName?: string; // Home-Bildschirm-Label + Manifest-short_name
  icons?: string; // Versions-Token, wenn eigene PWA-Icons hochgeladen sind
  manifest?: boolean; // true = dynamisches Manifest (/push/manifest.php) verwenden
}

// Schrift-Sets als reine CSS-Stacks (keine Font-Dateien nötig – offlinefähig).
// Schlüssel müssen mit dem CMS (push/cms, Tab Branding) übereinstimmen.
export const FONT_SETS: Record<string, { display: string; sans: string }> = {
  standard: {
    display: '"Oswald", "Bebas Neue", "Arial Narrow", system-ui, sans-serif',
    sans: '"Inter", system-ui, -apple-system, sans-serif',
  },
  system: {
    display: 'system-ui, -apple-system, "Segoe UI", sans-serif',
    sans: 'system-ui, -apple-system, "Segoe UI", sans-serif',
  },
  serif: {
    display: 'Georgia, "Times New Roman", serif',
    sans: 'system-ui, -apple-system, sans-serif',
  },
  plakat: {
    display: '"Arial Black", Impact, system-ui, sans-serif',
    sans: "Verdana, Geneva, system-ui, sans-serif",
  },
};

const COLOR_VARS = ["bg", "surface", "surface2", "text", "muted", "border"] as const;
const VAR_NAME: Record<(typeof COLOR_VARS)[number], string> = {
  bg: "--rid-bg",
  surface: "--rid-surface",
  surface2: "--rid-surface-2",
  text: "--rid-text",
  muted: "--rid-muted",
  border: "--rid-border",
};

/** "#rrggbb" → "r g b" (Token-Format für Tailwind-Alpha-Varianten). */
function hexToTriplet(hex: string): string | null {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
  if (!m) return null;
  const n = parseInt(m[1]!, 16);
  return `${(n >> 16) & 255} ${(n >> 8) & 255} ${n & 255}`;
}

/**
 * Branding auf das Dokument anwenden. Muss bei Theme-Wechsel erneut laufen,
 * weil Inline-Variablen den [data-theme="light"]-Block überstimmen würden –
 * daher werden die Werte des AKTIVEN Themes gesetzt (bzw. alles entfernt).
 */
export function applyBranding(branding: Branding | undefined, theme: "dark" | "light"): void {
  const root = document.documentElement;
  const colors = branding?.colors;
  const group = theme === "light" ? colors?.light : colors?.dark;

  // Farb-Tokens: setzen, wenn vorhanden – sonst zurück auf den Build-Stand.
  for (const key of COLOR_VARS) {
    const triplet = group?.[key] ? hexToTriplet(group[key]!) : null;
    if (triplet) root.style.setProperty(VAR_NAME[key], triplet);
    else root.style.removeProperty(VAR_NAME[key]);
  }
  const accent = colors?.accent ? hexToTriplet(colors.accent) : null;
  const accent2 = colors?.accent2 ? hexToTriplet(colors.accent2) : null;
  if (accent) root.style.setProperty("--rid-accent", accent);
  else root.style.removeProperty("--rid-accent");
  if (accent2) root.style.setProperty("--rid-accent-2", accent2);
  else root.style.removeProperty("--rid-accent-2");

  // Schleier über der Hintergrundgrafik in der (gebrandeten) Hintergrundfarbe.
  const bgTriplet = group?.bg ? hexToTriplet(group.bg) : null;
  if (bgTriplet) {
    const alpha = theme === "light" ? 0.86 : 0.78;
    root.style.setProperty("--rid-bg-scrim", `rgba(${bgTriplet.split(" ").join(", ")}, ${alpha})`);
  } else {
    root.style.removeProperty("--rid-bg-scrim");
  }

  // Schrift-Set.
  const font = branding?.font ? FONT_SETS[branding.font] : undefined;
  if (font) {
    root.style.setProperty("--rid-font-display", font.display);
    root.style.setProperty("--rid-font-sans", font.sans);
  } else {
    root.style.removeProperty("--rid-font-display");
    root.style.removeProperty("--rid-font-sans");
  }

  // Titel (Browser-Tab); Home-Bildschirm-Label übernimmt die AppShell.
  if (branding?.title) document.title = branding.title;

  // Dynamisches Manifest + eigenes Favicon (nur wenn im CMS eingerichtet).
  if (branding?.manifest) {
    document
      .querySelector('link[rel="manifest"]')
      ?.setAttribute("href", "/push/manifest.php");
  }
  if (branding?.icons) {
    const icon = document.querySelector('link[rel="icon"]');
    if (icon) {
      icon.setAttribute("type", "image/png");
      icon.setAttribute("href", `/data/uploads/pwa-icon-192.png?v=${branding.icons}`);
    }
    document
      .querySelector('link[rel="apple-touch-icon"]')
      ?.setAttribute("href", `/data/uploads/pwa-icon-192.png?v=${branding.icons}`);
  }
}

/** theme-color-Meta passend zum Branding (Fallback: Build-Farben). */
export function themeColorFor(branding: Branding | undefined, theme: "dark" | "light"): string {
  const fallback = theme === "dark" ? "#121212" : "#f4f4f5";
  const group = theme === "light" ? branding?.colors?.light : branding?.colors?.dark;
  return group?.bg && /^#[0-9a-f]{6}$/i.test(group.bg) ? group.bg : fallback;
}
