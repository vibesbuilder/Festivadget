import { useLocation } from "react-router-dom";
import { useFestival, useSponsors } from "@/data/queries";

// Footer at the end of every page: event name (festival facts), "Presented by",
// below it the logos of the main sponsors (tier "main"). Without a main sponsor
// the footer disappears entirely.
export function PresentedByFooter() {
  const { pathname } = useLocation();
  const { data } = useSponsors();
  const { data: festival } = useFestival();
  const main = (data ?? [])
    .filter((s) => s.tier === "main")
    .sort((a, b) => a.order - b.order);

  // Omit on the sponsors page - the main sponsors are already listed there.
  // Also omit on artist pages.
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
