import { useInfoPages } from "./queries";

// Effective info pages. The live override (data/app-info.json) is already
// handled generically in useDataset - a delegate suffices here so components
// and search keep an expressive API.
export function useInfo() {
  return useInfoPages();
}
