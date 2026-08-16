import { ExternalLink } from "lucide-react";
import { useTickets } from "@/data/queries";
import { BackLink } from "@/components/BackLink";
import { LoadingState, ErrorState, EmptyState } from "@/components/states";
import type { TicketProvider } from "@/types";

function TicketEmbed({ provider }: { provider: TicketProvider }) {
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
          Öffnen <ExternalLink size={14} />
        </a>
      </div>
      {provider.note && <p className="text-sm text-rid-muted">{provider.note}</p>}

      {provider.embedType === "iframe" ? (
        // iframe mit restriktivem sandbox (§12.11). Fallback ist der Link oben,
        // falls der Shop Framing per X-Frame-Options/CSP verbietet.
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
          <span className="font-medium">Zum Ticketshop</span>
          <ExternalLink size={18} className="text-rid-accent" />
        </a>
      )}
    </div>
  );
}

export default function Tickets() {
  const { data, isLoading, isError, refetch } = useTickets();

  if (isLoading) return <LoadingState />;
  if (isError) return <ErrorState onRetry={() => void refetch()} />;
  if (!data || data.providers.length === 0) return <EmptyState />;

  return (
    <section className="space-y-6">
      <BackLink to="/more" label="Mehr" />
      <h1 className="text-2xl font-bold">Tickets</h1>
      {data.providers.map((provider) => (
        <TicketEmbed key={provider.id} provider={provider} />
      ))}
    </section>
  );
}
