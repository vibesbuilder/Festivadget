import { Star } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useFavorites } from "@/store/favorites";

// Favoriten-Stern auf Slot-Ebene (§12.3). Gelb gefüllt, wenn favorisiert.
export function FavoriteButton({ slotId, label }: { slotId: string; label?: boolean }) {
  const { t } = useTranslation();
  const isFavorite = useFavorites((s) => s.favorites.has(slotId));
  const toggle = useFavorites((s) => s.toggle);

  return (
    <button
      onClick={() => toggle(slotId)}
      aria-pressed={isFavorite}
      aria-label={isFavorite ? t("artist.favorited") : t("artist.favorite")}
      className={[
        "inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition-colors",
        isFavorite
          ? "bg-rid-accent text-black"
          : "bg-rid-surface-2 text-rid-text hover:bg-rid-surface",
      ].join(" ")}
    >
      <Star size={16} fill={isFavorite ? "currentColor" : "none"} />
      {label && <span>{isFavorite ? t("artist.favorited") : t("artist.favorite")}</span>}
    </button>
  );
}
