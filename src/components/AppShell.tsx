import { Suspense, useEffect } from "react";
import { Outlet, useLocation } from "react-router-dom";
import { TopBar } from "./TopBar";
import { BottomNav } from "./BottomNav";
import { OfflineBadge } from "./OfflineBadge";
import { IosInstallPopup } from "./IosInstallPopup";
import { PresentedByFooter } from "./PresentedByFooter";
import { LoadingState } from "./states";
import { useVersionSync } from "@/data/useVersion";
import { initTracking, trackPage } from "@/lib/track";
import { applyBranding, themeColorFor } from "@/lib/branding";
import { useUi } from "@/store/ui";
import { useAppConfig } from "@/data/useAppConfig";
import { useFestival } from "@/data/queries";

// App-Shell: TopBar, OfflineBadge, scrollbarer Inhalt (<Outlet/>), untere Nav (§10).
export function AppShell() {
  // Startet das 2-Minuten-Versions-Polling + gezielte Invalidierung.
  useVersionSync();

  // Globale Admin-Einstellungen (data/app-config.json).
  const config = useAppConfig();

  // Home-Bildschirm-Label (iOS „Zum Home-Bildschirm") datengetrieben aus festival.shortName.
  // iOS liest das Meta-Tag beim Hinzufügen – die App ist dann offen, daher wirkt das Setzen
  // zur Laufzeit. index.html hält den statischen Fallback ("ROCK IM DORF").
  const { data: festival } = useFestival();
  useEffect(() => {
    // Branding-Kurzname (CMS) gewinnt über festival.shortName.
    const shortName = config.branding?.shortName || festival?.shortName;
    if (!shortName) return;
    document
      .querySelector('meta[name="apple-mobile-web-app-title"]')
      ?.setAttribute("content", shortName);
  }, [festival?.shortName, config.branding?.shortName]);

  // Admin-Standard-Theme anwenden – nur solange der User nicht selbst gewählt hat.
  const themeExplicit = useUi((s) => s.themeExplicit);
  const applyServerThemeDefault = useUi((s) => s.applyServerThemeDefault);
  useEffect(() => {
    if (!themeExplicit && config.themeDefault) {
      applyServerThemeDefault(config.themeDefault);
    }
  }, [config.themeDefault, themeExplicit, applyServerThemeDefault]);

  // Hell-/Dunkel-Modus auf <html> spiegeln (Inline-Skript in index.html setzt den Startwert).
  const theme = useUi((s) => s.theme);
  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    document
      .querySelector('meta[name="theme-color"]')
      ?.setAttribute("content", themeColorFor(config.branding, theme));
  }, [theme, config.branding]);

  // Kunden-Branding (Farben/Schrift/Titel/Manifest) anwenden – muss bei
  // Theme-Wechsel erneut laufen (Inline-Vars gelten je aktivem Theme).
  useEffect(() => {
    applyBranding(config.branding, theme);
  }, [config.branding, theme]);

  // Hintergrundgrafik an/aus (data-bg="off" → --rid-bg-image: none, siehe index.css).
  useEffect(() => {
    document.documentElement.dataset.bg = config.background === false ? "off" : "on";
  }, [config.background]);

  // Eigenes Hintergrundbild aus dem Admin (app-config.json → backgroundImage,
  // z. B. /data/uploads/hero.webp). Inline-Var nur setzen, solange die Grafik
  // aktiv ist – sonst würde sie das data-bg="off"-Stylesheet überstimmen.
  useEffect(() => {
    const root = document.documentElement;
    const img = config.background !== false ? config.backgroundImage : undefined;
    if (img && /^\/[\w/.-]+$/.test(img)) {
      root.style.setProperty("--rid-bg-image", `url("${img}")`);
    } else {
      root.style.removeProperty("--rid-bg-image");
    }
  }, [config.backgroundImage, config.background]);

  // Einmalig: PWA-Install-/Standalone-Events + Client-Fehler-Protokoll
  // (nur Produktiv-Build, siehe lib/track.ts).
  useEffect(() => {
    initTracking();
  }, []);

  // Bei jedem Seitenwechsel an den Anfang scrollen (sonst öffnet die neue Seite
  // an der vorherigen Scroll-Position, z. B. Artist-Seite aus dem Line-Up).
  // Nebenbei: anonymer Seitenzähler (nur Produktiv-Build, siehe lib/track.ts).
  const { pathname } = useLocation();
  useEffect(() => {
    window.scrollTo(0, 0);
    trackPage(pathname);
  }, [pathname]);

  return (
    <div className="flex min-h-dvh flex-col">
      <TopBar />
      <OfflineBadge />
      <main className="mx-auto w-full max-w-app flex-1 px-4 pb-6 pt-4">
        <Suspense fallback={<LoadingState />}>
          <Outlet />
        </Suspense>
        <PresentedByFooter />
      </main>
      <BottomNav />
      <IosInstallPopup />
    </div>
  );
}
