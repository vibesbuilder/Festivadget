import { useTranslation } from "react-i18next";
import { useMemo, useState } from "react";
import { useMapConfig, usePois, usePoiCategories, useStages } from "@/data/queries";
import { LoadingState, ErrorState, EmptyState } from "@/components/states";
import { FestivalMap } from "@/features/map/FestivalMap";
import { PoiFilterBar } from "@/features/map/PoiFilterBar";
import { PoiSheet } from "@/features/map/PoiSheet";
import { resolvePoiMeta } from "@/features/map/poiMeta";
import type { Poi } from "@/types";

export default function MapPage() {
  const { t } = useTranslation();
  const { data: config, isLoading: l1, isError, refetch } = useMapConfig();
  const { data: pois, isLoading: l2 } = usePois();
  const { data: categories } = usePoiCategories();
  const { data: stages } = useStages();

  const [active, setActive] = useState<Set<string>>(new Set());
  const [selected, setSelected] = useState<Poi | null>(null);

  // Categories as a Map (fast lookup) + set of hidden ones (master toggle).
  const catMap = useMemo(
    () => new Map((categories ?? []).map((c) => [c.id, c])),
    [categories],
  );
  const hiddenIds = useMemo(
    () => new Set((categories ?? []).filter((c) => c.hidden).map((c) => c.id)),
    [categories],
  );

  // Hidden categories are removed entirely (for all visitors).
  const visiblePois = useMemo(
    () => (pois ?? []).filter((p) => !hiddenIds.has(p.type)),
    [pois, hiddenIds],
  );

  // Filter chips only for actually present, visible categories - by category order.
  const available = useMemo(() => {
    const present = new Set(visiblePois.map((p) => p.type));
    const orderOf = (id: string) => catMap.get(id)?.order ?? 999;
    return Array.from(present)
      .sort((a, b) => orderOf(a) - orderOf(b))
      .map((id) => {
        const meta = resolvePoiMeta(id, catMap);
        return { id, label: meta.label, icon: meta.icon };
      });
  }, [visiblePois, catMap]);

  const filtered = useMemo(
    () => (active.size === 0 ? visiblePois : visiblePois.filter((p) => active.has(p.type))),
    [visiblePois, active],
  );

  const toggle = (id: string) => {
    setActive((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const selectedStage = selected?.stageId
    ? stages?.find((s) => s.id === selected.stageId)
    : undefined;

  if (l1 || l2) return <LoadingState />;
  if (isError || !config) return <ErrorState onRetry={() => void refetch()} />;
  if (!pois || pois.length === 0) return <EmptyState label={t("map.noPois")} />;

  return (
    <section className="space-y-3">
      <h1 className="text-2xl font-bold">{t("map.title")}</h1>
      <PoiFilterBar
        available={available}
        active={active}
        onToggle={toggle}
        onReset={() => setActive(new Set())}
      />
      {/* Flexible height: dynamic viewport (dvh) minus header/filter/bottom nav -
          70vh overlapped the bottom menu on phones. min-h as a safety net.
          isolate: Leaflet internally uses z-index up to 1000 and would otherwise
          paint over the sticky header/bottom nav (z-30). */}
      <div className="relative isolate z-0 h-[calc(100dvh_-_248px)] min-h-[320px] overflow-hidden rounded-xl border border-rid-border bg-rid-surface">
        <FestivalMap config={config} pois={filtered} categories={catMap} onSelect={setSelected} />
        {selected && (
          <PoiSheet
            poi={selected}
            meta={resolvePoiMeta(selected.type, catMap)}
            stage={selectedStage}
            onClose={() => setSelected(null)}
          />
        )}
      </div>
    </section>
  );
}
