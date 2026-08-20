import { useEffect, useRef } from "react";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import type { MapConfig, Poi, PoiCategory } from "@/types";
import { contrastColor, poiIcon, resolvePoiMeta } from "./poiMeta";
import { poiMarkerHtml } from "./poiIcons";

interface Props {
  config: MapConfig;
  pois: Poi[];
  categories: Map<string, PoiCategory>;
  onSelect: (poi: Poi) => void;
}

// Interactive offline map using Leaflet L.CRS.Simple + ImageOverlay (§12.4).
// POI coordinates (x,y) are pixels in the image (origin top-left); conversion
// to Leaflet LatLng = [height - y, x].
export function FestivalMap({ config, pois, categories, onSelect }: Props) {
  const containerRef = useRef<HTMLDivElement>(null);
  const mapRef = useRef<L.Map | null>(null);
  const markersRef = useRef<L.LayerGroup | null>(null);
  const onSelectRef = useRef(onSelect);
  onSelectRef.current = onSelect;

  // Initialize the map once.
  useEffect(() => {
    if (!containerRef.current || mapRef.current) return;

    const bounds: L.LatLngBoundsExpression = [
      [0, 0],
      [config.height, config.width],
    ];

    const map = L.map(containerRef.current, {
      crs: L.CRS.Simple,
      minZoom: config.minZoom,
      maxZoom: config.maxZoom,
      zoomControl: true,
      attributionControl: false,
    });

    L.imageOverlay(config.image, bounds).addTo(map);
    // Initial zoom: explicit via startZoom (decoupled from minZoom = maximum zoom-out),
    // otherwise fit the image. This allows zooming out further without the map
    // already starting zoomed out.
    if (typeof config.startZoom === "number") {
      map.setView([config.height / 2, config.width / 2], config.startZoom);
    } else {
      map.fitBounds(bounds);
    }
    map.setMaxBounds(bounds);

    markersRef.current = L.layerGroup().addTo(map);
    mapRef.current = map;

    // The container height is dynamic (dvh) - update Leaflet on size changes,
    // otherwise tiles/bounds stick to the old size.
    const ro = new ResizeObserver(() => map.invalidateSize());
    ro.observe(containerRef.current);

    return () => {
      ro.disconnect();
      map.remove();
      mapRef.current = null;
      markersRef.current = null;
    };
  }, [config]);

  // Rebuild markers when the (filtered) POIs change.
  useEffect(() => {
    const group = markersRef.current;
    if (!group) return;
    group.clearLayers();

    for (const poi of pois) {
      const meta = resolvePoiMeta(poi.type, categories);
      const icon = L.divIcon({
        className: "",
        html: `<div style="
          display:flex;align-items:center;justify-content:center;
          width:28px;height:28px;border-radius:50%;
          background:${meta.color};border:2px solid #121212;
          font-size:14px;box-shadow:0 1px 4px rgba(0,0,0,.5);
        ">${poiMarkerHtml(poiIcon(poi.icon, meta), 18, contrastColor(meta.color))}</div>`,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
      });

      L.marker([config.height - poi.y, poi.x], { icon })
        .addTo(group)
        .on("click", () => onSelectRef.current(poi));
    }
  }, [pois, config, categories]);

  return <div ref={containerRef} className="h-full w-full rounded-xl" />;
}
