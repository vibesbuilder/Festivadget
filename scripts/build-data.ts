import { readFile, writeFile, readdir } from "node:fs/promises";
import { createHash } from "node:crypto";
import { resolve } from "node:path";
import { DateTime } from "luxon";

// Validates the generated public/data/*.json against the schema (§7, lightweight)
// and produces version.json with content hashes (§5.2).

const DATA_DIR = resolve(process.cwd(), "public", "data");

type Kind = "object" | "array";

// Expected file kind + required fields per record (light validation).
const SCHEMA: Record<string, { kind: Kind; required: string[] }> = {
  festival: { kind: "object", required: ["name", "timezone", "days"] },
  stages: { kind: "array", required: ["id", "name", "shortName", "color", "order"] },
  artists: { kind: "array", required: ["id", "slug", "name"] },
  slots: { kind: "array", required: ["id", "artistId", "stageId", "dayId", "start", "end"] },
  pois: { kind: "array", required: ["id", "type", "name", "x", "y"] },
  "poi-categories": { kind: "array", required: ["id", "label", "color", "icon"] },
  map: { kind: "object", required: ["image", "width", "height", "minZoom", "maxZoom"] },
  news: { kind: "array", required: ["id", "title", "body", "category", "publishAt"] },
  sponsors: { kind: "array", required: ["id", "name", "logo", "tier", "order"] },
  info: { kind: "array", required: ["id", "title", "order", "body"] },
  tickets: { kind: "object", required: ["providers"] },
  weather: { kind: "object", required: ["generatedAt", "source", "days"] },
};

function shortHash(content: string): string {
  return createHash("sha1").update(content).digest("hex").slice(0, 8);
}

function validate(domain: string, parsed: unknown): void {
  const spec = SCHEMA[domain];
  if (!spec) return; // unknown file - hash only

  if (spec.kind === "array") {
    if (!Array.isArray(parsed)) throw new Error(`${domain}.json must be an array.`);
    parsed.forEach((item, i) => {
      for (const field of spec.required) {
        if (!(item && typeof item === "object" && field in item)) {
          throw new Error(`${domain}.json[${i}]: required field "${field}" missing.`);
        }
      }
    });
  } else {
    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
      throw new Error(`${domain}.json must be an object.`);
    }
    for (const field of spec.required) {
      if (!(field in (parsed as Record<string, unknown>))) {
        throw new Error(`${domain}.json: required field "${field}" missing.`);
      }
    }
  }
}

async function main(): Promise<void> {
  console.log("Festivadget · validation + version.json (§5.2)\n");

  const files = (await readdir(DATA_DIR)).filter(
    (f) => f.endsWith(".json") && f !== "version.json",
  );

  const datasets: Record<string, string> = {};

  for (const file of files.sort()) {
    const domain = file.replace(/\.json$/, "");
    const content = await readFile(resolve(DATA_DIR, file), "utf-8");
    let parsed: unknown;
    try {
      parsed = JSON.parse(content);
    } catch {
      throw new Error(`${file}: invalid JSON.`);
    }
    validate(domain, parsed);
    datasets[domain] = shortHash(content);
    console.log(`  ✓ ${file} → ${datasets[domain]}`);
  }

  const manifest = {
    generatedAt: DateTime.now().setZone("Europe/Vienna").toISO(),
    datasets,
  };

  await writeFile(
    resolve(DATA_DIR, "version.json"),
    JSON.stringify(manifest, null, 2) + "\n",
    "utf-8",
  );
  console.log("\n  ✓ version.json written.");
  console.log("\nValidation successful.");
}

main().catch((err) => {
  console.error("\n✗ build:data failed:", err instanceof Error ? err.message : err);
  process.exit(1);
});
