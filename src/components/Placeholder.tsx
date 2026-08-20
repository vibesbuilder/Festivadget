import { Construction } from "lucide-react";

// Placeholder for features of upcoming phases (roadmap §17).
export function Placeholder({ title, phase }: { title: string; phase?: string }) {
  return (
    <section>
      <h1 className="mb-4 text-2xl font-bold">{title}</h1>
      <div className="rid-card flex flex-col items-center gap-3 p-8 text-center text-rid-muted">
        <Construction className="text-rid-accent" size={28} />
        <p>Dieser Bereich wird in einer kommenden Phase umgesetzt.</p>
        {phase && <p className="text-xs uppercase tracking-wide">{phase}</p>}
      </div>
    </section>
  );
}
