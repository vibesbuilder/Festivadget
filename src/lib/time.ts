// Time helpers now live in @rid/core (shared with CrewCare). Thin re-export
// so app imports of `@/lib/time` stay unchanged.
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
