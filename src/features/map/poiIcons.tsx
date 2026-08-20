import { renderToStaticMarkup } from "react-dom/server";
import {
  Ambulance,
  Cross,
  Plus,
  Utensils,
  Beer,
  Coffee,
  Pizza,
  Wine,
  CookingPot,
  Car,
  Bus,
  TrainFront,
  Bike,
  SquareParking,
  CircleParking,
  Tent,
  Caravan,
  Music,
  Mic,
  Guitar,
  Disc3,
  Info,
  BadgeInfo,
  Ticket,
  Tickets,
  ShowerHead,
  Bath,
  Baby,
  Dog,
  Accessibility,
  CreditCard,
  ShoppingBag,
  Box,
  Shirt,
  Wifi,
  Phone,
  MapPin,
  Flag,
  Star,
  Heart,
  Flame,
  Trees,
  Sun,
  Umbrella,
  DoorOpen,
  LogOut,
  SquareArrowRight,
  SquareArrowOutUpRight,
  Shield,
  Droplet,
  Zap,
  Anchor,
  Cigarette,
  type LucideIcon,
} from "lucide-react";
import { isImageIcon, escapeHtml } from "./poiMeta";

// Curated Lucide icons for POIs/categories. Key = Lucide slug (lucide.dev).
// A value may also be an alias. Update docs/DATEN.md (list) on changes.
export const LUCIDE_POI_ICONS: Record<string, LucideIcon> = {
  ambulance: Ambulance,
  "first-aid": Cross,
  cross: Cross,
  plus: Plus,
  utensils: Utensils,
  food: Utensils,
  beer: Beer,
  coffee: Coffee,
  pizza: Pizza,
  wine: Wine,
  "cooking-pot": CookingPot,
  car: Car,
  bus: Bus,
  "train-front": TrainFront,
  train: TrainFront,
  bike: Bike,
  "square-parking": SquareParking,
  parking: SquareParking,
  "circle-parking": CircleParking,
  tent: Tent,
  caravan: Caravan,
  music: Music,
  mic: Mic,
  guitar: Guitar,
  "disc-3": Disc3,
  dj: Disc3,
  info: Info,
  "badge-info": BadgeInfo,
  ticket: Ticket,
  tickets: Tickets,
  "shower-head": ShowerHead,
  shower: ShowerHead,
  bath: Bath,
  baby: Baby,
  dog: Dog,
  accessibility: Accessibility,
  "credit-card": CreditCard,
  "shopping-bag": ShoppingBag,
  box: Box,
  shirt: Shirt,
  wifi: Wifi,
  phone: Phone,
  "map-pin": MapPin,
  flag: Flag,
  star: Star,
  heart: Heart,
  flame: Flame,
  trees: Trees,
  sun: Sun,
  umbrella: Umbrella,
  "door-open": DoorOpen,
  "log-out": LogOut,
  exit: LogOut,
  "square-arrow-right": SquareArrowRight,
  "square-arrow-right-exit": SquareArrowRight,
  "square-arrow-out-up-right": SquareArrowOutUpRight,
  shield: Shield,
  droplet: Droplet,
  zap: Zap,
  anchor: Anchor,
  cigarette: Cigarette,
};

// Lucide component for an icon value (name), if available.
export function lucideComp(icon?: string): LucideIcon | undefined {
  return icon ? LUCIDE_POI_ICONS[icon.trim().toLowerCase()] : undefined;
}

export function isLucideName(icon?: string): boolean {
  return !!lucideComp(icon);
}

// Inner HTML of the map marker: image (<img>) > Lucide SVG > emoji text.
export function poiMarkerHtml(icon: string, size: number, color: string): string {
  if (isImageIcon(icon)) {
    return `<img src="${escapeHtml(icon)}" alt="" style="width:${size}px;height:${size}px;object-fit:contain;display:block" />`;
  }
  const Comp = lucideComp(icon);
  if (Comp) {
    return renderToStaticMarkup(<Comp size={size} color={color} />);
  }
  return escapeHtml(icon);
}
