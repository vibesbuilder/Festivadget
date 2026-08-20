import { useTranslation } from "react-i18next";
import { ExternalLink } from "lucide-react";
import { useTickets } from "@/data/queries";
import { BackLink } from "@/components/BackLink";
import { LoadingState, ErrorState, EmptyState } from "@/components/states";
import type { TicketProvider } from "@/types";

function TicketEmbed({ provider }: { provider: TicketProvider }) {
  const { t } = useTranslation();

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <h2 className="font-semibold">{provider.name}</h2>
        <a
          href={provider.url}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1 text-sm text-rid-accent hover:underline"
        >
          {t("tickets.open")} <ExternalLink size={14} />
        </a>
      </div>
      {provider.note && <p className="text-sm text-rid-muted">{provider.note}</p>}

      {provider.embedType === "iframe" ? (
        // iframe with a restrictive sandbox (§12.11). Fallback is the link above,
        // in case the shop forbids framing via X-Frame-Options/CSP.
        <iframe
          title={provider.name}
          src={provider.url}
          className="h-[70vh] w-full rounded-xl border border-rid-border bg-white"
          sandbox="allow-scripts allow-forms allow-same-origin allow-popups"
          allow="payment"
        />
      ) : (
        <a
          href={provider.url}
          target="_blank"
          rel="noopener noreferrer"
          className="rid-card flex items-center justify-between p-4 hover:border-rid-accent"
        >
          <span className="font-medium">{t("tickets.shop")}</span>
          <ExternalLink size={18} className="text-rid-accent" />
        </a>
      )}
    </div>
  );
}

export default function Tickets() {
  const { t } = useTranslation();
  const { data, isLoading, isError, refetch } = useTickets();

  if (isLoading) return <LoadingState />;
  if (isError) return <ErrorState onRetry={() => void refetch()} />;
  if (!data || data.providers.length === 0) return <EmptyState />;

  return (
    <section className="space-y-6">
      <BackLink to="/more" label={t("nav.more")} />
      <h1 className="text-2xl font-bold">{t("tickets.title")}</h1>
      {data.providers.map((provider) => (
        <TicketEmbed key={provider.id} provider={provider} />
      ))}
    </section>
  );
}
