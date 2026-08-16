import { Link } from "react-router-dom";
import { ChevronRight, ShieldAlert } from "lucide-react";
import { useNewsFeed } from "./useNewsFeed";
import { formatDateTime } from "@/lib/time";

// Kompakte Newsfeed-Vorschau für die Home-Seite: nur die letzten 2 Einträge.
export function NewsfeedPreview() {
  const { items, isLoading } = useNewsFeed();
  if (isLoading || items.length === 0) return null;

  const latest = items.slice(0, 2);

  return (
    <ul className="divide-y divide-rid-border overflow-hidden rounded-xl border border-rid-border bg-rid-surface">
      {latest.map((item) => (
        <li key={item.id}>
          <Link to="/news" className="flex items-center gap-2 px-3 py-2.5 hover:bg-rid-surface-2">
            {item.category === "safety" && (
              <ShieldAlert size={15} className="shrink-0 text-rid-accent-2" />
            )}
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-medium">{item.title}</p>
              <p className="truncate text-xs text-rid-muted">{formatDateTime(item.publishAt)}</p>
            </div>
            <ChevronRight size={16} className="shrink-0 text-rid-muted" />
          </Link>
        </li>
      ))}
    </ul>
  );
}
