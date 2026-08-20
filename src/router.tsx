import { lazy } from "react";
import { createBrowserRouter } from "react-router-dom";
import { AppShell } from "@/components/AppShell";
import Home from "@/routes/Home";
import { NotFound } from "@/routes/stubs";

// Home stays eager (landing). Remaining routes are lazy-loaded (§17, phase 4) -
// heavy dependencies (Leaflet, Markdown) end up in their own chunks.
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

// Routing table (§9). Explicit (purely type-level) annotation so declaration
// emit under pnpm can name the router type (otherwise TS2742, because the
// transitive @remix-run/router type in the .pnpm store is not portably referenceable).
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
