import { Link } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { ChevronRight, ShieldAlert } from "lucide-react";
import { useNewsFeed } from "./useNewsFeed";
import { formatDateTime } from "@/lib/time";
import { lt } from "@/lib/localized";

// Compact newsfeed preview for the home page: only the latest 2 entries.
export function NewsfeedPreview() {
  const { i18n } = useTranslation();
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
              <p className="truncate text-sm font-medium">{lt(item.title, i18n.language)}</p>
              <p className="truncate text-xs text-rid-muted">{formatDateTime(item.publishAt, undefined, i18n.language)}</p>
            </div>
            <ChevronRight size={16} className="shrink-0 text-rid-muted" />
          </Link>
        </li>
      ))}
    </ul>
  );
}
