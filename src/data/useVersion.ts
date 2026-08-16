import { useEffect, useRef } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { get as idbGet, set as idbSet } from "idb-keyval";
import type { DatasetKey, VersionManifest } from "@/types";
import { fetchJson } from "./fetchJson";

const VERSION_FILE = "version.json";
const LAST_VERSION_KEY = "festivadget:last-version";

// Polling-Intervall: 2 Minuten (§5.2).
const POLL_MS = 120_000;

/**
 * Lädt version.json near-live. Pollt nur, wenn Tab sichtbar und online ist.
 * Offline-Fallback: zuletzt bekanntes Manifest aus IndexedDB.
 */
export function useVersion() {
  return useQuery<VersionManifest>({
    queryKey: ["version"],
    queryFn: async ({ signal }) => {
      try {
        const manifest = await fetchJson<VersionManifest>(VERSION_FILE, signal);
        // IndexedDB-Fehler (Safari-Privatmodus/Verbindungsabriss) dürfen den
        // erfolgreichen Abruf nicht scheitern lassen – Cache ist nachrangig.
        await idbSet(LAST_VERSION_KEY, manifest).catch(() => undefined);
        return manifest;
      } catch (err) {
        const cached = await idbGet<VersionManifest>(LAST_VERSION_KEY).catch(() => undefined);
        if (cached) return cached;
        throw err;
      }
    },
    refetchInterval: () =>
      typeof document !== "undefined" && document.visibilityState === "visible" && navigator.onLine
        ? POLL_MS
        : false,
    staleTime: 0,
  });
}

/**
 * Vergleicht die Dataset-Hashes bei jeder Version-Aktualisierung und invalidiert
 * gezielt nur die geänderten Datensätze (§5.2). In der App-Shell einmal mounten.
 */
export function useVersionSync(): void {
  const { data } = useVersion();
  const queryClient = useQueryClient();
  const prev = useRef<VersionManifest["datasets"] | null>(null);

  useEffect(() => {
    if (!data) return;
    const current = data.datasets;
    const previous = prev.current;

    if (previous) {
      (Object.keys(current) as DatasetKey[]).forEach((key) => {
        if (current[key] !== previous[key]) {
          void queryClient.invalidateQueries({ queryKey: ["data", key] });
        }
      });
    }
    prev.current = current;
  }, [data, queryClient]);
}
