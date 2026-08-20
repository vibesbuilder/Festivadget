import { readFile, writeFile } from "node:fs/promises";
import { resolve } from "node:path";
import sharp from "sharp";

// Generates the final PWA PNG icons from the SVG sources (§13, phase 4).
// Usage: npm run gen-icons

const ICON_DIR = resolve(process.cwd(), "public", "icons");

async function render(svgFile: string, size: number, outFile: string): Promise<void> {
  const svg = await readFile(resolve(ICON_DIR, svgFile));
  const png = await sharp(svg, { density: 384 }).resize(size, size).png().toBuffer();
  await writeFile(resolve(ICON_DIR, outFile), png);
  console.log(`  ✓ ${outFile} (${size}×${size})`);
}

async function main(): Promise<void> {
  console.log("Festivadget · PWA-Icons erzeugen\n");
  await render("icon.svg", 192, "icon-192.png");
  await render("icon.svg", 512, "icon-512.png");
  await render("icon.svg", 180, "apple-touch-icon.png");
  await render("icon-maskable.svg", 512, "icon-maskable-512.png");
  console.log("\nFertig.");
}

main().catch((err) => {
  console.error("\n✗ gen-icons fehlgeschlagen:", err instanceof Error ? err.message : err);
  process.exit(1);
});
