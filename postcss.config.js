import { fileURLToPath } from "node:url";

// Tailwind-Config EXPLIZIT referenzieren: ohne Pfad sucht Tailwind sie im
// Prozess-CWD – wird Vite von der Workspace-Wurzel gestartet (IDE-Launcher),
// findet es sonst keine Config und liefert unstyled CSS ("content missing").
const tailwindConfig = fileURLToPath(new URL("./tailwind.config.ts", import.meta.url));

export default {
  plugins: {
    tailwindcss: { config: tailwindConfig },
    autoprefixer: {},
  },
};
