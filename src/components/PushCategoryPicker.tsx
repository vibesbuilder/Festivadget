import { useState } from "react";
import {
  PUSH_CATEGORIES,
  getPushCategories,
  updatePushCategories,
  getPlanEnabled,
  updatePushPlanEnabled,
  type PushCategory,
} from "@/lib/push";

const CATEGORY_LABELS: Record<PushCategory, string> = {
  info: "Infos",
  lineup: "Line-Up",
  general: "Allgemein",
};

// Push category selection (safety always gets through) + "My plan" reminders.
// Used in the header bell popover.
export function PushCategoryPicker() {
  const [cats, setCats] = useState<PushCategory[]>(getPushCategories);
  const [plan, setPlan] = useState<boolean>(getPlanEnabled);

  const toggle = (c: PushCategory) => {
    const next = cats.includes(c) ? cats.filter((x) => x !== c) : [...cats, c];
    setCats(next);
    void updatePushCategories(next);
  };

  const togglePlan = () => {
    const next = !plan;
    setPlan(next);
    void updatePushPlanEnabled(next);
  };

  return (
    <div className="space-y-1.5">
      {PUSH_CATEGORIES.map((c) => (
        <label key={c} className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={cats.includes(c)}
            onChange={() => toggle(c)}
            className="h-4 w-4 accent-rid-accent"
          />
          {CATEGORY_LABELS[c]}
        </label>
      ))}
      <label className="flex items-center gap-2 text-sm text-rid-muted">
        <input type="checkbox" checked disabled className="h-4 w-4 accent-rid-accent" />
        Sicherheit <span className="text-xs">(immer aktiv)</span>
      </label>
      <label className="mt-1 flex items-center gap-2 border-t border-rid-border pt-2 text-sm">
        <input
          type="checkbox"
          checked={plan}
          onChange={togglePlan}
          className="h-4 w-4 accent-rid-accent"
        />
        Mein Plan <span className="text-xs text-rid-muted">(Erinnerung vor Konzertbeginn)</span>
      </label>
    </div>
  );
}
