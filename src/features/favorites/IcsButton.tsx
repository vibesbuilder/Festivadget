import { CalendarPlus } from "lucide-react";
import type { Artist, Slot, Stage } from "@/types";
import { buildIcs, downloadIcs, slotToVEvent } from "@/lib/ics";

interface Entry {
  slot: Slot;
  artist: Artist;
  stage?: Stage;
}

interface Props {
  entries: Entry[];
  label: string;
  filename: string;
  variant?: "primary" | "ghost";
}

// Lädt einen oder mehrere Termine als .ics mit 15-Min-Vorlauf (§11, §12.3).
export function IcsButton({ entries, label, filename, variant = "ghost" }: Props) {
  const onClick = () => {
    if (entries.length === 0) return;
    const ics = buildIcs(entries.map((e) => slotToVEvent(e)));
    downloadIcs(filename, ics);
  };

  const cls =
    variant === "primary"
      ? "bg-rid-accent text-black"
      : "bg-rid-surface-2 text-rid-text hover:bg-rid-surface";

  return (
    <button
      onClick={onClick}
      className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium ${cls}`}
    >
      <CalendarPlus size={16} />
      {label}
    </button>
  );
}
