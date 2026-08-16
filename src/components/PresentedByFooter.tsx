import { useLocation } from "react-router-dom";
import { useFestival, useSponsors } from "@/data/queries";

// Fußzeile am Ende jeder Seite: Eventname (Festival-Eckdaten), „Presented by",
// darunter die Logos der Hauptsponsoren (Tier „main"). Ohne main-Sponsor
// verschwindet die Fußzeile komplett.
export function PresentedByFooter() {
  const { pathname } = useLocation();
  const { data } = useSponsors();
  const { data: festival } = useFestival();
  const main = (data ?? [])
    .filter((s) => s.tier === "main")
    .sort((a, b) => a.order - b.order);

  // Auf der Sponsoren-Seite weglassen – dort sind die Hauptsponsoren bereits gelistet.
  // Auf Artist-Seiten ebenfalls weglassen.
  if (pathname === "/sponsors" || pathname.startsWith("/artist/")) return null;
  if (main.length === 0) return null;

  return (
    <footer className="flex flex-col items-center gap-2 pt-5 text-center">
      {festival?.name && <span className="text-sm font-semibold">{festival.name}</span>}
      <span className="text-xs uppercase tracking-wide text-rid-muted">Presented by</span>
      <div className="flex flex-wrap items-center justify-center gap-4">
        {main.map((s) => {
          const logo = (
            <img
              src={s.logo}
              alt={s.name}
              loading="lazy"
              className="h-24 w-auto max-w-[216px] object-contain"
            />
          );
          return s.url ? (
            <a key={s.id} href={s.url} target="_blank" rel="noopener noreferrer">
              {logo}
            </a>
          ) : (
            <span key={s.id}>{logo}</span>
          );
        })}
      </div>
    </footer>
  );
}
