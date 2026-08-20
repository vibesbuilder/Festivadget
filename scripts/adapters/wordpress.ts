import type { SourceAdapter } from "./types";
import { htmlToMarkdown, slugify } from "../lib/normalize";

// WordPress adapter (§6.4): WP REST API.
// GET {baseUrl}/wp-json/wp/v2/{postType}?categories={id}
// Auth: application password (basic auth via WP_USER/WP_APP_PW).
//
// Best-effort generic mapping like the Joomla adapter; fine mapping in phase 1.
export const wordpressAdapter: SourceAdapter = {
  async fetchDomain(_domain, binding, cfg) {
    const conn = cfg.wordpress;
    if (!conn) throw new Error("[wp] Keine wordpress-Verbindung in config.");

    const loc = binding.wordpress ?? {};
    const base = conn.baseUrl.replace(/\/$/, "");
    const postType = loc.postType ?? "posts";

    const headers: Record<string, string> = { Accept: "application/json" };
    const user = conn.userEnv ? process.env[conn.userEnv] : undefined;
    const pw = conn.appPwEnv ? process.env[conn.appPwEnv] : undefined;
    if (user && pw) {
      headers.Authorization = `Basic ${Buffer.from(`${user}:${pw}`).toString("base64")}`;
    }

    let url = `${base}/wp-json/wp/v2/${postType}?per_page=100&_embed=1`;
    if (loc.categorySlug) url += `&categories_slug=${encodeURIComponent(loc.categorySlug)}`;

    const res = await fetch(url, { headers });
    if (!res.ok) throw new Error(`[wp] HTTP ${res.status} für ${url}`);
    const posts = (await res.json()) as Array<Record<string, unknown>>;

    return posts.map((p) => {
      const title = renderField(p.title);
      const body = renderField(p.content);
      const acf = mapAcf(p.acf as Record<string, unknown> | undefined, loc.acf);
      return {
        id: String(p.id ?? slugify(title)),
        slug: typeof p.slug === "string" ? p.slug : slugify(title),
        name: title,
        title,
        body: htmlToMarkdown(body),
        image: extractFeaturedImage(p),
        ...acf,
      };
    });
  },
};

function renderField(field: unknown): string {
  if (typeof field === "string") return field;
  if (field && typeof field === "object" && "rendered" in field) {
    return String((field as { rendered: unknown }).rendered ?? "");
  }
  return "";
}

function extractFeaturedImage(post: Record<string, unknown>): string | undefined {
  const embedded = post._embedded as { ["wp:featuredmedia"]?: Array<{ source_url?: string }> };
  return embedded?.["wp:featuredmedia"]?.[0]?.source_url;
}

function mapAcf(
  acf: Record<string, unknown> | undefined,
  mapping?: Record<string, string>,
): Record<string, unknown> {
  if (!acf || !mapping) return {};
  const out: Record<string, unknown> = {};
  for (const [schemaField, acfName] of Object.entries(mapping)) {
    if (acfName in acf) out[schemaField] = acf[acfName];
  }
  return out;
}
