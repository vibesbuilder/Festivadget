// Anonymer Nutzungs-Zähler + Client-Fehlermeldungen (push/track.php):
// zufällige Geräte- und Sitzungskennung, Seitenname, Sprache/Theme – sonst
// nichts. Läuft nur im Produktiv-Build, fire-and-forget per sendBeacon –
// darf die App nie bremsen oder brechen.

const ANON_KEY = "festivadget:anon";
const SESSION_KEY = "festivadget:session";
const SESSION_GAP_MS = 30 * 60_000; // 30 min Inaktivität = neue Sitzung
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

// Sprache/Theme aus dem persistierten UI-Store (zustand, "festivadget:ui").
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
    // Statistik ist nachrangig.
  }
}

/** Seitenaufruf melden (page = erstes Pfadsegment, z. B. "home", "timetable"). */
export function trackPage(pathname: string): void {
  if (!import.meta.env.PROD) return;
  const page = pathname.replace(/^\/+/, "").split("/")[0] || "home";
  send({ page, ...uiState() });
}

/**
 * Einmalige Initialisierung (AppShell): PWA-Events + Client-Fehler.
 * - "_install": appinstalled-Event (Android/Chrome; iOS kennt das nicht).
 * - "_standalone": App läuft installiert (Home-Bildschirm) – 1x pro Seitenladen.
 * - Fehler: window.onerror/unhandledrejection → Protokoll (max. 5 je Sitzung).
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

  // Bekanntes Fremd-Rauschen (Browser-Erweiterungen, In-App-Browser-Bridges von
  // Instagram/Facebook & Co.): keine App-Fehler → nicht ins Server-Protokoll.
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
  window.addEventListener("error", (e) => reportError(String(e.message ?? "Unbekannter Fehler")));
  window.addEventListener("unhandledrejection", (e) =>
    reportError(`Unhandled rejection: ${String((e as PromiseRejectionEvent).reason).slice(0, 250)}`),
  );
}
