import { lazy } from "react";
import { createBrowserRouter } from "react-router-dom";
import { AppShell } from "@/components/AppShell";
import Home from "@/routes/Home";
import { NotFound } from "@/routes/stubs";

// Home bleibt eager (Landing). Übrige Routen werden lazy geladen (§17, Phase 4) –
// schwere Abhängigkeiten (Leaflet, Markdown) landen so in eigenen Chunks.
const Lineup = lazy(() => import("@/routes/Lineup"));
const Artist = lazy(() => import("@/routes/Artist"));
const More = lazy(() => import("@/routes/More"));
const Timetable = lazy(() => import("@/routes/Timetable"));
const Favorites = lazy(() => import("@/routes/Favorites"));
const Info = lazy(() => import("@/routes/Info"));
const InfoDetail = lazy(() => import("@/routes/InfoDetail"));
const Sponsors = lazy(() => import("@/routes/Sponsors"));
const Tickets = lazy(() => import("@/routes/Tickets"));
const MapPage = lazy(() => import("@/routes/MapPage"));
const News = lazy(() => import("@/routes/News"));
const Search = lazy(() => import("@/routes/Search"));
const Weather = lazy(() => import("@/routes/Weather"));

// Routing-Tabelle (§9). Explizite (rein typseitige) Annotation, damit das
// Declaration-Emit unter pnpm den Router-Typ benennen kann (sonst TS2742, weil
// der transitive @remix-run/router-Typ im .pnpm-Store nicht portabel referenzierbar ist).
export const router: ReturnType<typeof createBrowserRouter> = createBrowserRouter(
  [
    {
      path: "/",
      element: <AppShell />,
      children: [
        { index: true, element: <Home /> },
        { path: "lineup", element: <Lineup /> },
        { path: "artist/:slug", element: <Artist /> },
        { path: "timetable", element: <Timetable /> },
        { path: "favorites", element: <Favorites /> },
        { path: "map", element: <MapPage /> },
        { path: "news", element: <News /> },
        { path: "info", element: <Info /> },
        { path: "info/:id", element: <InfoDetail /> },
        { path: "sponsors", element: <Sponsors /> },
        { path: "tickets", element: <Tickets /> },
        { path: "search", element: <Search /> },
        { path: "weather", element: <Weather /> },
        { path: "more", element: <More /> },
        { path: "*", element: <NotFound /> },
      ],
    },
  ],
  {
    future: {
      v7_relativeSplatPath: true,
    },
  },
);
