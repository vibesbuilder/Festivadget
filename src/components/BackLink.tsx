import { Link } from "react-router-dom";
import { ChevronLeft } from "lucide-react";

// Uniform back link (label = target section).
export function BackLink({ to, label }: { to: string; label: string }) {
  return (
    <Link
      to={to}
      className="-ml-1 inline-flex items-center gap-0.5 text-sm text-rid-muted hover:text-rid-accent"
    >
      <ChevronLeft size={16} />
      {label}
    </Link>
  );
}
