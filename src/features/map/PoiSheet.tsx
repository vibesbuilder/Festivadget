import { X } from "lucide-react";
import { Link } from "react-router-dom";
import type { Poi, Stage } from "@/types";
import { contrastColor, poiIcon, type PoiMeta } from "./poiMeta";
import { PoiIcon } from "./PoiIcon";

interface Props {
  poi: Poi;
  meta: PoiMeta;
  stage?: Stage;
  onClose: () => void;
}

// Detail-Sheet zu einem POI (§12.4), als Bottom-Sheet über der Karte.
export function PoiSheet({ poi, meta, stage, onClose }: Props) {
  return (
    <div className="absolute inset-x-0 bottom-0 z-[1000] p-3">
      <div className="mx-auto max-w-app rounded-2xl border border-rid-border bg-rid-surface p-4 shadow-2xl">
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-center gap-2">
            <span
              className="flex h-9 w-9 items-center justify-center rounded-full text-lg"
              style={{ background: meta.color }}
            >
              <PoiIcon
                icon={poiIcon(poi.icon, meta)}
                size={22}
                color={contrastColor(meta.color)}
                alt={meta.label}
              />
            </span>
            <div>
              <h2 className="font-semibold leading-tight">{poi.name}</h2>
              <p className="text-xs text-rid-muted">{meta.label}</p>
            </div>
          </div>
          <button onClick={onClose} aria-label="Schließen" className="p-1 text-rid-muted">
            <X size={20} />
          </button>
        </div>

        {poi.description && <p className="mt-3 text-sm text-rid-text/90">{poi.description}</p>}

        {stage && (
          <Link
            to="/timetable"
            className="mt-3 inline-block rounded-full bg-rid-accent px-3 py-1.5 text-sm font-medium text-black"
          >
            Timetable: {stage.name}
          </Link>
        )}
      </div>
    </div>
  );
}
