import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { VitePWA } from "vite-plugin-pwa";
import { fileURLToPath, URL } from "node:url";

// Festivadget – Vite-Konfiguration.
// PWA: App-Shell wird precached; Laufzeit-Caching gemäß IMPLEMENTATION.md §5.1.
export default defineConfig({
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
  },
  build: {
    rollupOptions: {
      output: {
        // Kuratierter App-Vendor-Chunk (stets benötigt). Leaflet kommt in einen
        // eigenen Chunk (nur Karte). Markdown/Luxon u. a. überlässt man Rollups
        // automatischer Shared-Chunk-Aufteilung – sie landen so in eigenen, lazy
        // geladenen Chunks, ohne zirkuläre Abhängigkeiten zu erzeugen.
        manualChunks(id) {
          if (!id.includes("node_modules")) return undefined;
          if (id.includes("node_modules/leaflet")) return "leaflet";
          const vendorPkgs = [
            "node_modules/react/",
            "node_modules/react-dom/",
            "node_modules/react-router",
            "node_modules/scheduler/",
            "node_modules/@tanstack",
            "node_modules/zustand",
            "node_modules/i18next",
            "node_modules/react-i18next",
            "node_modules/lucide-react",
            "node_modules/idb-keyval",
          ];
          if (vendorPkgs.some((p) => id.includes(p))) return "vendor";
          return undefined;
        },
      },
    },
  },
  plugins: [
    react(),
    VitePWA({
      registerType: "autoUpdate",
      // Eigener Service Worker (Phase 5: Web-Push). Caching-Logik liegt in src/sw.ts.
      strategies: "injectManifest",
      srcDir: "src",
      filename: "sw.ts",
      includeAssets: ["icons/*.svg", "icons/*.png", "map/*.webp", "map/*.svg"],
      manifest: {
        name: "ROCK IM DORF Festival",
        short_name: "ROCK IM DORF",
        description: "Festival-Begleiter für mehrtägige Events – offline-fähig.",
        lang: "de",
        theme_color: "#121212",
        background_color: "#121212",
        display: "standalone",
        orientation: "portrait",
        start_url: "/",
        scope: "/",
        icons: [
          { src: "/icons/icon-192.png", sizes: "192x192", type: "image/png" },
          { src: "/icons/icon-512.png", sizes: "512x512", type: "image/png" },
          {
            src: "/icons/icon-maskable-512.png",
            sizes: "512x512",
            type: "image/png",
            purpose: "maskable",
          },
          { src: "/icons/icon.svg", sizes: "any", type: "image/svg+xml" },
          {
            src: "/icons/icon-maskable.svg",
            sizes: "any",
            type: "image/svg+xml",
            purpose: "maskable",
          },
        ],
      },
      injectManifest: {
        // Per-Datei-Limit anheben, damit auch der große Geländeplan (venue.jpg ~0,8 MB)
        // precacht wird (Workbox-Default sind 2 MiB – hier großzügig auf 4 MiB).
        maximumFileSizeToCacheInBytes: 4 * 1024 * 1024,
        globPatterns: [
          "**/*.{js,css,html,woff2,svg,png}",
          // Medien für vollständige Offline-Nutzung precachen (auch ohne vorherigen
          // Online-Aufruf): Sponsor-Logos, Artist-Fotos und der Geländeplan.
          // background.webp bleibt absichtlich im Runtime-Cache (nur Deko).
          "img/sponsors/**/*.{webp,jpg,jpeg}",
          "img/artists/**/*.{webp,jpg,jpeg}",
          "map/**/*.{jpg,jpeg}",
        ],
      },
      devOptions: {
        enabled: false,
        type: "module",
      },
    }),
  ],
});
