import { Link } from "react-router-dom";
import {
  Newspaper,
  Map,
  CloudSun,
  Info,
  Handshake,
  Ticket,
  Languages,
  FileText,
  ExternalLink,
  Mail,
  Sun,
  Moon,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { useUi } from "@/store/ui";
import i18n from "@/i18n/config";
import { InstallHint } from "@/components/InstallHint";
import { useAppConfig } from "@/data/useAppConfig";

const items = [
  { to: "/news", key: "news", icon: Newspaper },
  { to: "/map", key: "map", icon: Map },
  { to: "/weather", key: "weather", icon: CloudSun },
  { to: "/info", key: "info", icon: Info },
  { to: "/sponsors", key: "sponsors", icon: Handshake },
  { to: "/tickets", key: "tickets", icon: Ticket },
] as const;

export default function More() {
  const { t } = useTranslation();
  const language = useUi((s) => s.language);
  const setLanguage = useUi((s) => s.setLanguage);
  const theme = useUi((s) => s.theme);
  const toggleTheme = useUi((s) => s.toggleTheme);

  // Per Admin (data/app-config.json) ausgeblendete MEHR-Punkte.
  const { moreHidden } = useAppConfig();
  const hidden = (key: string) => moreHidden.includes(key);

  const toggleLang = () => {
    const next = language === "de" ? "en" : "de";
    setLanguage(next);
    void i18n.changeLanguage(next);
  };

  return (
    <section>
      <h1 className="mb-4 text-2xl font-bold">{t("more.title")}</h1>
      <ul className="space-y-2">
        {items
          .filter(({ key }) => !hidden(key))
          .map(({ to, key, icon: Icon }) => (
            <li key={key}>
              <Link
                to={to}
                className="rid-card flex items-center gap-3 p-4 hover:border-rid-accent"
              >
                <Icon size={20} className="text-rid-accent" />
                <span className="font-medium">{t(`more.${key}`)}</span>
              </Link>
            </li>
          ))}
        {!hidden("contact") && (
        <li>
          <a
            href="https://rockimdorf.at/kontakt"
            target="_blank"
            rel="noopener noreferrer"
            className="rid-card flex items-center gap-3 p-4 hover:border-rid-accent"
          >
            <Mail size={20} className="text-rid-accent" />
            <span className="flex-1 font-medium">{t("more.contact")}</span>
            <ExternalLink size={16} className="text-rid-muted" />
          </a>
        </li>
        )}
        {!hidden("impressum") && (
        <li>
          <a
            href="https://rockimdorf.at/impressum"
            target="_blank"
            rel="noopener noreferrer"
            className="rid-card flex items-center gap-3 p-4 hover:border-rid-accent"
          >
            <FileText size={20} className="text-rid-accent" />
            <span className="flex-1 font-medium">{t("more.impressum")}</span>
            <ExternalLink size={16} className="text-rid-muted" />
          </a>
        </li>
        )}
        {!hidden("theme") && (
        <li>
          <button
            onClick={toggleTheme}
            className="rid-card flex w-full items-center gap-3 p-4 text-left hover:border-rid-accent"
          >
            {theme === "dark" ? (
              <Moon size={20} className="text-rid-accent" />
            ) : (
              <Sun size={20} className="text-rid-accent" />
            )}
            <span className="font-medium">{t("more.theme")}</span>
          </button>
        </li>
        )}
        {!hidden("language") && (
        <li>
          <button
            onClick={toggleLang}
            className="rid-card flex w-full items-center gap-3 p-4 text-left hover:border-rid-accent"
          >
            <Languages size={20} className="text-rid-accent" />
            <span className="font-medium">{t("more.language")}</span>
          </button>
        </li>
        )}
      </ul>

      {/* Install-Hinweis nur hier unter „Mehr" (§13). */}
      <InstallHint />

      <p className="pt-4 text-center text-xs text-rid-muted">Festivadget by hoerich</p>
    </section>
  );
}
