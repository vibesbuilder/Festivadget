import type { Artist, Slot, Stage } from "@/types";
import { parse } from "@/lib/time";
import { EmptyState } from "@/components/states";
import { SlotCard } from "./SlotCard";

interface Props {
  slots: Slot[];
  artistById: Map<string, Artist>;
  stageById: Map<string, Stage>;
  clashes: Set<string>;
}

// Listen-Ansicht: chronologisch je Tag (§12.2).
export function TimetableList({ slots, artistById, stageById, clashes }: Props) {
  const sorted = slots
    .slice()
    .sort((a, b) => parse(a.start).toMillis() - parse(b.start).toMillis());

  if (sorted.length === 0) return <EmptyState label="Keine Slots für diese Auswahl." />;

  return (
    <ul className="space-y-2">
      {sorted.map((slot) => (
        <li key={slot.id}>
          <SlotCard
            slot={slot}
            artist={artistById.get(slot.artistId)}
            stage={stageById.get(slot.stageId)}
            isClash={clashes.has(slot.id)}
          />
        </li>
      ))}
    </ul>
  );
}
