import { useInfoPages } from "./queries";

// Effektive Info-Seiten. Der Live-Override (data/app-info.json) wird bereits
// generisch in useDataset behandelt – hier genügt ein Delegat, damit Komponenten
// und Suche eine sprechende API behalten.
export function useInfo() {
  return useInfoPages();
}
