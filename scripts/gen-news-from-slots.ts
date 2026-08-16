import { readFile, writeFile } from "node:fs/promises";
import { resolve } from "node:path";
import { DateTime } from "luxon";
import { readSlotsCsv } from "./adapters/csv";
import { slugify } from "./lib/normalize";

// Erzeugt pro Slot in content/slots.csv einen Lineup-News-Eintrag, der 15 Minuten
// vor Slot-Beginn erscheint (publishAt = start − 15 min), führt ihn mit den
// redaktionellen News zusammen und sortiert alles chronologisch (älteste oben).
//
// Generierte Einträge haben eine id mit Präfix "slot-" und werden bei erneutem
// Lauf ersetzt; redaktionelle News bleiben unangetastet.

const CONTENT = resolve(process.cwd(), "content");

interface NewsItem {
  id: string;
  title: string;
  body: string;
  category: string;
  publishAt: string;
  [k: string]: unknown;
}

async function loadJson<T>(file: string): Promise<T> {
  return JSON.parse(await readFile(resolve(CONTENT, file), "utf-8")) as T;
}

async function main(): Promise<void> {
  const rows = await readSlotsCsv();
  const artists = await loadJson<Array<{ slug: string; name: string }>>("artists.json");
  const stages = await loadJson<Array<{ id: string; name: string }>>("stages.json");
  const existing = await loadJson<NewsItem[]>("news.json");

  const nameBySlug = new Map(artists.map((a) => [a.slug, a.name]));
  const nameByStage = new Map(stages.map((s) => [s.id, s.name]));

  // Generierte Slot-News (eindeutig je id; doppelte CSV-Zeilen werden zusammengeführt).
  const generated = new Map<string, NewsItem>();
  for (const r of rows) {
    const start = DateTime.fromISO(r.start, { setZone: true });
    const end = DateTime.fromISO(r.end, { setZone: true });
    const publishAt = start.minus({ minutes: 15 }).toISO({ suppressMilliseconds: true })!;
    const artist = nameBySlug.get(r.artistSlug) ?? r.artistSlug;
    const stage = nameByStage.get(r.stageId) ?? r.stageId;
    const id = `slot-${r.dayId}-${r.stageId}-${slugify(r.artistSlug)}`;
    generated.set(id, {
      id,
      title: `Gleich: ${artist}`,
      body: `${start.toFormat("HH:mm")} - ${end.toFormat("HH:mm")} Uhr - ${stage}`,
      category: "lineup",
      publishAt,
    });
  }

  // Redaktionelle News behalten (alles, was NICHT generiert ist).
  const editorial = existing.filter((n) => !n.id.startsWith("slot-"));

  const merged = [...editorial, ...generated.values()].sort(
    (a, b) =>
      DateTime.fromISO(a.publishAt, { setZone: true }).toMillis() -
      DateTime.fromISO(b.publishAt, { setZone: true }).toMillis(),
  );

  await writeFile(resolve(CONTENT, "news.json"), JSON.stringify(merged, null, 2) + "\n", "utf-8");
  console.log(
    `✓ news.json: ${editorial.length} redaktionelle + ${generated.size} Slot-News = ${merged.length} (chronologisch sortiert).`,
  );
}

main().catch((err) => {
  console.error("✗ gen-news fehlgeschlagen:", err instanceof Error ? err.message : err);
  process.exit(1);
});
