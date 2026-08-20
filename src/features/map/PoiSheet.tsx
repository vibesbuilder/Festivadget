import { X } from "lucide-react";
import { useTranslation } from "react-i18next";
import { lt } from "@/lib/localized";
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

// Detail sheet for a POI (§12.4), as a bottom sheet above the map.
export function PoiSheet({ poi, meta, stage, onClose }: Props) {
  const { t, i18n } = useTranslation();
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
              <h2 className="font-semibold leading-tight">{lt(poi.name, i18n.language)}</h2>
              <p className="text-xs text-rid-muted">{meta.label}</p>
            </div>
          </div>
          <button onClick={onClose} aria-label={t("common.close")} className="p-1 text-rid-muted">
            <X size={20} />
          </button>
        </div>

        {lt(poi.description, i18n.language) && <p className="mt-3 text-sm text-rid-text/90">{lt(poi.description, i18n.language)}</p>}

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
