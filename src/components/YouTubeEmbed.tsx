// YouTube-Embed für Artist-Pages. Akzeptiert flexibel, was man in `artist.youtube`
// einträgt: Watch-Link (youtube.com/watch?v=…), Kurz-Link (youtu.be/…), Shorts-Link,
// Embed-URL, kompletten <iframe>-Embed-Code oder die nackte 11-stellige Video-ID.
export function youtubeEmbedSrc(value?: string): string | null {
  if (!value) return null;
  const m =
    value.match(/(?:youtu\.be\/|watch\?v=|embed\/|shorts\/|v=)([A-Za-z0-9_-]{11})/) ??
    value.match(/^\s*([A-Za-z0-9_-]{11})\s*$/);
  if (!m) return null;
  // nocookie-Variante = datenschutzfreundlicher.
  return `https://www.youtube-nocookie.com/embed/${m[1]}`;
}

export function YouTubeEmbed({ value }: { value: string }) {
  const src = youtubeEmbedSrc(value);
  if (!src) return null;

  return (
    <div className="aspect-video w-full overflow-hidden rounded-xl bg-black">
      <iframe
        title="YouTube"
        src={src}
        loading="lazy"
        allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowFullScreen
        className="h-full w-full border-0"
      />
    </div>
  );
}
