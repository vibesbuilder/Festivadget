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
import i18n, { LANGUAGES, type AppLanguage } from "@/i18n/config";
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

  // MORE entries hidden via the admin (data/app-config.json).
  const { moreHidden, contactUrl, impressumUrl } = useAppConfig();
  const hidden = (key: string) => moreHidden.includes(key);

  const selectLang = (next: AppLanguage) => {
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
        {!hidden("contact") && contactUrl && (
        <li>
          <a
            href={contactUrl}
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
        {!hidden("impressum") && impressumUrl && (
        <li>
          <a
            href={impressumUrl}
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
          <div className="rid-card p-4">
            <div className="flex items-center gap-3">
              <Languages size={20} className="text-rid-accent" />
              <span className="font-medium">{t("more.language")}</span>
            </div>
            <div className="mt-3 flex flex-wrap gap-2">
              {(Object.keys(LANGUAGES) as AppLanguage[]).map((code) => (
                <button
                  key={code}
                  onClick={() => selectLang(code)}
                  className={language === code ? "rid-chip rid-chip-active" : "rid-chip"}
                >
                  {LANGUAGES[code]}
                </button>
              ))}
            </div>
          </div>
        </li>
        )}
      </ul>

      {/* Install hint only here under "More" (§13). */}
      <InstallHint />

      {/* Instance override via VITE_FOOTER_CREDIT (RID .env); the release builds neutral. */}
      <p className="pt-4 text-center text-xs text-rid-muted">
        {import.meta.env.VITE_FOOTER_CREDIT || "Festivadget by vibesbuilder"}
      </p>
    </section>
  );
}
