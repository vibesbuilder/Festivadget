import { isImageIcon } from "./poiMeta";
import { lucideComp } from "./poiIcons";

// Stellt ein POI-/Kategorie-Icon dar: Bild (<img>) bei Pfad/URL, Lucide-Icon bei
// bekanntem Lucide-Namen, sonst Emoji als Text.
export function PoiIcon({
  icon,
  size = 16,
  color,
  alt = "",
}: {
  icon: string;
  size?: number;
  color?: string;
  alt?: string;
}) {
  if (isImageIcon(icon)) {
    return (
      <img
        src={icon}
        alt={alt}
        style={{ width: size, height: size }}
        className="inline-block object-contain align-[-0.15em]"
      />
    );
  }
  const Comp = lucideComp(icon);
  if (Comp) {
    return <Comp size={size} color={color} className="inline-block align-[-0.15em]" />;
  }
  return <span>{icon}</span>;
}
