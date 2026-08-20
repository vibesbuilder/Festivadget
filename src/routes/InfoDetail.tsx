import { useMemo } from "react";
import { Link, useParams } from "react-router-dom";
import { useInfo } from "@/data/useInfo";
import { Markdown } from "@/components/Markdown";
import { InfoIcon } from "@/components/InfoIcon";
import { FaqAccordion } from "@/components/FaqAccordion";
import { LoadingState, EmptyState } from "@/components/states";
import { useTranslation } from "react-i18next";
import { lt } from "@/lib/localized";

export default function InfoDetail() {
  const { id } = useParams<{ id: string }>();
  const { t, i18n } = useTranslation();
  const { data, isLoading } = useInfo();

  const page = useMemo(() => data?.find((p) => p.id === id), [data, id]);

  if (isLoading) return <LoadingState />;
  if (!page) return <EmptyState label={t("info.notFound")} />;

  return (
    <article className="space-y-4">
      <Link to="/info" className="text-sm text-rid-muted hover:text-rid-accent">
        ← {t("info.title")}
      </Link>
      <header className="flex items-center gap-3">
        <InfoIcon name={page.icon} size={24} />
        <h1 className="text-2xl font-bold">{lt(page.title, i18n.language)}</h1>
      </header>

      {/* Accordion when the faq flag is set (fallback: the existing "faq" page without
          the flag), otherwise Markdown on an opaque rid-card background (§12.9). */}
      {(page.faq ?? page.id === "faq") ? (
        <FaqAccordion body={lt(page.body, i18n.language)} />
      ) : (
        <div className="rid-card p-4">
          <Markdown>{lt(page.body, i18n.language)}</Markdown>
        </div>
      )}
    </article>
  );
}
