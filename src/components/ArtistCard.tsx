import { Link } from "react-router-dom";
import type { Artist } from "@/types";

// Artist-Karte mit Bild im Hochformat 4:5 (§2, §12.1).
// `showImage = false` → kompakte Karte ohne Bild (Name/Genre), z. B. für Acts
// jenseits des Bild-Limits im Line-Up (siehe LINEUP_IMAGE_LIMIT in config.ts).
export function ArtistCard({ artist, showImage = true }: { artist: Artist; showImage?: boolean }) {
  if (!showImage) {
    const genres = (artist.genres ?? []).join(" · ");
    return (
      <Link
        to={`/artist/${artist.slug}`}
        className="rid-card flex min-h-[4.5rem] flex-col justify-center gap-1 p-3 hover:border-rid-accent"
      >
        {artist.isHeadliner && (
          <span className="inline-block w-fit rounded bg-rid-accent px-1.5 py-0.5 text-[10px] font-bold uppercase text-black">
            Headliner
          </span>
        )}
        {artist.isDj && (
          <span className="inline-block w-fit rounded bg-rid-accent-2 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">
            DJ
          </span>
        )}
        <h3 className="text-base font-bold leading-tight">{artist.name}</h3>
        {genres && <p className="text-xs text-rid-muted">{genres}</p>}
      </Link>
    );
  }

  return (
    <Link
      to={`/artist/${artist.slug}`}
      className="group relative block overflow-hidden rounded-xl border border-rid-border bg-rid-surface"
    >
      <div className="aspect-4/5 w-full overflow-hidden bg-rid-surface-2">
        {artist.image ? (
          <img
            src={artist.image}
            alt={artist.name}
            loading="lazy"
            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center text-4xl text-rid-border">
            ♪
          </div>
        )}
      </div>
      <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 to-transparent p-3">
        {(artist.isHeadliner || artist.isDj) && (
          <div className="mb-1 flex flex-wrap gap-1">
            {artist.isHeadliner && (
              <span className="inline-block rounded bg-rid-accent px-1.5 py-0.5 text-[10px] font-bold uppercase text-black">
                Headliner
              </span>
            )}
            {artist.isDj && (
              <span className="inline-block rounded bg-rid-accent-2 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">
                DJ
              </span>
            )}
          </div>
        )}
        <h3 className="text-base font-bold leading-tight text-white">{artist.name}</h3>
        {(artist.genres?.length ?? 0) > 0 && (
          <p className="text-xs text-white/70">{(artist.genres ?? []).join(" · ")}</p>
        )}
      </div>
    </Link>
  );
}
