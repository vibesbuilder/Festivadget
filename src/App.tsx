import { useEffect } from "react";
import { RouterProvider } from "react-router-dom";
import { QueryClientProvider } from "@tanstack/react-query";
import { queryClient } from "@/data/client";
import { router } from "@/router";
import { useFavorites } from "@/store/favorites";
import { getFirstOpenAt } from "@/lib/firstOpen";
import { syncPushLanguage, syncPushPlan } from "@/lib/push";
import { useUi } from "@/store/ui";
import "@/i18n/config";

export default function App() {
  const hydrate = useFavorites((s) => s.hydrate);

  // Load favorites from IndexedDB (§11) + record the first open early.
  useEffect(() => {
    void hydrate();
    getFirstOpenAt();
  }, [hydrate]);

  // Forward favorite changes to the "My plan" push subscription (debounced).
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

  // Forward language changes to the push subscription (push texts follow it).
  useEffect(() => {
    const unsub = useUi.subscribe((state, prev) => {
      if (state.language !== prev.language) void syncPushLanguage();
    });
    return unsub;
  }, []);

  return (
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>
  );
}
