import { useMemo } from "react";
import { useParams, Link } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { useArtists, useSlots, useStages } from "@/data/queries";
import { LoadingState, EmptyState } from "@/components/states";
import { Markdown } from "@/components/Markdown";
import { SpotifyEmbed } from "@/components/SpotifyEmbed";
import { YouTubeEmbed } from "@/components/YouTubeEmbed";
import { FavoriteButton } from "@/features/favorites/FavoriteButton";
import { formatDateTime, formatTime, parse } from "@/lib/time";

export default function Artist() {
  const { slug } = useParams<{ slug: string }>();
  const { t } = useTranslation();
  const { data: artists, isLoading } = useArtists();
  const { data: slots } = useSlots();
  const { data: stages } = useStages();

  const artist = useMemo(() => artists?.find((a) => a.slug === slug), [artists, slug]);

  const playtimes = useMemo(() => {
    if (!artist || !slots) return [];
    const stageById = new Map((stages ?? []).map((s) => [s.id, s]));
    return slots
      .filter((s) => s.artistId === artist.id)
      .sort((a, b) => parse(a.start).toMillis() - parse(b.start).toMillis())
      .map((s) => ({ slot: s, stage: stageById.get(s.stageId) }));
  }, [artist, slots, stages]);

  if (isLoading) return <LoadingState />;
  if (!artist) return <EmptyState label="Act nicht gefunden." />;

  return (
    <article className="space-y-5">
      <Link to="/lineup" className="text-sm text-rid-muted hover:text-rid-accent">
        ← {t("lineup.title")}
      </Link>

      {/* Oben: Bild links (klein), rechts Name/Genre/Land + Spielzeiten */}
      <div className="flex gap-4">
        {artist.image && (
          <div className="aspect-4/5 w-[150px] shrink-0 overflow-hidden rounded-xl bg-rid-surface-2">
            <img src={artist.image} alt={artist.name} className="h-full w-full object-cover" />
          </div>
        )}

        <div className="min-w-0 flex-1 space-y-3">
          <header className="space-y-1">
            <h1 className="text-2xl font-bold">{artist.name}</h1>
            {((artist.genres?.length ?? 0) > 0 || artist.country) && (
              <p className="text-rid-muted">
                {[(artist.genres ?? []).join(" · "), artist.country].filter(Boolean).join(" · ")}
              </p>
            )}
          </header>

          {playtimes.length > 0 && (
            <section>
              <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-rid-muted">
                {t("artist.playtimes")}
              </h2>
              <ul className="space-y-2">
                {playtimes.map(({ slot, stage }) => (
                  <li key={slot.id} className="rid-card flex items-center justify-between gap-2 p-3">
                    <div className="min-w-0">
                      <p className="truncate font-medium">{stage?.name ?? slot.stageId}</p>
                      <p className="text-sm text-rid-muted">
                        {formatDateTime(slot.start)} – {formatTime(slot.end)}
                      </p>
                    </div>
                    <FavoriteButton slotId={slot.id} />
                  </li>
                ))}
              </ul>
            </section>
          )}
        </div>
      </div>

      {(artist.spotify ?? artist.spotifyEmbedId) && (
        <SpotifyEmbed value={(artist.spotify ?? artist.spotifyEmbedId) as string} />
      )}

      {artist.youtube && <YouTubeEmbed value={artist.youtube} />}

      {artist.bio && (
        <section className="rid-card p-4">
          <Markdown>{artist.bio}</Markdown>
        </section>
      )}
    </article>
  );
}
