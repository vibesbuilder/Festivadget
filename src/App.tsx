import { useEffect } from "react";
import { RouterProvider } from "react-router-dom";
import { QueryClientProvider } from "@tanstack/react-query";
import { queryClient } from "@/data/client";
import { router } from "@/router";
import { useFavorites } from "@/store/favorites";
import { getFirstOpenAt } from "@/lib/firstOpen";
import { syncPushPlan } from "@/lib/push";
import "@/i18n/config";

export default function App() {
  const hydrate = useFavorites((s) => s.hydrate);

  // Favoriten aus IndexedDB laden (§11) + Erstöffnung früh festhalten.
  useEffect(() => {
    void hydrate();
    getFirstOpenAt();
  }, [hydrate]);

  // Favoriten-Änderungen ans „Mein Plan"-Push-Abo durchreichen (entprellt).
  useEffect(() => {
    let t: ReturnType<typeof setTimeout>;
    const unsub = useFavorites.subscribe((state, prev) => {
      if (state.favorites === prev.favorites) return;
      clearTimeout(t);
      t = setTimeout(() => void syncPushPlan(), 800);
    });
    return () => {
      clearTimeout(t);
      unsub();
    };
  }, []);

  return (
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>
  );
}
