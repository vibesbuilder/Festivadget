import { useTranslation } from "react-i18next";
import { Placeholder } from "@/components/Placeholder";

// Placeholder for routes without their own implementation (404).
export const NotFound = () => {
  const { t } = useTranslation();
  return <Placeholder title={t("common.notFound")} />;
};
