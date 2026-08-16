import { useEffect, useState } from "react";
import { Download, Share, X } from "lucide-react";

// Install-Hinweise (§13): Android/Chrome via beforeinstallprompt-Button,
// iOS via Teilen-Hinweis. Einmal dismissbar (localStorage).

const DISMISS_KEY = "festivadget:install-dismissed";

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: "accepted" | "dismissed" }>;
}

function isIos(): boolean {
  return /iphone|ipad|ipod/i.test(navigator.userAgent);
}

function isStandalone(): boolean {
  return (
    window.matchMedia("(display-mode: standalone)").matches ||
    // iOS Safari
    (navigator as unknown as { standalone?: boolean }).standalone === true
  );
}

export function InstallHint() {
  const [deferred, setDeferred] = useState<BeforeInstallPromptEvent | null>(null);
  const [show, setShow] = useState(false);

  useEffect(() => {
    if (isStandalone() || localStorage.getItem(DISMISS_KEY)) return;

    // Android/Chrome: Prompt abfangen.
    const onPrompt = (e: Event) => {
      e.preventDefault();
      setDeferred(e as BeforeInstallPromptEvent);
      setShow(true);
    };
    window.addEventListener("beforeinstallprompt", onPrompt);

    // iOS: kein Prompt-Event → eigener Hinweis.
    if (isIos()) setShow(true);

    return () => window.removeEventListener("beforeinstallprompt", onPrompt);
  }, []);

  if (!show) return null;

  const dismiss = () => {
    setShow(false);
    localStorage.setItem(DISMISS_KEY, "1");
  };

  const install = async () => {
    if (!deferred) return;
    await deferred.prompt();
    await deferred.userChoice;
    dismiss();
  };

  return (
    <div className="mt-4">
      <div className="flex items-center gap-3 rounded-xl border border-rid-accent/50 bg-rid-surface p-3">
        <Download size={20} className="shrink-0 text-rid-accent" />
        <div className="min-w-0 flex-1 text-sm">
          {deferred ? (
            <span>App installieren für Offline-Nutzung.</span>
          ) : (
            <span className="inline-flex flex-wrap items-center gap-1">
              Zum Home-Bildschirm: <Share size={14} className="inline" /> Teilen → „Zum
              Home-Bildschirm".
            </span>
          )}
        </div>
        {deferred && (
          <button
            onClick={install}
            className="shrink-0 rounded-full bg-rid-accent px-3 py-1.5 text-sm font-medium text-black"
          >
            Installieren
          </button>
        )}
        <button onClick={dismiss} aria-label="Schließen" className="shrink-0 p-1 text-rid-muted">
          <X size={18} />
        </button>
      </div>
    </div>
  );
}
