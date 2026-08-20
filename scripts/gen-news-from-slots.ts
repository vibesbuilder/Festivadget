import { readFile, writeFile } from "node:fs/promises";
import { resolve } from "node:path";
import { DateTime } from "luxon";
import { readSlotsCsv } from "./adapters/csv";
import { slugify } from "./lib/normalize";

// Generates one line-up news entry per slot in content/slots.csv, appearing 15
// minutes before the slot starts (publishAt = start - 15 min), merges it with
// the editorial news and sorts everything chronologically (oldest first).
//
// Generated entries have an id prefixed "slot-" and are replaced on re-runs;
// editorial news stays untouched.

const CONTENT = resolve(process.cwd(), "content");

type LocalizedText = string | Partial<Record<"de" | "en" | "fr" | "es", string>>;

interface NewsItem {
  id: string;
  title: LocalizedText;
  body: LocalizedText;
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

  // Generated slot news (unique per id; duplicate CSV rows are merged).
  const generated = new Map<string, NewsItem>();
  for (const r of rows) {
    const start = DateTime.fromISO(r.start, { setZone: true });
    const end = DateTime.fromISO(r.end, { setZone: true });
    const publishAt = start.minus({ minutes: 15 }).toISO({ suppressMilliseconds: true })!;
    const artist = nameBySlug.get(r.artistSlug) ?? r.artistSlug;
    const stage = nameByStage.get(r.stageId) ?? r.stageId;
    const id = `slot-${r.dayId}-${r.stageId}-${slugify(r.artistSlug)}`;
    const range = `${start.toFormat("HH:mm")} - ${end.toFormat("HH:mm")}`;
    generated.set(id, {
      id,
      title: {
        de: `Gleich: ${artist}`,
        en: `Up next: ${artist}`,
        fr: `Bientôt : ${artist}`,
        es: `Pronto: ${artist}`,
      },
      body: {
        de: `${range} Uhr - ${stage}`,
        en: `${range} - ${stage}`,
        fr: `${range} - ${stage}`,
        es: `${range} - ${stage}`,
      },
      category: "lineup",
      publishAt,
    });
  }

  // Keep editorial news (everything that is NOT generated).
  const editorial = existing.filter((n) => !n.id.startsWith("slot-"));

  const merged = [...editorial, ...generated.values()].sort(
    (a, b) =>
      DateTime.fromISO(a.publishAt, { setZone: true }).toMillis() -
      DateTime.fromISO(b.publishAt, { setZone: true }).toMillis(),
  );

  await writeFile(resolve(CONTENT, "news.json"), JSON.stringify(merged, null, 2) + "\n", "utf-8");
  console.log(
    `✓ news.json: ${editorial.length} editorial + ${generated.size} slot news = ${merged.length} (sorted chronologically).`,
  );
}

main().catch((err) => {
  console.error("✗ gen-news failed:", err instanceof Error ? err.message : err);
  process.exit(1);
});
