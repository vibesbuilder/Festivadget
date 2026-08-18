import { Link } from "react-router-dom";
import { useTranslation } from "react-i18next";
import i18n from "@/i18n/config";
import { ChevronRight } from "lucide-react";
import { NowNextWidget } from "@/components/NowNextWidget";
import { NewsfeedPreview } from "@/features/news/NewsfeedPreview";
import { NotificationsToggle } from "@/components/NotificationsToggle";
import { HomeVideo } from "@/components/HomeVideo";
import { WeatherHomeCard } from "@/features/weather/WeatherHomeCard";
import { useFestival } from "@/data/queries";
import { useAppConfig } from "@/data/useAppConfig";
import { formatDateRange } from "@/lib/time";

export default function Home() {
  const { t } = useTranslation();
  const { data: festival } = useFestival();
  // Kopf (Name + Datum) per Admin-Einstellung ausblendbar (fehlender Key = anzeigen).
  const { homeHeader } = useAppConfig();

  // Titel/Datum aus festival.json (im Admin unter „Inhalte" → „Festival" pflegbar).
  const title = festival?.name ?? "Festivadget";
  const dateRange =
    festival?.start && festival?.end
      ? formatDateRange(festival.start, festival.end, festival.timezone, i18n.language)
      : "";

  return (
    <div className="space-y-6">
      {homeHeader !== false && (
        <section>
          <h1 className="text-xl font-bold">{title}</h1>
          {dateRange && <p className="text-rid-muted">{dateRange}</p>}
        </section>
      )}

      <NotificationsToggle />

      {/* Intro-Video (CMS-Tab „Branding") – volle Breite oberhalb des Newsfeeds. */}
      <HomeVideo />

      {/* Newsfeed zuerst (oberhalb der Programmübersicht). */}
      <section>
        <div className="mb-2 flex items-center justify-between">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-rid-muted">
            {t("home.newsfeed")}
          </h2>
          <Link
            to="/news"
            className="inline-flex items-center text-sm text-rid-accent hover:underline"
          >
            {t("home.all")} <ChevronRight size={16} />
          </Link>
        </div>
        <NewsfeedPreview />
      </section>

      {/* Live-Wetter (GeoSphere, self-gating – verschwindet ohne Daten). */}
      <WeatherHomeCard />

      <section>
        <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-rid-muted">
          {t("home.overview")}
        </h2>
        <NowNextWidget />
      </section>
    </div>
  );
}
