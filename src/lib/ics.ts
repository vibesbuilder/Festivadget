// .ics generation now lives in @rid/core (shared with CrewCare). Thin re-export
// so app imports of `@/lib/ics` stay unchanged.
export { slotToVEvent, buildIcs, downloadIcs } from "@rid/core";
