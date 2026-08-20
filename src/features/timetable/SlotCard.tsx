import { Link } from "react-router-dom";
import { AlertTriangle } from "lucide-react";
import type { Artist, Slot, Stage } from "@/types";
import { formatTime } from "@/lib/time";
import { FavoriteButton } from "@/features/favorites/FavoriteButton";

interface Props {
  slot: Slot;
  artist?: Artist;
  stage?: Stage;
  isClash?: boolean;
}

// List rendering of a slot (§12.2 list).
export function SlotCard({ slot, artist, stage, isClash }: Props) {
  return (
    <div
      className="rid-card flex items-center gap-3 p-3"
      style={{ borderLeft: `4px solid ${stage?.color ?? "rgb(var(--rid-border))"}` }}
    >
      <div className="w-14 shrink-0 text-sm">
        <div className="font-semibold">{formatTime(slot.start)}</div>
        <div className="text-rid-muted">{formatTime(slot.end)}</div>
      </div>
      <div className="min-w-0 flex-1">
        <Link to={`/artist/${artist?.slug ?? ""}`} className="block truncate font-medium">
          {slot.cancelled && <span className="text-rid-accent-2">[abgesagt] </span>}
          {artist?.name ?? slot.artistId}
        </Link>
        <div className="flex items-center gap-2 text-xs text-rid-muted">
          <span>{stage?.name ?? slot.stageId}</span>
          {isClash && (
            <span className="inline-flex items-center gap-1 text-rid-accent-2">
              <AlertTriangle size={12} /> Überschneidung
            </span>
          )}
        </div>
      </div>
      <FavoriteButton slotId={slot.id} />
    </div>
  );
}
