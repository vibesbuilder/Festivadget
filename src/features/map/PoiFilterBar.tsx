import { PoiIcon } from "./PoiIcon";

interface CategoryChip {
  id: string;
  label: string;
  icon: string;
}

interface Props {
  available: CategoryChip[];
  active: Set<string>;
  onToggle: (id: string) => void;
  onReset: () => void;
}

// Filterleiste: Chips je vorhandener (sichtbarer) POI-Kategorie (§12.4).
export function PoiFilterBar({ available, active, onToggle, onReset }: Props) {
  const allActive = active.size === 0;

  return (
    <div className="flex gap-2 overflow-x-auto pb-1">
      <button onClick={onReset} className={allActive ? "rid-chip rid-chip-active" : "rid-chip"}>
        Alle
      </button>
      {available.map((cat) => {
        const isActive = active.has(cat.id);
        return (
          <button
            key={cat.id}
            onClick={() => onToggle(cat.id)}
            className={isActive ? "rid-chip rid-chip-active" : "rid-chip"}
          >
            <span className="mr-1">
              <PoiIcon icon={cat.icon} alt="" />
            </span>
            {cat.label}
          </button>
        );
      })}
    </div>
  );
}
