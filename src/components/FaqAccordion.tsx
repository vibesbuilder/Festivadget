import { useMemo, useState } from "react";
import { ChevronDown } from "lucide-react";
import { Markdown } from "./Markdown";

interface QA {
  question: string;
  answer: string;
}

// Zerlegt Markdown-Body in optionalen Intro (Text vor der 1. Frage) + "## Frage"-Blöcke (§12.9).
function parseFaq(body: string): { intro: string; items: QA[] } {
  const parts = body.split(/^##\s+/m);
  // Erster Teil steht vor der ersten "## " und ist – falls vorhanden – der Intro-Text.
  const intro = (parts.shift() ?? "").trim();
  const items = parts
    .filter((p) => p.trim())
    .map((part) => {
      const [first, ...rest] = part.split("\n");
      return { question: first.trim(), answer: rest.join("\n").trim() };
    });
  return { intro, items };
}

export function FaqAccordion({ body }: { body: string }) {
  const { intro, items } = useMemo(() => parseFaq(body), [body]);
  const [open, setOpen] = useState<number | null>(0);

  // Keine "## "-Fragen erkannt → wie bisher als reines Markdown anzeigen.
  if (items.length === 0) return <Markdown>{body}</Markdown>;

  return (
    <div className="space-y-4">
      {intro && (
        <div className="rid-card p-4">
          <Markdown>{intro}</Markdown>
        </div>
      )}
      <ul className="space-y-2">
        {items.map((item, i) => {
          const isOpen = open === i;
          return (
            <li key={i} className="rid-card overflow-hidden">
              <button
                onClick={() => setOpen(isOpen ? null : i)}
                aria-expanded={isOpen}
                className="flex w-full items-center justify-between gap-3 p-4 text-left font-medium"
              >
                <span>{item.question}</span>
                <ChevronDown
                  size={18}
                  className={`shrink-0 text-rid-muted transition-transform ${isOpen ? "rotate-180" : ""}`}
                />
              </button>
              {isOpen && (
                <div className="border-t border-rid-border p-4 pt-3 text-sm">
                  <Markdown>{item.answer}</Markdown>
                </div>
              )}
            </li>
          );
        })}
      </ul>
    </div>
  );
}
