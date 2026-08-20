import { QueryClient } from "@tanstack/react-query";

// Central TanStack Query instance.
// staleTime of 2 min matches the data freshness (§5/§11); invalidation happens
// selectively via the version polling (useVersionSync).
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
