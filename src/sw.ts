/// <reference lib="webworker" />
import { precacheAndRoute, cleanupOutdatedCaches } from "workbox-precaching";
import { registerRoute } from "workbox-routing";
import { NetworkFirst, StaleWhileRevalidate } from "workbox-strategies";
import { ExpirationPlugin } from "workbox-expiration";

// Custom service worker (injectManifest, phase 5). Reproduces the previous
// runtime caching (§5.1) AND adds web push (§13).

declare const self: ServiceWorkerGlobalScope & {
  __WB_MANIFEST: Array<{ url: string; revision: string | null }>;
};

// Precache the app shell (injected by vite-plugin-pwa).
precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();

// version.json: always from the network, cache only as offline fallback.
registerRoute(
  ({ url }) => url.pathname.endsWith("/data/version.json"),
  new NetworkFirst({
    cacheName: "festivadget-version",
    networkTimeoutSeconds: 3,
    plugins: [new ExpirationPlugin({ maxEntries: 4 })],
  }),
);

// Content JSON: NetworkFirst with a short timeout, fallback cache.
registerRoute(
  ({ url }) => url.pathname.startsWith("/data/") && url.pathname.endsWith(".json"),
  new NetworkFirst({
    cacheName: "festivadget-data",
    networkTimeoutSeconds: 3,
    plugins: [new ExpirationPlugin({ maxEntries: 64, maxAgeSeconds: 60 * 60 * 24 * 14 })],
  }),
);

// Images/map: StaleWhileRevalidate.
registerRoute(
  ({ url }) => url.pathname.startsWith("/img/") || url.pathname.startsWith("/map/"),
  new StaleWhileRevalidate({
    cacheName: "festivadget-media",
    plugins: [new ExpirationPlugin({ maxEntries: 256, maxAgeSeconds: 60 * 60 * 24 * 30 })],
  }),
);

// Immediate activation of new SW versions (autoUpdate behavior).
self.addEventListener("install", () => {
  void self.skipWaiting();
});
self.addEventListener("activate", (event) => {
  event.waitUntil(self.clients.claim());
});

// --- Web push (§13) -------------------------------------------------------

interface PushPayload {
  title?: string;
  body?: string;
  url?: string;
  tag?: string;
}

self.addEventListener("push", (event) => {
  let payload: PushPayload = {};
  try {
    payload = event.data?.json() ?? {};
  } catch {
    payload = { body: event.data?.text() };
  }

  const title = payload.title ?? "Festivadget";
  const options: NotificationOptions = {
    body: payload.body ?? "",
    icon: "/icons/icon-192.png",
    badge: "/icons/icon-192.png",
    tag: payload.tag,
    data: { url: payload.url ?? "/" },
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const target = (event.notification.data as { url?: string })?.url ?? "/";

  event.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((clients) => {
      // Focus an already open window, otherwise open a new one.
      for (const client of clients) {
        if ("focus" in client) {
          void client.focus();
          if ("navigate" in client) void client.navigate(target);
          return;
        }
      }
      return self.clients.openWindow(target);
    }),
  );
});
