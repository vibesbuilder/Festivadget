import { useTranslation } from "react-i18next";
import { useAppConfig } from "@/data/useAppConfig";

// Intro-Video auf der Home-Seite (CMS-Tab „Branding"): volle Breite oberhalb
// des Newsfeeds. Quellen wie bei CrewCare – Microsoft-Cloud (OneDrive/
// SharePoint-„Einbetten"-Link) als iframe, direkte Videodateien (FTP/Uploads
// oder https-Link) als <video>, YouTube/Vimeo automatisch als Player-iframe.

function embedUrl(url: string): string | null {
  const yt = url.match(
    /(?:youtube\.com\/(?:watch\?(?:.*&)?v=|shorts\/|embed\/)|youtu\.be\/)([\w-]{6,})/,
  );
  if (yt) return `https://www.youtube-nocookie.com/embed/${yt[1]}`;
  const vimeo = url.match(/vimeo\.com\/(\d+)/);
  if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}`;
  return null;
}

const isVideoFile = (url: string) => /\.(mp4|webm|m4v|mov|ogv)(\?|#|$)/i.test(url);

// Passender MIME-Typ zur Dateiendung (hilft dem Player, das Format zu erkennen).
function videoMime(url: string): string | undefined {
  const ext = url.toLowerCase().match(/\.(mp4|m4v|webm|ogv|mov)(\?|#|$)/)?.[1];
  return { mp4: "video/mp4", m4v: "video/mp4", webm: "video/webm", ogv: "video/ogg", mov: "video/quicktime" }[
    ext ?? ""
  ];
}

export function HomeVideo() {
  const { t } = useTranslation();
  const { homeVideo } = useAppConfig();
  if (!homeVideo?.enabled || !homeVideo.url) return null;

  const url = homeVideo.url;
  const embed = homeVideo.source === "mscloud" ? url : embedUrl(url);

  if (embed) {
    return (
      <section className="aspect-video w-full overflow-hidden rounded-xl border border-rid-border bg-black">
        <iframe src={embed} title={t("home.video")} allowFullScreen className="h-full w-full border-0" />
      </section>
    );
  }

  if (isVideoFile(url)) {
    return (
      <section>
        <video
          controls
          preload="metadata"
          playsInline
          className="aspect-video w-full rounded-xl border border-rid-border bg-black"
        >
          <source src={url} type={videoMime(url)} />
        </video>
      </section>
    );
  }

  // Unbekanntes Format: als externer Link statt kaputtem Player.
  return (
    <section>
      <a
        href={url}
        target="_blank"
        rel="noopener noreferrer"
        className="inline-block text-sm font-medium text-rid-accent underline underline-offset-2"
      >
        {t("home.video")}
      </a>
    </section>
  );
}
