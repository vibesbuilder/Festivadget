import { NavLink } from "react-router-dom";
import { Home, Music2, CalendarClock, CalendarHeart, Menu } from "lucide-react";
import { useTranslation } from "react-i18next";

// Bottom tab bar with 5 slots (§9). "More" opens the More sheet via a route.
const tabs = [
  { to: "/", key: "home", icon: Home, end: true },
  { to: "/lineup", key: "lineup", icon: Music2, end: false },
  { to: "/timetable", key: "timetable", icon: CalendarClock, end: false },
  { to: "/favorites", key: "myplan", icon: CalendarHeart, end: false },
  { to: "/more", key: "more", icon: Menu, end: false },
] as const;

export function BottomNav() {
  const { t } = useTranslation();

  return (
    <nav
      className="sticky bottom-0 z-30 border-t border-rid-border bg-rid-surface/95 backdrop-blur"
      style={{ paddingBottom: "var(--safe-bottom)" }}
    >
      <ul className="mx-auto flex max-w-app items-stretch justify-between">
        {tabs.map(({ to, key, icon: Icon, end }) => (
          <li key={key} className="flex-1">
            <NavLink
              to={to}
              end={end}
              className={({ isActive }) =>
                [
                  "flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium transition-colors",
                  isActive ? "text-rid-accent" : "text-rid-muted hover:text-rid-text",
                ].join(" ")
              }
            >
              <Icon size={22} strokeWidth={2} />
              <span className="text-center leading-tight">{t(`nav.${key}`)}</span>
            </NavLink>
          </li>
        ))}
      </ul>
    </nav>
  );
}
