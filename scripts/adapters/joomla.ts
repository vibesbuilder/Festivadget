import type { SourceAdapter } from "./types";
import { htmlToMarkdown, slugify } from "../lib/normalize";

// Joomla-Adapter (§6.3): Joomla Web-Services REST API.
// GET {baseUrl}/api/index.php/v1/content/articles?filter[category]={id}
// Header: Authorization: Bearer {JOOMLA_API_TOKEN}
//
// Hinweis: Das hier ist ein Best-Effort-Generic-Mapping (id/slug/name/body +
// Custom Fields). Die domänenspezifische Feinabbildung (Artist vs. News vs. Info)
// wird in Phase 1 verfeinert. Ohne erreichbaren Joomla-Endpunkt ungenutzt.
export const joomlaAdapter: SourceAdapter = {
  async fetchDomain(domain, binding, cfg) {
    const conn = cfg.joomla;
    if (!conn) throw new Error("[joomla] Keine joomla-Verbindung in config.");
    const token = process.env[conn.tokenEnv];
    if (!token) throw new Error(`[joomla] ENV ${conn.tokenEnv} nicht gesetzt.`);

    const loc = binding.joomla ?? {};
    const base = conn.baseUrl.replace(/\/$/, "");
    const headers = {
      Authorization: `Bearer ${token}`,
      Accept: "application/vnd.api+json",
    };

    // Artikel-Liste je Kategorie oder explizite IDs.
    const urls: string[] = [];
    if (loc.ids?.length) {
      urls.push(...loc.ids.map((id) => `${base}/api/index.php/v1/content/articles/${id}`));
    } else if (loc.categoryId != null) {
      urls.push(
        `${base}/api/index.php/v1/content/articles?filter[category]=${loc.categoryId}`,
      );
    } else {
      throw new Error(`[joomla] ${domain}: weder categoryId noch ids konfiguriert.`);
    }

    const records: unknown[] = [];
    for (const url of urls) {
      const res = await fetch(url, { headers });
      if (!res.ok) throw new Error(`[joomla] HTTP ${res.status} für ${url}`);
      const json = (await res.json()) as { data: unknown };
      const data = Array.isArray(json.data) ? json.data : [json.data];
      for (const entry of data as Array<{ id?: number; attributes?: Record<string, unknown> }>) {
        const a = entry.attributes ?? {};
        const title = String(a.title ?? "");
        const body = typeof a.introtext === "string" ? a.introtext : String(a.text ?? "");
        const customFields = mapCustomFields(a, loc.customFields);
        records.push({
          id: String(entry.id ?? a.id ?? slugify(title)),
          slug: typeof a.alias === "string" && a.alias ? a.alias : slugify(title),
          name: title,
          title,
          body: htmlToMarkdown(body),
          ...customFields,
        });
      }
    }
    return records;
  },
};

function mapCustomFields(
  attrs: Record<string, unknown>,
  mapping?: Record<string, string>,
): Record<string, unknown> {
  if (!mapping) return {};
  // Joomla liefert Custom Fields oft unter attrs.com_fields oder jcfields.
  const fields = (attrs.jcfields ?? attrs.com_fields) as
    | Array<{ name: string; rawvalue?: unknown; value?: unknown }>
    | undefined;
  const byName = new Map<string, unknown>();
  for (const f of fields ?? []) byName.set(f.name, f.rawvalue ?? f.value);
  const out: Record<string, unknown> = {};
  for (const [schemaField, joomlaName] of Object.entries(mapping)) {
    if (byName.has(joomlaName)) out[schemaField] = byName.get(joomlaName);
  }
  return out;
}
