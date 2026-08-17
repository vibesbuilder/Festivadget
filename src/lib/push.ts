// Web-Push-Client (§13). Verwaltet das Abo über die Push-API und meldet es an
// das PHP-Backend (/push/subscribe.php). Der VAPID-Public-Key kommt zur
// Laufzeit vom Server (/push/vapid.php, gecacht in localStorage); die
// Build-Env (VITE_VAPID_PUBLIC_KEY) bleibt optionaler Fallback. Ohne Key
// (kein Backend + keine Env) ist Push deaktiviert.

import { useFavorites } from "@/store/favorites";

const BUILD_VAPID_KEY = import.meta.env.VITE_VAPID_PUBLIC_KEY as string | undefined;
const SUBSCRIBE_URL = `${import.meta.env.BASE_URL}push/subscribe.php`;
const VAPID_URL = `${import.meta.env.BASE_URL}push/vapid.php`;
const VAPID_CACHE_KEY = "rid-vapid-key";

// Base64URL-kodierter unkomprimierter P-256-Punkt (üblich: 87 Zeichen).
const VAPID_KEY_RE = /^[A-Za-z0-9_-]{80,100}$/;

function cachedVapidKey(): string | undefined {
  try {
    const key = localStorage.getItem(VAPID_CACHE_KEY);
    return key && VAPID_KEY_RE.test(key) ? key : undefined;
  } catch {
    return undefined;
  }
}

// Bekannter Key: Build-Env vor localStorage-Cache; sonst per ensureVapidKey().
let vapidKey: string | undefined = BUILD_VAPID_KEY || cachedVapidKey();

/**
 * Liefert den VAPID-Public-Key – ggf. per einmaligem Fetch vom Backend
 * (danach aus localStorage, damit die App offline startklar bleibt).
 * undefined = kein Key erreichbar (Push bleibt deaktiviert), wirft nie.
 */
export async function ensureVapidKey(): Promise<string | undefined> {
  if (vapidKey) return vapidKey;
  try {
    const res = await fetch(VAPID_URL);
    if (!res.ok) return undefined;
    const data = (await res.json()) as { publicKey?: unknown };
    const key = typeof data.publicKey === "string" ? data.publicKey.trim() : "";
    if (!VAPID_KEY_RE.test(key)) return undefined;
    vapidKey = key;
    try {
      localStorage.setItem(VAPID_CACHE_KEY, key);
    } catch {
      /* ignore – dann eben beim nächsten Start erneut holen */
    }
    return key;
  } catch {
    return undefined; // offline/kein Backend → Push (vorerst) nicht verfügbar
  }
}

/** Kann dieser Browser Web-Push (unabhängig von der Key-Konfiguration)? */
export function isPushCapable(): boolean {
  return "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;
}

/** Ist Web-Push möglich UND ein Key bekannt (Build, Cache oder bereits geholt)? */
export function isPushSupported(): boolean {
  return !!vapidKey && isPushCapable();
}

export function notificationPermission(): NotificationPermission {
  return typeof Notification !== "undefined" ? Notification.permission : "denied";
}

// VAPID-Key (Base64URL) → Uint8Array für applicationServerKey.
function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
  const raw = atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) output[i] = raw.charCodeAt(i);
  return output;
}

export async function currentSubscription(): Promise<PushSubscription | null> {
  if (!("serviceWorker" in navigator)) return null;
  const reg = await navigator.serviceWorker.ready;
  return reg.pushManager.getSubscription();
}

// Vom Nutzer wählbare Push-Kategorien (Safety kommt immer an, ist nicht wählbar).
export const PUSH_CATEGORIES = ["info", "lineup", "general"] as const;
export type PushCategory = (typeof PUSH_CATEGORIES)[number];

const CATS_KEY = "rid-push-categories";

