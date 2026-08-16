import { Megaphone, Info, Music2, ShieldAlert } from "lucide-react";
import type { NewsCategory } from "@/types";
import { Markdown } from "@/components/Markdown";
import { formatDateTime } from "@/lib/time";
import type { FeedItem } from "./useNewsFeed";

const CATEGORY_ICON: Record<NewsCategory, typeof Info> = {
  general: Megaphone,
  info: Info,
  lineup: Music2,
  safety: ShieldAlert,
};

export function NewsItemCard({ item }: { item: FeedItem }) {
  // Fallback, falls eine unbekannte Kategorie in den Daten steht (kein Crash).
  const Icon = CATEGORY_ICON[item.category] ?? Megaphone;

  return (
    <article className="rid-card p-4">
      <div className="mb-1 flex items-center gap-2 text-xs text-rid-muted">
        <Icon size={14} className="text-rid-accent" />
        <span>{formatDateTime(item.publishAt)}</span>
        {item.pinned && (
          <span className="rounded bg-rid-accent px-1.5 text-[10px] font-bold text-black">
            Angepinnt
          </span>
        )}
      </div>
      <h2 className="font-semibold">{item.title}</h2>
      {item.body && (
        <div className="mt-1 text-sm">
          <Markdown>{item.body}</Markdown>
        </div>
      )}
      {item.image && (
        <img src={item.image} alt="" className="mt-3 w-full rounded-lg" loading="lazy" />
      )}
      {item.link && (
        <a
          href={item.link.url}
          target="_blank"
          rel="noopener noreferrer"
          className="mt-2 inline-block text-sm text-rid-accent underline underline-offset-2"
        >
          {item.link.label}
        </a>
      )}
    </article>
  );
}
