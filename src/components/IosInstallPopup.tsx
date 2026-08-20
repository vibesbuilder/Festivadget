import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import { Share, Plus, X } from "lucide-react";
import { useFestival } from "@/data/queries";

// One-time popup on first start - ONLY for iOS users who have not yet added the
// app to their home screen. Explains that this is required to receive push
// notifications (iOS only allows web push for installed PWAs).
// Can also be reopened at any time via the window event
// (e.g. "More info" in the notifications toggle).

const SHOWN_KEY = "festivadget:ios-popup-shown";

// Event other components (e.g. NotificationsToggle) use to reopen the popup.
export const IOS_POPUP_EVENT = "festivadget:show-ios-popup";

function isIos(): boolean {
  const ua = navigator.userAgent;
  const iOSDevice = /iphone|ipad|ipod/i.test(ua);
  // iPadOS 13+ identifies as "Macintosh" with touch support.
  const iPadOS = /Macintosh/.test(ua) && navigator.maxTouchPoints > 1;
  return iOSDevice || iPadOS;
}

function isStandalone(): boolean {
  return (
    window.matchMedia("(display-mode: standalone)").matches ||
    (navigator as unknown as { standalone?: boolean }).standalone === true
  );
}

export function IosInstallPopup() {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const { data: festival } = useFestival();
  const appName = festival?.shortName ?? festival?.name ?? "die App";

  // Show automatically on first start (iOS, not installed yet).
  useEffect(() => {
    if (isIos() && !isStandalone() && !localStorage.getItem(SHOWN_KEY)) {
      setOpen(true);
    }
  }, []);

  // Reopen manually (e.g. "More info") - independent of the "already shown" flag.
  useEffect(() => {
    const reopen = () => setOpen(true);
    window.addEventListener(IOS_POPUP_EVENT, reopen);
    return () => window.removeEventListener(IOS_POPUP_EVENT, reopen);
  }, []);

  if (!open) return null;

  const dismiss = () => {
    localStorage.setItem(SHOWN_KEY, "1");
    setOpen(false);
  };

  return (
    <div
      className="fixed inset-0 z-[2000] flex items-end justify-center bg-black/70 p-4"
      role="dialog"
      aria-modal="true"
      onClick={dismiss}
    >
      <div
        className="w-full max-w-app rounded-2xl border border-rid-border bg-rid-surface p-5 shadow-2xl"
        style={{ marginBottom: "var(--safe-bottom)" }}
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-3 flex items-start justify-between gap-3">
          <h2 className="text-lg font-bold">Benachrichtigungen aktivieren</h2>
          <button onClick={dismiss} aria-label={t("common.close")} className="p-1 text-rid-muted">
            <X size={20} />
          </button>
        </div>

        <p className="text-sm text-rid-text/90">
          Füge {appName} zum <strong>Home-Bildschirm</strong> hinzu – nur so kannst du
          Push-Benachrichtigungen am <strong>Sperrbildschirm</strong> deines iPhone/iPad empfangen
          (z. B. Konzertstarts & Sicherheitshinweise, wähle selbst).
        </p>

        <ol className="mt-4 space-y-3 text-sm">
          <li className="flex items-center gap-3">
            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rid-surface-2 font-bold">
              1
            </span>
            <span className="flex items-center gap-1">
              Unten auf das Teilen-Symbol
              <Share size={16} className="text-rid-accent" /> tippen
            </span>
          </li>
          <li className="flex items-center gap-3">
            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rid-surface-2 font-bold">
              2
            </span>
            <span className="flex items-center gap-1">
              <span className="inline-flex items-center gap-1 rounded bg-rid-surface-2 px-1.5 py-0.5">
                Zum Home-Bildschirm <Plus size={14} className="text-rid-accent" />
              </span>
              wählen
            </span>
          </li>
          <li className="flex items-center gap-3">
            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rid-surface-2 font-bold">
              3
            </span>
            <span>{appName} vom Home-Bildschirm öffnen</span>
          </li>
          <li className="flex items-center gap-3">
            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rid-surface-2 font-bold">
              4
            </span>
            <span>
              In der App bei <strong>Benachrichtigungen</strong> auf <strong>Aktivieren</strong>{" "}
              klicken
            </span>
          </li>
        </ol>

        <button
          onClick={dismiss}
          className="mt-5 w-full rounded-full bg-rid-accent py-2.5 font-semibold text-black"
        >
          Verstanden
        </button>
      </div>
    </div>
  );
}
