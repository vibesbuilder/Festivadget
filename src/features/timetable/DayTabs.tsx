import { useTranslation } from "react-i18next";
import { lt } from "@/lib/localized";
import type { FestivalDay } from "@/types";

interface Props {
  days: FestivalDay[];
  selectedId: string;
  onSelect: (id: string) => void;
}

// Day selection as tabs (§12.2).
export function DayTabs({ days, selectedId, onSelect }: Props) {
  const { i18n } = useTranslation();
  return (
    <div className="flex gap-2 overflow-x-auto pb-1">
      {days.map((day) => (
        <button
          key={day.id}
          onClick={() => onSelect(day.id)}
          className={day.id === selectedId ? "rid-chip rid-chip-active" : "rid-chip"}
        >
          {lt(day.label, i18n.language)}
        </button>
      ))}
    </div>
  );
}
