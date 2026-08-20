import { useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import { Link } from "react-router-dom";
import { Search as SearchIcon, Music2, CalendarClock, Info, MapPin } from "lucide-react";
import { useSearchIndex, runSearch, type SearchKind } from "@/features/search/useSearchIndex";
import { BackLink } from "@/components/BackLink";

const KIND_ICON: Record<SearchKind, typeof Info> = {
  artist: Music2,
  slot: CalendarClock,
  info: Info,
  poi: MapPin,
};

export default function Search() {
  const { t } = useTranslation();
  const [query, setQuery] = useState("");
  const index = useSearchIndex();
  const results = useMemo(() => runSearch(index, query), [index, query]);

  return (
    <section className="space-y-4">
      <BackLink to="/more" label={t("nav.more")} />
      <h1 className="text-2xl font-bold">{t("search.title")}</h1>

      <div className="flex items-center gap-2 rounded-xl border border-rid-border bg-rid-surface px-3 py-2">
        <SearchIcon size={18} className="text-rid-muted" />
        <input
          autoFocus
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Acts, Slots, Infos, Orte …"
          className="w-full bg-transparent text-rid-text outline-none placeholder:text-rid-muted"
        />
      </div>

      {query.trim() && results.length === 0 && (
        <p className="py-8 text-center text-rid-muted">{t("search.noResults", { query })}</p>
      )}

      <ul className="space-y-2">
        {results.map((r, i) => {
          const Icon = KIND_ICON[r.kind];
          return (
            <li key={`${r.to}-${i}`}>
              <Link
                to={r.to}
                className="rid-card flex items-center gap-3 p-3 hover:border-rid-accent"
              >
                <Icon size={18} className="shrink-0 text-rid-accent" />
                <div className="min-w-0 flex-1">
                  <p className="truncate font-medium">{r.label}</p>
                  <p className="truncate text-xs text-rid-muted">{r.sublabel}</p>
                </div>
              </Link>
            </li>
          );
        })}
      </ul>
    </section>
  );
}
