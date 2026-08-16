import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import rehypeRaw from "rehype-raw";
import rehypeSanitize, { defaultSchema } from "rehype-sanitize";
import { Link } from "react-router-dom";

// Einheitliches Markdown-Rendering für Bio/Info/News (§3, §12.9).
// Unterstützt zusätzlich eingebettetes (sanitiziertes) HTML aus dem CMS-Import:
// Überschriften, Bilder und iframes von erlaubten Hosts.

// Erlaubte iframe-Quellen (Defense-in-Depth zusätzlich zum Server-Whitelist im Import).
const IFRAME_EXACT_HOSTS = [
  "youtube.com",
  "www.youtube.com",
  "www.youtube-nocookie.com",
  "open.spotify.com",
];
// Google Maps in allen Varianten: (www.|maps.)google.<tld>. Anker gegen Spoofing.
const GOOGLE_HOST = /^(www\.|maps\.)?google\.(com|at|de|ch)$/;

function iframeAllowed(src?: string): boolean {
  if (!src) return false;
  try {
    const host = new URL(src).host;
    return IFRAME_EXACT_HOSTS.includes(host) || GOOGLE_HOST.test(host);
  } catch {
    return false;
  }
}

// Sanitize-Schema: Standard + iframe (mit Einbettungs-Attributen) + Bild-Attribute.
// `protocols` des Standardschemas beschränkt src/href weiterhin auf http(s).
const schema = {
  ...defaultSchema,
  tagNames: [...(defaultSchema.tagNames ?? []), "iframe"],
  attributes: {
    ...defaultSchema.attributes,
    iframe: [
      "src",
      "width",
      "height",
      "allow",
      "allowfullscreen",
      "frameborder",
      "loading",
      "title",
      "referrerpolicy",
    ],
    img: [...(defaultSchema.attributes?.img ?? []), "src", "alt", "loading"],
  },
};

export function Markdown({ children }: { children: string }) {
  return (
    <div className="space-y-3 leading-relaxed text-rid-text">
      <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        rehypePlugins={[rehypeRaw, [rehypeSanitize, schema]]}
        components={{
          h1: ({ children }) => (
            <h1 className="text-[2rem] font-bold leading-[2.5rem]">{children}</h1>
          ),
          h2: ({ children }) => (
            <h2 className="mt-4 text-[1.75rem] font-semibold leading-[2.25rem] text-rid-text">
              {children}
            </h2>
          ),
          h3: ({ children }) => (
            <h3 className="mt-3 text-[1.5rem] font-semibold">{children}</h3>
          ),
          h4: ({ children }) => (
            <h4 className="mt-2 text-[1.25rem] font-semibold text-rid-text">{children}</h4>
          ),
          h5: ({ children }) => (
            <h5 className="text-[1.125rem] font-semibold text-rid-text">{children}</h5>
          ),
          h6: ({ children }) => <h6 className="font-semibold text-rid-muted">{children}</h6>,
          p: ({ children }) => <p className="text-rid-text/90">{children}</p>,
          ul: ({ children }) => <ul className="list-disc space-y-1 pl-5">{children}</ul>,
          ol: ({ children }) => <ol className="list-decimal space-y-1 pl-5">{children}</ol>,
          a: ({ children, href }) =>
            href && href.startsWith("/") ? (
              // Interner Link → in-App navigieren (kein neuer Tab).
              <Link to={href} className="text-rid-accent underline underline-offset-2">
                {children}
              </Link>
            ) : (
              <a
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                className="text-rid-accent underline underline-offset-2"
              >
                {children}
              </a>
            ),
          strong: ({ children }) => <strong className="font-semibold">{children}</strong>,
          img: ({ node: _node, ...props }) => (
            <img {...props} loading="lazy" className="h-auto max-w-full rounded-xl" />
          ),
          iframe: ({ node: _node, ...props }) =>
            iframeAllowed(props.src as string | undefined) ? (
              <iframe
                {...props}
                title={(props.title as string) || "Eingebetteter Inhalt"}
                className="w-full rounded-xl border-0"
              />
            ) : null,
        }}
      >
        {children}
      </ReactMarkdown>
    </div>
  );
}
