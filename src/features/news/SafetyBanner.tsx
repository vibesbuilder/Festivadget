import { ShieldAlert } from "lucide-react";
import { Markdown } from "@/components/Markdown";
import type { FeedItem } from "./useNewsFeed";

// Prominente Safety-Hinweise (§12.5).
export function SafetyBanner({ items }: { items: FeedItem[] }) {
  if (items.length === 0) return null;

  return (
    <div className="space-y-2">
      {items.map((item) => (
        <div
          key={item.id}
          className="flex items-start gap-3 rounded-xl border border-rid-accent-2 bg-rid-surface p-3"
        >
          <ShieldAlert size={20} className="mt-0.5 shrink-0 text-rid-accent-2" />
          <div>
            <p className="font-semibold">{item.title}</p>
            {item.body && (
              <div className="text-sm text-rid-text/90">
                <Markdown>{item.body}</Markdown>
              </div>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}
