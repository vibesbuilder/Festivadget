import { WifiOff } from "lucide-react";
import { useTranslation } from "react-i18next";
import { DateTime } from "luxon";
import { useOnline } from "@/lib/useOnline";
import { useVersion } from "@/data/useVersion";

// Shows "Offline / as of HH:MM" based on the last successful fetch (§5.3).
export function OfflineBadge() {
  const online = useOnline();
  const { t } = useTranslation();
  const { data } = useVersion();

  if (online) return null;

  const stamp = data?.generatedAt
    ? DateTime.fromISO(data.generatedAt, { setZone: true }).toFormat("HH:mm")
    : "—";

  return (
    <div className="flex items-center justify-center gap-2 bg-rid-accent-2 px-3 py-1 text-xs font-medium text-white">
      <WifiOff size={14} />
      <span>
        {t("common.offline")} · {t("common.lastUpdate", { time: stamp })}
      </span>
    </div>
  );
}
