import {
  Cloud,
  CloudFog,
  CloudHail,
  CloudLightning,
  CloudMoon,
  CloudRain,
  CloudRainWind,
  CloudSnow,
  CloudSun,
  Cloudy,
  Moon,
  Snowflake,
  Sun,
  type LucideIcon,
} from "lucide-react";

// Icon-Codes des Wetter-Endpoints (push/weather.php, GeoSphere-sy normalisiert) –
// gleiche Basen wie in CrewCare.
const ICONS: Record<string, LucideIcon> = {
  "clear-day": Sun,
  "clear-night": Moon,
  "partly-day": CloudSun,
  "partly-night": CloudMoon,
  cloudy: Cloud,
  overcast: Cloudy,
  fog: CloudFog,
  rain: CloudRain,
  "heavy-rain": CloudRainWind,
  snow: CloudSnow,
  "heavy-snow": Snowflake,
  sleet: CloudHail,
  thunderstorm: CloudLightning,
};

export function WeatherIcon({
  icon,
  size = 22,
  className,
}: {
  icon: string;
  size?: number;
  className?: string;
}) {
  const Icon = ICONS[icon] ?? Cloud;
  return <Icon size={size} className={className} aria-hidden />;
}
