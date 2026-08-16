import { readFile } from "node:fs/promises";
import { existsSync } from "node:fs";
import { resolve } from "node:path";
import type { SourceAdapter } from "./types";

const CONTENT_DIR = resolve(process.cwd(), "content");

// Manueller Adapter (§6.2): liest content/<domain>.json (im Repo gepflegt).
// Gibt das geparste JSON zurück (Array oder Objekt – Validierung in build-data).
export const manualAdapter: SourceAdapter = {
  async fetchDomain(domain) {
    const file = resolve(CONTENT_DIR, `${domain}.json`);
    if (!existsSync(file)) {
      throw new Error(`[manual] content/${domain}.json fehlt.`);
    }
    const raw = await readFile(file, "utf-8");
    let parsed: unknown;
    try {
      parsed = JSON.parse(raw);
    } catch (e) {
      // Datei nennen, damit man bei mehreren content-Dateien sofort weiß, wo der Fehler steckt.
      throw new Error(`content/${domain}.json ist kein gültiges JSON – ${(e as Error).message}`);
    }
    // Einheitlich als Array zurückgeben; Objekt-Domänen (festival/map/tickets/weather)
    // werden vom Orchestrator wieder ausgepackt.
    return Array.isArray(parsed) ? parsed : [parsed];
  },
};
