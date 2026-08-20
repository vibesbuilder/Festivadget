// Anonymous usage counter + client error reports (push/track.php):
// random device and session IDs, page name, language/theme - nothing else.
// Runs only in the production build, fire-and-forget via sendBeacon -
// must never slow down or break the app.

const ANON_KEY = "festivadget:anon";
const SESSION_KEY = "festivadget:session";
const SESSION_GAP_MS = 30 * 60_000; // 30 min inactivity = new session
const MAX_ERRORS_PER_SESSION = 5;

const TRACK_URL = `${import.meta.env.BASE_URL}push/track.php`;

function randomId(): string {
  try {
    return crypto.randomUUID().replace(/-/g, "").slice(0, 16);
  } catch {
    return Math.random().toString(36).slice(2, 18);
  }
}

function anonId(): string {
  let id = localStorage.getItem(ANON_KEY);
  if (!id) {
    id = randomId();
    localStorage.setItem(ANON_KEY, id);
  }
  return id;
}

function sessionId(): string {
  const now = Date.now();
  try {
    const raw = localStorage.getItem(SESSION_KEY);
    const parsed = raw ? (JSON.parse(raw) as { id: string; last: number }) : null;
    const id = parsed && now - parsed.last < SESSION_GAP_MS ? parsed.id : randomId();
    localStorage.setItem(SESSION_KEY, JSON.stringify({ id, last: now }));
    return id;
  } catch {
    return randomId();
  }
}

// Language/theme from the persisted UI store (zustand, "festivadget:ui").
function uiState(): { lang: string; theme: string } {
  try {
    const raw = localStorage.getItem("festivadget:ui");
    const state = raw ? (JSON.parse(raw) as { state?: { language?: string; theme?: string } }).state : null;
    return { lang: state?.language ?? "", theme: state?.theme ?? "" };
  } catch {
    return { lang: "", theme: "" };
  }
}

function send(payload: Record<string, unknown>): void {
  try {
    navigator.sendBeacon(
      TRACK_URL,
      new Blob([JSON.stringify({ ...payload, anon: anonId(), session: sessionId() })], {
        type: "application/json",
      }),
    );
  } catch {
    // Statistics are secondary.
  }
}

/** Report a page view (page = first path segment, e.g. "home", "timetable"). */
export function trackPage(pathname: string): void {
  if (!import.meta.env.PROD) return;
  const page = pathname.replace(/^\/+/, "").split("/")[0] || "home";
  send({ page, ...uiState() });
}

/**
 * One-time initialization (AppShell): PWA events + client errors.
 * - "_install": appinstalled event (Android/Chrome; iOS does not have it).
 * - "_standalone": app runs installed (home screen) - once per page load.
 * - Errors: window.onerror/unhandledrejection -> log (max. 5 per session).
 */
let initialized = false;

export function initTracking(): void {
  if (!import.meta.env.PROD || initialized) return;
  initialized = true;

  window.addEventListener("appinstalled", () => send({ page: "_install" }));

  const standalone =
    window.matchMedia("(display-mode: standalone)").matches ||
    (navigator as { standalone?: boolean }).standalone === true;
  if (standalone) send({ page: "_standalone" });

  // Known third-party noise (browser extensions, in-app browser bridges of
  // Instagram/Facebook & co.): not app errors -> keep out of the server log.
  const IGNORED_PATTERNS = [
    "Script error", // Cross-Origin ohne Details (Erweiterungen/Fremd-Skripte)
    "webkit.messageHandlers", // iOS-In-App-Browser-Bridge
    "@webkit-masked-url", // iOS-Safari-Erweiterungen
    "runtime.sendMessage", // Chrome-Extension-API
    "Java object is gone", // Android-WebView-Bridge
    "-extension://", // chrome-extension:// / moz-extension:// / safari-extension://
  ];

  let errorsSent = 0;
  const reportError = (message: string) => {
    if (errorsSent >= MAX_ERRORS_PER_SESSION) return;
    if (IGNORED_PATTERNS.some((p) => message.includes(p))) return;
    errorsSent++;
    send({
      type: "error",
      route: window.location.pathname,
      message: message.slice(0, 300),
    });
  };
  window.addEventListener("error", (e) => reportError(String(e.message ?? "Unknown error")));
  window.addEventListener("unhandledrejection", (e) =>
    reportError(`Unhandled rejection: ${String((e as PromiseRejectionEvent).reason).slice(0, 250)}`),
  );
}
