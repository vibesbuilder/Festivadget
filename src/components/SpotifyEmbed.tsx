// Spotify embed for artist pages (§12.1).
// Flexibly accepts whatever is entered in `artist.spotify`:
//  - share link        https://open.spotify.com/artist/XXXX?si=...
//  - intl link         https://open.spotify.com/intl-de/track/XXXX
//  - embed URL         https://open.spotify.com/embed/album/XXXX
//  - full embed code   <iframe src="https://open.spotify.com/embed/...">
//  - or the short form artist/XXXX
// The correct embed URL is built from that. `theme=0` enforces - like on the
// website - the uniform default style (without this parameter Spotify tints
// the embed individually per artist).
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
