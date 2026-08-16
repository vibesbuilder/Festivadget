// Tailwind-Preset mit den RID-/Festivadget-Design-Tokens. Apps binden es per
// `presets: [preset]` ein; die App-Tailwind-Config enthält dann nur noch `content`.
// (Bewusst ohne tailwindcss-Typimport, damit @rid/tokens dependency-frei bleibt.)
import { ridColors, ridFontFamily } from "./index";

const preset = {
  theme: {
    extend: {
      colors: {
        rid: ridColors,
      },
      fontFamily: ridFontFamily,
      fontSize: {
        // Seiten-Überschriften (h1 … text-2xl): größer als der Tailwind-Standard.
        "2xl": ["2.25rem", "2.75rem"],
      },
      aspectRatio: {
        "4/5": "4 / 5",
      },
      maxWidth: {
        app: "640px",
      },
    },
  },
};

export default preset;
