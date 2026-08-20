import { isImageIcon } from "./poiMeta";
import { lucideComp } from "./poiIcons";

// Renders a POI/category icon: image (<img>) for a path/URL, Lucide icon for a
// known Lucide name, otherwise emoji as text.
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
