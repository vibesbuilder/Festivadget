import type { FestivalDay } from "@/types";

interface Props {
  days: FestivalDay[];
  selectedId: string;
  onSelect: (id: string) => void;
}

// Day selection as tabs (§12.2).
export function DayTabs({ days, selectedId, onSelect }: Props) {
  return (
    <div className="flex gap-2 overflow-x-auto pb-1">
      {days.map((day) => (
        <button
          key={day.id}
          onClick={() => onSelect(day.id)}
          className={day.id === selectedId ? "rid-chip rid-chip-active" : "rid-chip"}
        >
          {day.label}
        </button>
      ))}
    </div>
  );
}
