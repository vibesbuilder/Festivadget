import { useMemo } from "react";
import { Link, useParams } from "react-router-dom";
import { useInfo } from "@/data/useInfo";
import { Markdown } from "@/components/Markdown";
import { InfoIcon } from "@/components/InfoIcon";
import { FaqAccordion } from "@/components/FaqAccordion";
import { LoadingState, EmptyState } from "@/components/states";

export default function InfoDetail() {
  const { id } = useParams<{ id: string }>();
  const { data, isLoading } = useInfo();

  const page = useMemo(() => data?.find((p) => p.id === id), [data, id]);

  if (isLoading) return <LoadingState />;
  if (!page) return <EmptyState label="Info-Seite nicht gefunden." />;

  return (
    <article className="space-y-4">
      <Link to="/info" className="text-sm text-rid-muted hover:text-rid-accent">
        ← Infos
      </Link>
      <header className="flex items-center gap-3">
        <InfoIcon name={page.icon} size={24} />
        <h1 className="text-2xl font-bold">{page.title}</h1>
      </header>

      {/* Accordion, wenn faq-Flag gesetzt (Fallback: bestehende "faq"-Seite ohne Flag),
          sonst Markdown auf deckendem rid-card-Hintergrund (§12.9). */}
      {(page.faq ?? page.id === "faq") ? (
        <FaqAccordion body={page.body} />
      ) : (
        <div className="rid-card p-4">
          <Markdown>{page.body}</Markdown>
        </div>
      )}
    </article>
  );
}
