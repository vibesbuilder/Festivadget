import { Link } from "react-router-dom";
import { ChevronRight } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useLiveWeather } from "./useLiveWeather";
import { WeatherIcon } from "./icons";

function temp(v: number | null | undefined): string {
  return typeof v === "number" ? `${Math.round(v)}°` : "–";
}

// Compact home weather block (like CrewCare): only today + tomorrow, one click
// opens the weather page. Self-gating: invisible without data/on error.
export function WeatherHomeCard() {
  const { t } = useTranslation();
  const { data } = useLiveWeather();
  if (!data?.ok || data.days.length === 0) return null;
  const [today, tomorrow] = data.days;

  return (
    <Link to="/weather" className="rid-card flex items-center gap-3 px-3 py-2 hover:border-rid-accent">
      <span className="shrink-0 text-lg font-semibold">{t("weather.title")}</span>
      <span className="inline-flex items-center gap-1.5">
        <WeatherIcon icon={today.icon} size={20} className="text-rid-accent" />
        <span className="text-sm font-semibold">{temp(today.max)}</span>
        <span className="text-xs text-rid-muted">{t("weather.todayShort")}</span>
      </span>
      {tomorrow && (
        <span className="inline-flex items-center gap-1.5">
          <WeatherIcon icon={tomorrow.icon} size={20} className="text-rid-muted" />
          <span className="text-sm">{temp(tomorrow.max)}</span>
          <span className="text-xs text-rid-muted">{t("weather.tomorrowShort")}</span>
        </span>
      )}
      {data.stale && (
        <span className="h-1.5 w-1.5 rounded-full bg-rid-accent" title={t("weather.stale")} />
      )}
      <ChevronRight size={16} className="ml-auto shrink-0 text-rid-muted" />
    </Link>
  );
}
