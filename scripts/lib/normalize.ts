// Helpers for build-time normalization (§6.6: sanitize HTML).

export function slugify(input: string): string {
  return input
    .toLowerCase()
    .replace(/[äöü]/g, (m) => ({ ä: "ae", ö: "oe", ü: "ue" })[m] ?? m)
    .replace(/ß/g, "ss")
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "") // kombinierende Diakritika entfernen
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

// Very light HTML -> Markdown/text conversion without an extra package.
// Removes dangerous elements (script/style/iframe) and converts the most
// common block/inline tags. For complex CMS HTML consider replacing with a
// more robust solution (e.g. turndown + sanitize-html) in phase 1.
export function htmlToMarkdown(html: string): string {
  if (!html) return "";
  let out = html;

  // Remove dangerous/irrelevant blocks including their content.
  out = out.replace(/<(script|style|iframe|noscript)[\s\S]*?<\/\1>/gi, "");

  // Headings.
  out = out.replace(/<h1[^>]*>([\s\S]*?)<\/h1>/gi, "\n# $1\n");
  out = out.replace(/<h2[^>]*>([\s\S]*?)<\/h2>/gi, "\n## $1\n");
  out = out.replace(/<h3[^>]*>([\s\S]*?)<\/h3>/gi, "\n### $1\n");

  // Links and emphasis.
  out = out.replace(/<a [^>]*href="([^"]*)"[^>]*>([\s\S]*?)<\/a>/gi, "[$2]($1)");
  out = out.replace(/<(strong|b)>([\s\S]*?)<\/\1>/gi, "**$2**");
  out = out.replace(/<(em|i)>([\s\S]*?)<\/\1>/gi, "*$2*");

  // Lists.
  out = out.replace(/<li[^>]*>([\s\S]*?)<\/li>/gi, "- $1\n");

  // Paragraphs and line breaks.
  out = out.replace(/<\/p>/gi, "\n\n").replace(/<br\s*\/?>/gi, "\n");

  // Remove remaining tags.
  out = out.replace(/<[^>]+>/g, "");

  // HTML entities (most common).
  out = out
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'");

  // Collapse multiple blank lines.
  return out.replace(/\n{3,}/g, "\n\n").trim();
}
