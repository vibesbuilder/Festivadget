import { useQuery, useQueryClient } from "@tanstack/react-query";
import { isPushSupported, currentSubscription } from "./push";

// Geteilter Push-Status (über die Query-Cache), damit Home-Schalter und
// Header-Glocke synchron sind: aktiviert man Push am Schalter, erscheint die
// Glocke sofort (und umgekehrt).
export function usePushActive(): { supported: boolean; active: boolean } {
  const supported = isPushSupported();
  const { data } = useQuery({
    queryKey: ["push-sub"],
    queryFn: () => currentSubscription(),
    enabled: supported,
    staleTime: Infinity,
  });
  return { supported, active: !!data };
}

/** Nach (Ab-)Abonnieren aufrufen, damit alle Push-Anzeigen aktualisieren. */
export function useRefreshPush(): () => void {
  const qc = useQueryClient();
  return () => void qc.invalidateQueries({ queryKey: ["push-sub"] });
}
