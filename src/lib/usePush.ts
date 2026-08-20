import { useQuery, useQueryClient } from "@tanstack/react-query";
import { isPushCapable, isPushSupported, ensureVapidKey, currentSubscription } from "./push";

// Shared push state (via the query cache) so the home toggle and header bell
// stay in sync: enabling push via the toggle makes the bell appear immediately
// (and vice versa). Also fetches the VAPID key from the backend if it is not
// already known from the build env/localStorage.
export function usePushActive(): { supported: boolean; active: boolean } {
  const capable = isPushCapable();
  const { data } = useQuery({
    queryKey: ["push-sub"],
    // Never throws (ensureVapidKey catches network errors) -> no retry/pause limbo.
    queryFn: async () => {
      const key = await ensureVapidKey();
      return { hasKey: !!key, sub: key ? await currentSubscription() : null };
    },
    enabled: capable,
    staleTime: Infinity,
    retry: false,
  });
  // Until the response arrives, the synchronously known state (build env/cache) counts.
  const supported = capable && (data ? data.hasKey : isPushSupported());
  return { supported, active: !!data?.sub };
}

/** Call after (un)subscribing so all push indicators refresh. */
export function useRefreshPush(): () => void {
  const qc = useQueryClient();
  return () => void qc.invalidateQueries({ queryKey: ["push-sub"] });
}
