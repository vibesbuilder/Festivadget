import { readFile } from "node:fs/promises";
import { existsSync } from "node:fs";
import { resolve } from "node:path";
import type { SourceAdapter } from "./types";

const CONTENT_DIR = resolve(process.cwd(), "content");

// Manual adapter (§6.2): reads content/<domain>.json (maintained in the repo).
// Returns the parsed JSON (array or object - validation in build-data).
export const manualAdapter: SourceAdapter = {
  async fetchDomain(domain) {
    const file = resolve(CONTENT_DIR, `${domain}.json`);
    if (!existsSync(file)) {
      throw new Error(`[manual] content/${domain}.json missing.`);
    }
    const raw = await readFile(file, "utf-8");
    let parsed: unknown;
    try {
      parsed = JSON.parse(raw);
    } catch (e) {
      // Name the file so with multiple content files it is immediately clear where the error is.
      throw new Error(`content/${domain}.json is not valid JSON - ${(e as Error).message}`);
    }
    // Return uniformly as an array; object domains (festival/map/tickets/weather)
    // are unwrapped again by the orchestrator.
    return Array.isArray(parsed) ? parsed : [parsed];
  },
};
