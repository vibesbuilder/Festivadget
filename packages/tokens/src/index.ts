// @rid/tokens — geteilte Design-Tokens (Farben, Fonts) für Festivadget & CrewCare.
//
// Die FARBWERTE selbst (hell/dunkel-Theming) liegen weiterhin als CSS-Variablen in der
// jeweiligen App (Festivadget: src/styles/index.css). Hier stehen nur die Tailwind-Token-
// Definitionen, die auf diese CSS-Variablen verweisen – so bleibt das Theming pro App
// steuerbar, während die Token-Struktur (rid-*) geteilt ist.

export const ridColors = {
  bg: "rgb(var(--rid-bg) / <alpha-value>)",
  surface: "rgb(var(--rid-surface) / <alpha-value>)",
  "surface-2": "rgb(var(--rid-surface-2) / <alpha-value>)",
  text: "rgb(var(--rid-text) / <alpha-value>)",
  muted: "rgb(var(--rid-muted) / <alpha-value>)",
  accent: "rgb(var(--rid-accent) / <alpha-value>)",
  "accent-2": "rgb(var(--rid-accent-2) / <alpha-value>)",
  border: "rgb(var(--rid-border) / <alpha-value>)",
} as const;

export const ridFontFamily = {
  display: ["var(--rid-font-display)", "system-ui", "sans-serif"],
  sans: ["var(--rid-font-sans)", "system-ui", "sans-serif"],
} as const;
