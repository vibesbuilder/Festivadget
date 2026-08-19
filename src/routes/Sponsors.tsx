import { useMemo } from "react";
import { useTranslation } from "react-i18next";
import { useSponsors } from "@/data/queries";
import { BackLink } from "@/components/BackLink";
import { LoadingState, ErrorState, EmptyState } from "@/components/states";
import type { Sponsor, SponsorTier } from "@/types";

// Reihenfolge + Beschriftung der Tiers (§7.8, §12.8).
const TIER_ORDER: SponsorTier[] = ["main", "premium", "partner", "supporter"];
const TIER_LABEL_KEY: Record<SponsorTier, string> = {
  main: "sponsors.tier.main",
  premium: "sponsors.tier.premium",
  partner: "sponsors.tier.partner",
  supporter: "sponsors.tier.supporter",
};

function SponsorLogo({ sponsor }: { sponsor: Sponsor }) {
  const inner = (
    <div className="rid-card flex h-24 items-center justify-center overflow-hidden p-3">
      <img
        src={sponsor.logo}
        alt={sponsor.name}
        loading="lazy"
        className="max-h-full max-w-full object-contain"
        onError={(e) => {
          // Fallback: Name als Text, falls Logo fehlt.
          const el = e.currentTarget;
          el.style.display = "none";
          el.insertAdjacentText("afterend", sponsor.name);
        }}
      />
    </div>
  );
  return sponsor.url ? (
    <a href={sponsor.url} target="_blank" rel="noopener noreferrer" className="block">
      {inner}
    </a>
  ) : (
    inner
  );
}

export default function Sponsors() {
  const { t } = useTranslation();
  const { data, isLoading, isError, refetch } = useSponsors();

  const grouped = useMemo(() => {
    const map = new Map<SponsorTier, Sponsor[]>();
    for (const s of data ?? []) {
      const list = map.get(s.tier) ?? [];
      list.push(s);
      map.set(s.tier, list);
    }
    for (const list of map.values()) list.sort((a, b) => a.order - b.order);
    return map;
  }, [data]);

  if (isLoading) return <LoadingState />;
  if (isError) return <ErrorState onRetry={() => void refetch()} />;
  if (!data || data.length === 0) return <EmptyState />;

  return (
    <section className="space-y-6">
      <BackLink to="/more" label={t("nav.more")} />
      <h1 className="text-2xl font-bold">{t("sponsors.title")}</h1>
      {TIER_ORDER.map((tier) => {
        const list = grouped.get(tier);
        if (!list || list.length === 0) return null;
        return (
          <div key={tier}>
            <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-rid-muted">
              {t(TIER_LABEL_KEY[tier])}
            </h2>
            <div className={tier === "main" ? "grid grid-cols-1 gap-3" : "grid grid-cols-2 gap-3"}>
              {list.map((s) => (
                <SponsorLogo key={s.id} sponsor={s} />
              ))}
            </div>
          </div>
        );
      })}
    </section>
  );
}
