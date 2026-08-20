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

// Reads content/slots.csv (§6.5). Columns: artistSlug,stageId,dayId,start,end,note
// The join with artists (via artistSlug) happens in the orchestrator.
export async function readSlotsCsv(): Promise<CsvSlotRow[]> {
  if (!existsSync(SLOTS_CSV)) {
    throw new Error("[csv] content/slots.csv missing (slots.format === 'csv').");
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
