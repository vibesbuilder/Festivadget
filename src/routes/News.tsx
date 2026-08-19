import { useTranslation } from "react-i18next";
import { useNewsFeed } from "@/features/news/useNewsFeed";
import { SafetyBanner } from "@/features/news/SafetyBanner";
import { NewsItemCard } from "@/features/news/NewsItemCard";
import { BackLink } from "@/components/BackLink";
import { LoadingState, EmptyState } from "@/components/states";

export default function News() {
  const { t } = useTranslation();
  const { items, safety, isLoading } = useNewsFeed();

  if (isLoading) return <LoadingState />;

  // Nur die im Banner hervorgehobenen (gepinnten) Safety-Items nicht doppelt
  // listen; ungepinnte Sicherheits-News reihen sich normal in den Feed ein.
  const feedWithoutSafetyDupes = items.filter((i) => !(i.category === "safety" && i.pinned));

  return (
    <section className="space-y-4">
      <BackLink to="/more" label={t("nav.more")} />
      <h1 className="text-2xl font-bold">{t("news.title")}</h1>

      <SafetyBanner items={safety} />

      {feedWithoutSafetyDupes.length === 0 && safety.length === 0 ? (
        <EmptyState />
      ) : (
        <div className="space-y-3">
          {feedWithoutSafetyDupes.map((item) => (
            <NewsItemCard key={item.id} item={item} />
          ))}
        </div>
      )}
    </section>
  );
}
