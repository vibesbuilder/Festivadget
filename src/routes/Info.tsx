import { useMemo } from "react";
import { useTranslation } from "react-i18next";
import { Link } from "react-router-dom";
import { ChevronRight } from "lucide-react";
import { useInfo } from "@/data/useInfo";
import { InfoIcon } from "@/components/InfoIcon";
import { BackLink } from "@/components/BackLink";
import { LoadingState, ErrorState, EmptyState } from "@/components/states";

export default function Info() {
  const { t } = useTranslation();
  const { data, isLoading, isError, refetch } = useInfo();

  const pages = useMemo(
    () =>
      data
        ? data
            .filter((p) => !p.hidden) // versteckte Einträge nicht im Menü zeigen
            .sort((a, b) => a.order - b.order)
        : [],
    [data],
  );

  if (isLoading) return <LoadingState />;
  if (isError) return <ErrorState onRetry={() => void refetch()} />;
  if (pages.length === 0) return <EmptyState />;

  return (
    <section className="space-y-2">
      <BackLink to="/more" label={t("nav.more")} />
      <h1 className="mb-4 text-2xl font-bold">{t("info.title")}</h1>
      <ul className="space-y-2">
        {pages.map((page) => (
          <li key={page.id}>
            <Link
              to={`/info/${page.id}`}
              className="rid-card flex items-center gap-3 p-4 hover:border-rid-accent"
            >
              <InfoIcon name={page.icon} />
              <span className="flex-1 font-medium">{page.title}</span>
              <ChevronRight size={18} className="text-rid-muted" />
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
