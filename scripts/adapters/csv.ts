import { readFile } from "node:fs/promises";
import { existsSync } from "node:fs";
import { resolve } from "node:path";
import Papa from "papaparse";

const SLOTS_CSV = resolve(process.cwd(), "content", "slots.csv");

interface CsvSlotRow {
  artistSlug: string;
  stageId: string;
  dayId: string;
  start: string;
  end: string;
  note?: string;
}

// Liest content/slots.csv (§6.5). Spalten: artistSlug,stageId,dayId,start,end,note
// Join mit Artists (über artistSlug) passiert im Orchestrator.
export async function readSlotsCsv(): Promise<CsvSlotRow[]> {
  if (!existsSync(SLOTS_CSV)) {
    throw new Error("[csv] content/slots.csv fehlt (slots.format === 'csv').");
  }
  const raw = await readFile(SLOTS_CSV, "utf-8");
  const parsed = Papa.parse<CsvSlotRow>(raw, {
    header: true,
    skipEmptyLines: true,
    transform: (v) => v.trim(),
  });
  if (parsed.errors.length) {
    throw new Error(`[csv] Parse-Fehler: ${parsed.errors[0].message}`);
  }
  return parsed.data.filter((r) => r.artistSlug);
}

export type { CsvSlotRow };
