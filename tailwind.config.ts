import type { Config } from "tailwindcss";
import preset from "@rid/tokens/tailwind-preset";

// Design-Tokens (Farben, Fonts, Layout) kommen aus dem geteilten @rid/tokens-Preset.
// Die eigentlichen Farbwerte (hell/dunkel) bleiben als CSS-Variablen in src/styles/index.css.
export default {
  presets: [preset],
  // relative: true → Globs gelten relativ zu DIESER Datei, nicht zum
  // Prozess-CWD. Sonst scannt der Dev-Server nichts, wenn Vite von der
  // Workspace-Wurzel aus gestartet wird (z. B. IDE-Launcher) – die App
  // wäre im Dev unstyled, während der Build (CWD = App-Ordner) passt.
  content: { relative: true, files: ["./index.html", "./src/**/*.{ts,tsx}"] },
  plugins: [],
} satisfies Config;