/** Lokale Auswahl der Push-Kategorien (Default: alle). */
export function getPushCategories(): PushCategory[] {
  try {
    const raw = localStorage.getItem(CATS_KEY);
    if (raw) {
      const arr = JSON.parse(raw) as string[];
      return PUSH_CATEGORIES.filter((c) => arr.includes(c));
    }
  } catch {
    /* ignore */
  }
  return [...PUSH_CATEGORIES];
}

function storePushCategories(cats: PushCategory[]): void {
  try {
    localStorage.setItem(CATS_KEY, JSON.stringify(cats));
  } catch {
    /* ignore */
  }
}

// --- „Mein Plan" (Erinnerung vor Konzertbeginn an favorisierte Acts) --------
const PLAN_KEY = "rid-push-plan";

/** Ist das „Mein Plan"-Abo (Favoriten-Erinnerungen) aktiviert? (Default: aus) */
export function getPlanEnabled(): boolean {
  try {
    return localStorage.getItem(PLAN_KEY) === "1";
  } catch {
    return false;
  }
}

function storePlanEnabled(on: boolean): void {
  try {
    localStorage.setItem(PLAN_KEY, on ? "1" : "0");
  } catch {
    /* ignore */
  }
}

/** Aktuell ans Backend zu meldende Plan-Slots: nur wenn Plan-Abo aktiv. */
function currentPlanSlots(): string[] {
  return getPlanEnabled() ? Array.from(useFavorites.getState().favorites) : [];
}

/** Meldet den aktuellen Zustand (Kategorien + Plan) ans Backend – falls abonniert. */
async function postSubscriptionState(): Promise<void> {
  const sub = await currentSubscription();
  if (!sub) return; // nur lokal merken, bis abonniert wird
  await fetch(SUBSCRIBE_URL, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      action: "subscribe",
      subscription: sub.toJSON(),
      categories: getPushCategories(),
      plan: currentPlanSlots(),
    }),
  }).catch(() => {
    /* offline → wird beim nächsten Subscribe/Update nachgezogen */
  });
}

/** Fordert Erlaubnis an, abonniert und meldet das Abo (Kategorien + Plan) ans Backend. */
export async function subscribePush(): Promise<PushSubscription> {
  const key = await ensureVapidKey();
  if (!key) throw new Error("Kein VAPID-Public-Key konfiguriert.");

  const permission = await Notification.requestPermission();
  if (permission !== "granted") throw new Error("Benachrichtigungen nicht erlaubt.");

  const reg = await navigator.serviceWorker.ready;
  let sub = await reg.pushManager.getSubscription();
  if (!sub) {
    sub = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(key) as BufferSource,
    });
  }

  const res = await fetch(SUBSCRIBE_URL, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      action: "subscribe",
      subscription: sub.toJSON(),
      categories: getPushCategories(),
      plan: currentPlanSlots(),
    }),
  });
  if (!res.ok) throw new Error(`Abo konnte nicht gespeichert werden (HTTP ${res.status}).`);

  return sub;
}

/** Aktualisiert die Kategorie-Auswahl (lokal + Backend, falls bereits abonniert). */
export async function updatePushCategories(cats: PushCategory[]): Promise<void> {
  storePushCategories(cats);
  await postSubscriptionState();
}

/** Schaltet das „Mein Plan"-Abo um und meldet den Stand ans Backend. */
export async function updatePushPlanEnabled(on: boolean): Promise<void> {
  storePlanEnabled(on);
  await postSubscriptionState();
}

/** Bei Favoriten-Änderung aufrufen: synchronisiert den Plan, falls aktiv + abonniert. */
export async function syncPushPlan(): Promise<void> {
  if (!getPlanEnabled()) return;
  await postSubscriptionState();
}

/** Meldet das Abo ab (Backend + Browser). */
export async function unsubscribePush(): Promise<void> {
  const sub = await currentSubscription();
  if (!sub) return;

  await fetch(SUBSCRIBE_URL, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "unsubscribe", endpoint: sub.endpoint }),
  }).catch(() => {
    /* Abmeldung im Browser trotzdem versuchen. */
  });

  await sub.unsubscribe();
}
