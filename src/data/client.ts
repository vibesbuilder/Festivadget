import { QueryClient } from "@tanstack/react-query";

// Zentrale TanStack-Query-Instanz.
// staleTime 2 min entspricht der Datenaktualität (§5/§11); Invalidierung erfolgt
// gezielt über das Versions-Polling (useVersionSync).
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 2 * 60_000,
      gcTime: 24 * 60 * 60_000,
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});
