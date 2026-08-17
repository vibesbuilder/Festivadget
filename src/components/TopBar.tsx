import { Link } from "react-router-dom";
import { Search } from "lucide-react";
import { useFestival } from "@/data/queries";
import { useAppConfig } from "@/data/useAppConfig";
import { PushBellButton } from "./PushBellButton";

// Schlanke obere Leiste: Logo-Grafik (max. 36px hoch, 300px breit) + Glocke + Suche.
export function TopBar() {
  const { data: festival } = useFestival();
  // Kunden-Logo aus dem Branding (CMS); leer = Build-Logo.
  const { branding } = useAppConfig();

  return (
    <header
      className="sticky top-0 z-30 border-b border-rid-border bg-rid-bg/95 backdrop-blur"
      style={{ paddingTop: "var(--safe-top)" }}
    >
      <div className="mx-auto flex max-w-app items-center justify-between gap-3 px-4 py-3">
        <Link to="/" className="flex min-w-0 items-center" aria-label={festival?.name ?? "Home"}>
          <img
            src={branding?.logo || "/img/logo.png"}
            alt={festival?.name ?? "ROCK IM DORF Festival 2026"}
            className="h-9 w-auto max-w-[300px] object-contain"
          />
        </Link>
        <div className="flex shrink-0 items-center gap-1">
          <PushBellButton />
          <Link
            to="/search"
            aria-label="Suche"
            className="rounded-full p-2 text-rid-muted hover:text-rid-accent"
          >
            <Search size={20} />
          </Link>
        </div>
      </div>
    </header>
  );
}
