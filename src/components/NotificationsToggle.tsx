import { useState } from "react";
import { BellOff, Loader2 } from "lucide-react";
import { subscribePush, notificationPermission } from "@/lib/push";
import { usePushActive, useRefreshPush } from "@/lib/usePush";
import { IOS_POPUP_EVENT } from "./IosInstallPopup";

function isIosNotStandalone(): boolean {
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const standalone =
    window.matchMedia("(display-mode: standalone)").matches ||
    (navigator as unknown as { standalone?: boolean }).standalone === true;
  return ios && !standalone;
}

// Aufruf zum Aktivieren von Web-Push (§13). Blendet sich aus, wenn Push nicht
// verfügbar ist ODER bereits aktiv – dann übernimmt die Glocke im Header
// (Kategorie-Wahl + Ausschalten).
export function NotificationsToggle() {
  const { supported, active } = usePushActive();
  const refresh = useRefreshPush();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!supported || active) return null;

  const denied = notificationPermission() === "denied";

  const activate = async () => {
    setBusy(true);
    setError(null);
    try {
      await subscribePush();
      refresh(); // Glocke im Header erscheint, dieser Schalter verschwindet
    } catch (e) {
      setError(e instanceof Error ? e.message : "Fehler");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="rounded-xl border border-rid-border bg-rid-surface p-4">
      <div className="flex items-center gap-3">
        <BellOff size={20} className="text-rid-muted" />
        <div className="flex-1">
          <p className="font-medium">Benachrichtigungen</p>
          <p className="text-xs text-rid-muted">
            Konzertstarts & wichtige Infos auf den Sperrbildschirm.{" "}
            {isIosNotStandalone() && (
              <button
                type="button"
                onClick={() => window.dispatchEvent(new Event(IOS_POPUP_EVENT))}
                className="font-medium text-rid-accent underline underline-offset-2"
              >
                Mehr Infos
              </button>
            )}
          </p>
        </div>
        <button
          onClick={activate}
          disabled={busy || denied}
          className="shrink-0 rounded-full bg-rid-accent px-3 py-1.5 text-sm font-medium text-black disabled:opacity-50"
        >
          {busy ? <Loader2 size={16} className="animate-spin" /> : "Aktivieren"}
        </button>
      </div>

      {denied && (
        <p className="mt-2 text-xs text-rid-accent-2">
          Benachrichtigungen sind im Browser blockiert – bitte in den Seiteneinstellungen erlauben.
        </p>
      )}
      {error && <p className="mt-2 text-xs text-rid-accent-2">{error}</p>}
    </div>
  );
}
