import { mkdir, writeFile } from "node:fs/promises";
import { existsSync } from "node:fs";
import { resolve } from "node:path";
import config, {
  type SourceBinding,
  type ContentSourcesConfig,
} from "../content-sources.config";

// Load .env (built into Node, from 20.12+/21.7+) so CMS tokens from .env end
// up in process.env (§6.6). Irrelevant for a pure "manual" build without .env.
if (existsSync(resolve(process.cwd(), ".env"))) {
  process.loadEnvFile(resolve(process.cwd(), ".env"));
}
import { manualAdapter } from "./adapters/manual";
import { joomlaAdapter } from "./adapters/joomla";
import { wordpressAdapter } from "./adapters/wordpress";
import { readSlotsCsv } from "./adapters/csv";
import type { SourceAdapter } from "./adapters/types";
import { slugify } from "./lib/normalize";

// Orchestration (§6.2): iterates over bindings, calls the adapter per provider,
// normalizes to the schema (§7) and writes public/data/<domain>.json.

const OUT_DIR = resolve(process.cwd(), "public", "data");

// Domains emitted as a single object (not an array).
const OBJECT_DOMAINS = new Set(["festival", "map", "tickets", "weather"]);

function adapterFor(binding: SourceBinding): SourceAdapter {
  switch (binding.provider) {
    case "manual":
      return manualAdapter;
    case "joomla":
      return joomlaAdapter;
    case "wordpress":
      return wordpressAdapter;
    default:
      throw new Error(`Unbekannter provider: ${(binding as { provider: string }).provider}`);
  }
}

async function writeDomain(domain: string, data: unknown): Promise<void> {
  const file = resolve(OUT_DIR, `${domain}.json`);
  await writeFile(file, JSON.stringify(data, null, 2) + "\n", "utf-8");
  const count = Array.isArray(data) ? `${data.length} Einträge` : "Objekt";
  console.log(`  ✓ ${domain}.json (${count})`);
}

/** Slots from CSV: join via artistSlug with the (previously imported) artists. */
async function buildSlotsFromCsv(artists: Array<{ id: string; slug: string }>): Promise<unknown[]> {
  const rows = await readSlotsCsv();
  const idBySlug = new Map(artists.map((a) => [a.slug, a.id]));
  return rows.map((r, i) => {
    const artistId = idBySlug.get(r.artistSlug);
    if (!artistId) {
      throw new Error(`[slots] Kein Artist für slug "${r.artistSlug}" (Zeile ${i + 2}).`);
    }
    return {
      id: `${r.dayId}-${r.stageId}-${slugify(r.artistSlug)}`,
      artistId,
      stageId: r.stageId,
      dayId: r.dayId,
      start: r.start,
      end: r.end,
      ...(r.note ? { note: r.note } : {}),
    };
  });
}

interface InfoItem {
  id: string;
  title?: string;
  body?: string;
  [k: string]: unknown;
}

/**
 * info (§6.4): the default source delivers structure + texts (content/info.json:
 * id, icon, order, hidden, fallback title/text). Per entry ID a different source
 * (joomla/wordpress) can be chosen in `info.overrides` - it then delivers only
 * title/text, the structure (id/icon/order/hidden) stays from the default
 * source. This binds every submenu entry to a source individually.
 */
async function importInfo(cfg: ContentSourcesConfig): Promise<void> {
  const infoCfg = cfg.bindings.info;
  const base = (await adapterFor(infoCfg.default).fetchDomain(
    "info",
    infoCfg.default,
    cfg,
  )) as InfoItem[];
  const overrides = infoCfg.overrides ?? {};

  const merged: InfoItem[] = [];
  for (const item of base) {
    const ov = overrides[item.id];
    if (!ov || ov.provider === infoCfg.default.provider) {
      merged.push(item);
      continue;
    }
    // Fetch the override source - only take title/text, keep the structure.
    const recs = (await adapterFor(ov).fetchDomain("info", ov, cfg)) as InfoItem[];
    const src = recs[0];
    if (!src) {
      throw new Error(`[info] Override-Quelle (${ov.provider}) für "${item.id}" lieferte keine Daten.`);
    }
    merged.push({
      ...item,
      ...(src.title ? { title: src.title } : {}),
      ...(src.body != null ? { body: src.body } : {}),
    });
  }
  await writeDomain("info", merged);
}

async function importDomain(
  domain: string,
  binding: SourceBinding,
  cfg: ContentSourcesConfig,
  ctx: { artists: Array<{ id: string; slug: string }> },
): Promise<void> {
  // Slots special case (§6.5): source controlled via slots.format.
  if (domain === "slots") {
    const slotsBinding = cfg.bindings.slots;
    if (slotsBinding.provider === "manual" && (slotsBinding.format ?? "csv") === "csv") {
      await writeDomain("slots", await buildSlotsFromCsv(ctx.artists));
      return;
    }
    // joomla-customfields / wordpress-acf: derive slots from artist records
    // (refine in phase 1) - fallback: the regular adapter.
  }

  const records = await adapterFor(binding).fetchDomain(domain, binding, cfg);

  if (OBJECT_DOMAINS.has(domain)) {
    await writeDomain(domain, records[0] ?? {});
  } else {
    await writeDomain(domain, records);
  }
}

async function main(): Promise<void> {
  console.log("Festivadget · Import aus konfigurierten Quellen (§6)\n");
  await mkdir(OUT_DIR, { recursive: true });

  // Import artists first (needed for the slots CSV join).
  const artistRecords = (await adapterFor(config.bindings.artists).fetchDomain(
    "artists",
    config.bindings.artists,
    config,
  )) as Array<{ id: string; slug: string }>;
  await writeDomain("artists", artistRecords);

  const ctx = { artists: artistRecords };

  const order: Array<[string, SourceBinding]> = [
    ["festival", config.bindings.festival],
    ["stages", config.bindings.stages],
    ["slots", config.bindings.slots],
    ["pois", config.bindings.pois],
    ["map", config.bindings.map],
    ["news", config.bindings.news],
    ["sponsors", config.bindings.sponsors],
    ["tickets", config.bindings.tickets],
    ["weather", config.bindings.weather],
  ];

  for (const [domain, binding] of order) {
    await importDomain(domain, binding, config, ctx);
  }

  // info: source per entry (default + optional overrides per ID, §6.4).
  await importInfo(config);

  console.log("\nImport abgeschlossen. Nächster Schritt: npm run build:data");
}

main().catch((err) => {
  console.error("\n✗ Import fehlgeschlagen:", err instanceof Error ? err.message : err);
  process.exit(1);
});
