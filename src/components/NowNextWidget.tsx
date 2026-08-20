import { useMemo } from "react";
import { Link } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { useArtists, useSlots, useStages } from "@/data/queries";
import { now, parse, formatTime, isLive } from "@/lib/time";

// Now / Up Next (§12.6): per stage "playing now" + "up next".
export function NowNextWidget() {
  const { t } = useTranslation();
  const { data: slots } = useSlots();
  const { data: stages } = useStages();
  const { data: artists } = useArtists();

  const perStage = useMemo(() => {
    if (!slots || !stages || !artists) return [];
    const at = now();
    const artistById = new Map(artists.map((a) => [a.id, a]));

    return stages
      .slice()
      .sort((a, b) => a.order - b.order)
      .map((stage) => {
        const stageSlots = slots
          .filter((s) => s.stageId === stage.id && !s.cancelled)
          .sort((a, b) => parse(a.start).toMillis() - parse(b.start).toMillis());
        const live = stageSlots.find((s) => isLive(s, at));
        const next = stageSlots.find((s) => parse(s.start) > at);
        return { stage, live, next, artistById };
      })
      .filter((row) => row.live || row.next);
  }, [slots, stages, artists]);

  if (perStage.length === 0) return null;

  return (
    <div className="space-y-3">
      {perStage.map(({ stage, live, next, artistById }) => (
        <div key={stage.id} className="rid-card p-4">
          <div className="mb-1.5 flex items-center gap-2">
            <span className="h-3 w-3 rounded-full" style={{ backgroundColor: stage.color }} />
            <span className="text-base font-semibold">{stage.name}</span>
          </div>
          {live && (
            <Link
              to={`/artist/${artistById.get(live.artistId)?.slug ?? ""}`}
              className="block text-lg"
            >
              <span className="mr-2 rounded bg-rid-accent px-1.5 py-0.5 text-xs font-bold uppercase text-black">
                {t("home.now")}
              </span>
              <span className="font-semibold">{artistById.get(live.artistId)?.name}</span>
              <span className="text-rid-muted"> · bis {formatTime(live.end)}</span>
            </Link>
          )}
          {next && (
            <Link
              to={`/artist/${artistById.get(next.artistId)?.slug ?? ""}`}
              className="mt-1 block text-base text-rid-muted"
            >
              {t("home.upNext")}: {artistById.get(next.artistId)?.name} · {formatTime(next.start)}
            </Link>
          )}
        </div>
      ))}
    </div>
  );
}
