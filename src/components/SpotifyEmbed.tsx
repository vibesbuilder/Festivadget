// Spotify-Embed für Artist-Pages (§12.1).
// Akzeptiert flexibel, was man in `artist.spotify` einträgt:
//  - Share-Link        https://open.spotify.com/artist/XXXX?si=...
//  - intl-Link         https://open.spotify.com/intl-de/track/XXXX
//  - Embed-URL         https://open.spotify.com/embed/album/XXXX
//  - kompletten Embed-Code  <iframe src="https://open.spotify.com/embed/...">
//  - oder kurz         artist/XXXX
// Daraus wird die korrekte Embed-URL gebaut. `theme=0` erzwingt – wie auf der
// Webseite – den einheitlichen Standard-Style (ohne diesen Parameter färbt
// Spotify das Embed individuell nach Artist ein).
export function spotifyEmbedSrc(value?: string): string | null {
  if (!value) return null;
  const m = value.match(/(track|artist|album|playlist|episode|show)\/([A-Za-z0-9]+)/i);
  if (!m) return null;
  return `https://open.spotify.com/embed/${m[1].toLowerCase()}/${m[2]}?utm_source=generator&theme=0`;
}

export function SpotifyEmbed({ value }: { value: string }) {
  const src = spotifyEmbedSrc(value);
  if (!src) return null;

  return (
    <iframe
      title="Spotify"
      src={src}
      width="100%"
      height="352"
      loading="lazy"
      allow="encrypted-media"
      className="rounded-xl border-0"
    />
  );
}
