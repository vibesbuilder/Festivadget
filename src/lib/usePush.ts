import { useQuery, useQueryClient } from "@tanstack/react-query";
import { isPushCapable, isPushSupported, ensureVapidKey, currentSubscription } from "./push";

// Geteilter Push-Status (über die Query-Cache), damit Home-Schalter und
// Header-Glocke synchron sind: aktiviert man Push am Schalter, erscheint die
// Glocke sofort (und umgekehrt). Holt nebenbei den VAPID-Key vom Backend,
// falls er nicht schon aus Build-Env/localStorage bekannt ist.
export function usePushActive(): { supported: boolean; active: boolean } {
  const capable = isPushCapable();
  const { data } = useQuery({
    queryKey: ["push-sub"],
    // Wirft nie (ensureVapidKey fängt Netzwerkfehler) → kein Retry/Pause-Limbo.
    queryFn: async () => {
      const key = await ensureVapidKey();
      return { hasKey: !!key, sub: key ? await currentSubscription() : null };
    },
    enabled: capable,
    staleTime: Infinity,
    retry: false,
  });
  // Bis zur Antwort zählt der synchron bekannte Stand (Build-Env/Cache).
  const supported = capable && (data ? data.hasKey : isPushSupported());
  return { supported, active: !!data?.sub };
}

/** Nach (Ab-)Abonnieren aufrufen, damit alle Push-Anzeigen aktualisieren. */
export function useRefreshPush(): () => void {
  const qc = useQueryClient();
  return () => void qc.invalidateQueries({ queryKey: ["push-sub"] });
}
