import { ShieldAlert } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Markdown } from "@/components/Markdown";
import { lt } from "@/lib/localized";
import type { FeedItem } from "./useNewsFeed";

// Prominent safety notices (§12.5).
export function SafetyBanner({ items }: { items: FeedItem[] }) {
  const { i18n } = useTranslation();
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
            <p className="font-semibold">{lt(item.title, i18n.language)}</p>
            {lt(item.body, i18n.language) && (
              <div className="text-sm text-rid-text/90">
                <Markdown>{lt(item.body, i18n.language)}</Markdown>
              </div>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}
