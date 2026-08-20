// Web push client (§13). Manages the subscription via the Push API and reports
// it to the PHP backend (/push/subscribe.php). The VAPID public key arrives at
// runtime from the server (/push/vapid.php, cached in localStorage); the
// build env (VITE_VAPID_PUBLIC_KEY) remains an optional fallback. Without a key
// (no backend + no env) push is disabled.

import { useFavorites } from "@/store/favorites";
import { useUi } from "@/store/ui";
import i18n from "@/i18n/config";

const BUILD_VAPID_KEY = import.meta.env.VITE_VAPID_PUBLIC_KEY as string | undefined;
const SUBSCRIBE_URL = `${import.meta.env.BASE_URL}push/subscribe.php`;
const VAPID_URL = `${import.meta.env.BASE_URL}push/vapid.php`;
const VAPID_CACHE_KEY = "rid-vapid-key";

// Base64URL-encoded uncompressed P-256 point (typically 87 chars).
const VAPID_KEY_RE = /^[A-Za-z0-9_-]{80,100}$/;

function cachedVapidKey(): string | undefined {
  try {
    const key = localStorage.getItem(VAPID_CACHE_KEY);
    return key && VAPID_KEY_RE.test(key) ? key : undefined;
  } catch {
    return undefined;
  }
}

// Known key: build env before the localStorage cache; otherwise via ensureVapidKey().
let vapidKey: string | undefined = BUILD_VAPID_KEY || cachedVapidKey();

/**
 * Returns the VAPID public key - fetched once from the backend if needed
 * (afterwards from localStorage so the app stays ready to start offline).
 * undefined = no key reachable (push stays disabled), never throws.
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
      /* ignore - fetch again on the next start */
    }
    return key;
  } catch {
    return undefined; // offline/no backend -> push unavailable (for now)
  }
}

/** Can this browser do web push (independent of the key configuration)? */
export function isPushCapable(): boolean {
  return "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;
}

/** Is web push possible AND a key known (build, cache or already fetched)? */
export function isPushSupported(): boolean {
  return !!vapidKey && isPushCapable();
}

export function notificationPermission(): NotificationPermission {
  return typeof Notification !== "undefined" ? Notification.permission : "denied";
}

// VAPID key (Base64URL) -> Uint8Array for applicationServerKey.
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

// User-selectable push categories (safety always gets through, not selectable).
export const PUSH_CATEGORIES = ["info", "lineup", "general"] as const;
export type PushCategory = (typeof PUSH_CATEGORIES)[number];

const CATS_KEY = "rid-push-categories";

/** Local selection of push categories (default: all). */
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

// --- "My plan" (reminder before showtime for favorited acts) ----------------
const PLAN_KEY = "rid-push-plan";

/** Is the "My plan" subscription (favorite reminders) enabled? (default: off) */
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

/** Plan slots to report to the backend right now: only while the plan subscription is active. */
function currentPlanSlots(): string[] {
  return getPlanEnabled() ? Array.from(useFavorites.getState().favorites) : [];
}

/** Reports the current state (categories + plan) to the backend - if subscribed. */
async function postSubscriptionState(): Promise<void> {
  const sub = await currentSubscription();
  if (!sub) return; // only remember locally until subscribed
  await fetch(SUBSCRIBE_URL, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      action: "subscribe",
      subscription: sub.toJSON(),
      categories: getPushCategories(),
      plan: currentPlanSlots(),
      lang: useUi.getState().language,
    }),
  }).catch(() => {
    /* offline -> caught up on the next subscribe/update */
  });
}

/** Requests permission, subscribes and reports the subscription (categories + plan) to the backend. */
export async function subscribePush(): Promise<PushSubscription> {
  const key = await ensureVapidKey();
  if (!key) throw new Error(i18n.t("push.noKey"));

  const permission = await Notification.requestPermission();
  if (permission !== "granted") throw new Error(i18n.t("push.denied"));

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
      lang: useUi.getState().language,
    }),
  });
  if (!res.ok) throw new Error(i18n.t("push.saveFailed", { status: res.status }));

  return sub;
}

/** Updates the category selection (locally + backend, if already subscribed). */
export async function updatePushCategories(cats: PushCategory[]): Promise<void> {
  storePushCategories(cats);
  await postSubscriptionState();
}

/** Toggles the "My plan" subscription and reports the state to the backend. */
export async function updatePushPlanEnabled(on: boolean): Promise<void> {
  storePlanEnabled(on);
  await postSubscriptionState();
}

/** Call on favorite changes: syncs the plan if active + subscribed. */
export async function syncPushPlan(): Promise<void> {
  if (!getPlanEnabled()) return;
  await postSubscriptionState();
}

/** Call on language changes: updates the subscription language at the backend. */
export async function syncPushLanguage(): Promise<void> {
  await postSubscriptionState();
}

/** Unsubscribes (backend + browser). */
export async function unsubscribePush(): Promise<void> {
  const sub = await currentSubscription();
  if (!sub) return;

  await fetch(SUBSCRIBE_URL, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "unsubscribe", endpoint: sub.endpoint }),
  }).catch(() => {
    /* Still attempt to unsubscribe in the browser. */
  });

  await sub.unsubscribe();
}
