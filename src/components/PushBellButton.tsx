import { useEffect, useRef, useState } from "react";
import { Bell, Loader2 } from "lucide-react";
import { usePushActive, useRefreshPush } from "@/lib/usePush";
import { unsubscribePush } from "@/lib/push";
import { PushCategoryPicker } from "./PushCategoryPicker";

// Glocke im Header (links neben der Suche): nur sichtbar, wenn Push aktiv ist.
// Öffnet ein Popover zur Kategorie-Wahl + „Benachrichtigungen ausschalten".
export function PushBellButton() {
  const { supported, active } = usePushActive();
  const refresh = useRefreshPush();
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onDoc = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, [open]);

  if (!supported || !active) return null;

  const turnOff = async () => {
    setBusy(true);
    try {
      await unsubscribePush();
      refresh();
      setOpen(false);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen((o) => !o)}
        aria-label="Benachrichtigungen"
        aria-expanded={open}
        className="shrink-0 rounded-full p-2 text-rid-accent hover:text-rid-accent"
      >
        <Bell size={20} />
      </button>
      {open && (
        <div className="absolute right-0 z-50 mt-2 w-64 rounded-xl border border-rid-border bg-rid-surface p-3 shadow-2xl">
          <p className="mb-2 text-xs font-medium text-rid-muted">Push-Benachrichtigungen</p>
          <PushCategoryPicker />
          <button
            onClick={turnOff}
            disabled={busy}
            className="mt-3 flex w-full items-center justify-center gap-2 rounded-full bg-rid-surface-2 px-3 py-1.5 text-sm text-rid-text disabled:opacity-50"
          >
            {busy && <Loader2 size={14} className="animate-spin" />}
            Benachrichtigungen ausschalten
          </button>
        </div>
      )}
    </div>
  );
}
