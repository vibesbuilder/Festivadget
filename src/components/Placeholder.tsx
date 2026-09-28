import { Construction } from "lucide-react";
import { useTranslation } from "react-i18next";

// Placeholder for routes without their own page (currently only the 404 stub).
export function Placeholder({ title, phase }: { title: string; phase?: string }) {
  const { t } = useTranslation();
  return (
    <section>
      <h1 className="mb-4 text-2xl font-bold">{title}</h1>
      <div className="rid-card flex flex-col items-center gap-3 p-8 text-center text-rid-muted">
        <Construction className="text-rid-accent" size={28} />
        <p>{t("common.pageMissing")}</p>
        {phase && <p className="text-xs uppercase tracking-wide">{phase}</p>}
      </div>
    </section>
  );
}
