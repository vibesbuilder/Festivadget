import { Loader2, AlertTriangle } from "lucide-react";
import { useTranslation } from "react-i18next";

// Reusable loading/error/empty states.

export function LoadingState({ label }: { label?: string }) {
  const { t } = useTranslation();
  return (
    <div className="flex items-center justify-center gap-2 py-16 text-rid-muted">
      <Loader2 className="animate-spin" size={18} />
      <span>{label ?? t("common.loading")}</span>
    </div>
  );
}

export function ErrorState({ onRetry }: { onRetry?: () => void }) {
  const { t } = useTranslation();
  return (
    <div className="flex flex-col items-center gap-3 py-16 text-rid-muted">
      <AlertTriangle className="text-rid-accent-2" size={22} />
      <p>{t("common.error")}</p>
      {onRetry && (
        <button
          onClick={onRetry}
          className="rounded-full bg-rid-surface-2 px-4 py-1.5 text-sm text-rid-text hover:bg-rid-accent hover:text-black"
        >
          {t("common.retry")}
        </button>
      )}
    </div>
  );
}

export function EmptyState({ label }: { label?: string }) {
  const { t } = useTranslation();
  return <div className="py-16 text-center text-rid-muted">{label ?? t("common.empty")}</div>;
}
