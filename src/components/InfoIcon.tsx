import {
  Car,
  Tent,
  CreditCard,
  HelpCircle,
  MapPin,
  Utensils,
  Beer,
  Bus,
  ScrollText,
  SquareParking,
  Info as InfoIcon2,
  type LucideIcon,
} from "lucide-react";

// Mapping der info.icon-Strings (§7.9) auf lucide-Icons.
const ICONS: Record<string, LucideIcon> = {
  car: Car,
  tent: Tent,
  "credit-card": CreditCard,
  "help-circle": HelpCircle,
  gelaende: MapPin,
  food: Utensils,
  kulinarik: Utensils,
  drink: Beer,
  getraenke: Beer,
  shuttle: Bus,
  bus: Bus,
  platzordnung: ScrollText,
  parken: SquareParking,
  parking: SquareParking,
};

export function InfoIcon({ name, size = 20 }: { name?: string; size?: number }) {
  const Icon = (name && ICONS[name]) || InfoIcon2;
  return <Icon size={size} className="text-rid-accent" />;
}
