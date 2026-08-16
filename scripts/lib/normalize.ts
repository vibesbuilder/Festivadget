// Hilfsfunktionen für die Build-Time-Normalisierung (§6.6: HTML sanitizen).

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

// Sehr leichte HTML→Markdown/Text-Konvertierung ohne Zusatzpaket.
// Entfernt gefährliche Elemente (script/style/iframe) und konvertiert die
// häufigsten Block-/Inline-Tags. Für komplexes CMS-HTML in Phase 1 ggf. durch
// eine robustere Lösung (z. B. turndown + sanitize-html) ersetzen.
export function htmlToMarkdown(html: string): string {
  if (!html) return "";
  let out = html;

  // Gefährliche/irrelevante Blöcke samt Inhalt entfernen.
  out = out.replace(/<(script|style|iframe|noscript)[\s\S]*?<\/\1>/gi, "");

  // Überschriften.
  out = out.replace(/<h1[^>]*>([\s\S]*?)<\/h1>/gi, "\n# $1\n");
  out = out.replace(/<h2[^>]*>([\s\S]*?)<\/h2>/gi, "\n## $1\n");
  out = out.replace(/<h3[^>]*>([\s\S]*?)<\/h3>/gi, "\n### $1\n");

  // Links und Betonung.
  out = out.replace(/<a [^>]*href="([^"]*)"[^>]*>([\s\S]*?)<\/a>/gi, "[$2]($1)");
  out = out.replace(/<(strong|b)>([\s\S]*?)<\/\1>/gi, "**$2**");
  out = out.replace(/<(em|i)>([\s\S]*?)<\/\1>/gi, "*$2*");

  // Listen.
  out = out.replace(/<li[^>]*>([\s\S]*?)<\/li>/gi, "- $1\n");

  // Absätze und Umbrüche.
  out = out.replace(/<\/p>/gi, "\n\n").replace(/<br\s*\/?>/gi, "\n");

  // Restliche Tags entfernen.
  out = out.replace(/<[^>]+>/g, "");

  // HTML-Entities (häufigste).
  out = out
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'");

  // Mehrfache Leerzeilen reduzieren.
  return out.replace(/\n{3,}/g, "\n\n").trim();
}
