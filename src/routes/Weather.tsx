import { MapPin } from "lucide-react";
import { useTranslation } from "react-i18next";
import { BackLink } from "@/components/BackLink";
import { LoadingState, ErrorState } from "@/components/states";
import { useLiveWeather, type LiveWeatherDay, type LiveWeatherSection } from "@/features/weather/useLiveWeather";
import { WeatherIcon } from "@/features/weather/icons";

function temp(v: number | null | undefined): string {
  return typeof v === "number" ? `${Math.round(v)}°` : "–";
}

// Ein Tag liest sich Morgens → Mittags → Abends → Nachts (kommende Nacht).
const SECTION_KEYS = ["morning", "noon", "evening", "night"] as const;

// Wetterseite (portiert aus CrewCare): Heute/Morgen/Übermorgen mit je vier
// Tagesabschnitten (Temperatur, Niederschlag, Wind) + GeoSphere-Attribution.
export default function Weather() {
  const { t, i18n } = useTranslation();
  const { data, isLoading, isError, refetch } = useLiveWeather();

  if (isLoading) return <LoadingState />;
  if (isError || !data?.ok) return <ErrorState onRetry={() => void refetch()} />;

  const dayLabel = (index: number) =>
    [t("weather.today"), t("weather.tomorrow"), t("weather.dayAfter")][index] ?? "";
  const weekday = (date: string) =>
    new Date(`${date}T12:00:00`).toLocaleDateString(
      i18n.language === "en" ? "en-GB" : "de-AT",
      { weekday: "long" },
    );
  const updated = new Date(data.fetchedAt).toLocaleString(
    i18n.language === "en" ? "en-GB" : "de-AT",
    { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" },
  );

  return (
    <section className="space-y-4">
      <BackLink to="/more" label={t("more.title")} />
      <h1 className="text-2xl font-bold">{t("weather.title")}</h1>

      {/* Kopf: Standort + aktuelle Temperatur + letzte Aktualisierung */}
      <div className="space-y-1">
        <p className="inline-flex items-center gap-1.5 text-sm font-medium">
          <MapPin size={15} className="text-rid-accent" /> {data.location}
        </p>
        {data.current && (
          <p className="flex items-center gap-2">
            <WeatherIcon icon={data.current.icon} size={28} className="text-rid-accent" />
            <span className="text-2xl font-bold tabular-nums">{temp(data.current.temp)}</span>
            <span className="text-xs text-rid-muted">{t("weather.now")}</span>
          </p>
        )}
        <p className="text-xs text-rid-muted">
          {t("weather.updated")}: {updated}
          {data.stale && ` · ${t("weather.stale")}`}
        </p>
      </div>

      {/* Tageskarten: Heute / Morgen / Übermorgen */}
      <div className="space-y-3">
        {data.days.map((day, i) => (
          <DayCard key={day.date} day={day} label={dayLabel(i)} weekday={weekday(day.date)} />
        ))}
      </div>

      {/* Quellenangabe (CC BY 4.0) */}
      <p className="pt-1 text-center text-xs text-rid-muted">{data.attribution}</p>
    </section>
  );
}

function DayCard({ day, label, weekday }: { day: LiveWeatherDay; label: string; weekday: string }) {
  const { t } = useTranslation();
  return (
    <div className="rid-card space-y-3 p-3">
      <div className="flex items-center justify-between gap-2">
        <p className="min-w-0 font-semibold">
          {label}
          <span className="font-normal text-rid-muted">, {weekday}</span>
        </p>
        <div className="flex shrink-0 items-center gap-2">
          <WeatherIcon icon={day.icon} size={30} className="text-rid-accent" />
          <span className="text-2xl font-bold tabular-nums">{temp(day.max)}</span>
        </div>
      </div>
      {day.noData ? (
        <p className="text-sm text-rid-muted">{t("weather.noData")}</p>
      ) : (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
          {SECTION_KEYS.map((key) => (
            <SectionCard key={key} label={t(`weather.section.${key}`)} section={day.sections[key]} />
          ))}
        </div>
      )}
    </div>
  );
}

function SectionCard({ label, section }: { label: string; section: LiveWeatherSection }) {
  const { t } = useTranslation();
  return (
    <div className="space-y-1 rounded-lg bg-rid-surface-2 p-2 text-center">
      <p className="text-xs font-medium text-rid-muted">{label}</p>
      {section.noData ? (
        <p className="py-2 text-xs text-rid-muted">{t("weather.noData")}</p>
      ) : (
        <>
          <WeatherIcon icon={section.icon} size={22} className="mx-auto" />
          <p className="text-sm font-semibold tabular-nums">{temp(section.maxTemp)}</p>
          <p className="text-xs text-rid-muted">{section.precipitation} mm</p>
          {section.windSpeed != null && (
            <p className="text-xs text-rid-muted">
              {section.windSpeed} km/h {section.windDirectionText ?? ""}
            </p>
          )}
        </>
      )}
    </div>
  );
}
