import { useTranslation } from "react-i18next";
import { useAppConfig } from "@/data/useAppConfig";

// Intro video on the home page (CMS tab "Branding"): full width above the
// newsfeed. Sources as in CrewCare - Microsoft cloud (OneDrive/SharePoint
// "Embed" link) as an iframe, direct video files (FTP/uploads or https link)
// as <video>, YouTube/Vimeo automatically as a player iframe.

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

// MIME type matching the file extension (helps the player detect the format).
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

  // Unknown format: render as an external link instead of a broken player.
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
