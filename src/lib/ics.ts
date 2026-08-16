// .ics-Erzeugung lebt jetzt in @rid/core (geteilt mit CrewCare). Dünner Re-Export,
// damit App-Importe `@/lib/ics` unverändert bleiben.
export { slotToVEvent, buildIcs, downloadIcs } from "@rid/core";
