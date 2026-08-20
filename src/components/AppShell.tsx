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
import i18n, { LANGUAGES, type AppLanguage } from "@/i18n/config";
import { useUi } from "@/store/ui";
import { useAppConfig } from "@/data/useAppConfig";
import { useFestival } from "@/data/queries";

// App shell: top bar, offline badge, scrollable content (<Outlet/>), bottom nav (§10).
export function AppShell() {
  // Starts the 2-minute version polling + targeted invalidation.
  useVersionSync();

  // Global admin settings (data/app-config.json).
  const config = useAppConfig();

  // Home screen label (iOS "Add to Home Screen") driven by festival.shortName.
  // iOS reads the meta tag when adding - the app is open at that point, so setting
  // it at runtime works. index.html keeps the static fallback ("ROCK IM DORF").
  const { data: festival } = useFestival();
  useEffect(() => {
    // The branding short name (CMS) wins over festival.shortName.
    const shortName = config.branding?.shortName || festival?.shortName;
    if (!shortName) return;
    document
      .querySelector('meta[name="apple-mobile-web-app-title"]')
      ?.setAttribute("content", shortName);
  }, [festival?.shortName, config.branding?.shortName]);

  // Apply the admin default theme - only while the user has not chosen themselves.
  const themeExplicit = useUi((s) => s.themeExplicit);
  const applyServerThemeDefault = useUi((s) => s.applyServerThemeDefault);
  useEffect(() => {
    if (!themeExplicit && config.themeDefault) {
      applyServerThemeDefault(config.themeDefault);
    }
  }, [config.themeDefault, themeExplicit, applyServerThemeDefault]);

  // Apply the admin default language - only while the user has not chosen themselves.
  const languageExplicit = useUi((s) => s.languageExplicit);
  const applyServerLanguageDefault = useUi((s) => s.applyServerLanguageDefault);
  useEffect(() => {
    const lang = config.languageDefault;
    if (!languageExplicit && lang && lang in LANGUAGES) {
      applyServerLanguageDefault(lang as AppLanguage);
      void i18n.changeLanguage(lang);
    }
  }, [config.languageDefault, languageExplicit, applyServerLanguageDefault]);

  // Mirror light/dark mode onto <html> (an inline script in index.html sets the initial value).
  const theme = useUi((s) => s.theme);
  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    document
      .querySelector('meta[name="theme-color"]')
      ?.setAttribute("content", themeColorFor(config.branding, theme));
  }, [theme, config.branding]);

  // Apply customer branding (colors/font/title/manifest) - must run again on
  // theme changes (inline vars apply per active theme).
  useEffect(() => {
    applyBranding(config.branding, theme);
  }, [config.branding, theme]);

  // Background artwork on/off (data-bg="off" -> --rid-bg-image: none, see index.css).
  useEffect(() => {
    document.documentElement.dataset.bg = config.background === false ? "off" : "on";
  }, [config.background]);

  // Custom background image from the admin (app-config.json -> backgroundImage,
  // e.g. /data/uploads/hero.webp). Only set the inline var while the artwork
  // is active - otherwise it would override the data-bg="off" stylesheet.
  useEffect(() => {
    const root = document.documentElement;
    const img = config.background !== false ? config.backgroundImage : undefined;
    if (img && /^\/[\w/.-]+$/.test(img)) {
      root.style.setProperty("--rid-bg-image", `url("${img}")`);
    } else {
      root.style.removeProperty("--rid-bg-image");
    }
  }, [config.backgroundImage, config.background]);

  // Once: PWA install/standalone events + client error log
  // (production build only, see lib/track.ts).
  useEffect(() => {
    initTracking();
  }, []);

  // Scroll to the top on every page change (otherwise the new page opens at the
  // previous scroll position, e.g. an artist page opened from the line-up).
  // Also: anonymous page counter (production build only, see lib/track.ts).
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
