// Zeit-Helfer leben jetzt in @rid/core (geteilt mit CrewCare). Dünner Re-Export,
// damit App-Importe `@/lib/time` unverändert bleiben.
export {
  DEFAULT_TZ,
  parse,
  now,
  formatTime,
  formatDateTime,
  formatDateRange,
  isLive,
  dayForInstant,
  slotsOverlap,
  durationMinutes,
} from "@rid/core";
