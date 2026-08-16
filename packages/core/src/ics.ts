import { DateTime } from "luxon";
import type { Artist, Slot, Stage } from "./types";

// Minimale .ics-Erzeugung (kein Paket nötig, §11). VEVENT mit VALARM (-PT15M).
// Funktioniert auf iOS + Android.

function toIcsStamp(iso: string): string {
  // In UTC, Format: 20260731T200000Z
  return DateTime.fromISO(iso, { setZone: true }).toUTC().toFormat("yyyyLLdd'T'HHmmss'Z'");
}

function escapeText(text: string): string {
  return text.replace(/\\/g, "\\\\").replace(/;/g, "\\;").replace(/,/g, "\\,").replace(/\n/g, "\\n");
}

function fold(line: string): string {
  // RFC 5545: Zeilen > 75 Oktette falten.
  if (line.length <= 75) return line;
  const chunks: string[] = [];
  let rest = line;
  chunks.push(rest.slice(0, 75));
  rest = rest.slice(75);
  while (rest.length > 74) {
    chunks.push(" " + rest.slice(0, 74));
    rest = rest.slice(74);
  }
  if (rest.length) chunks.push(" " + rest);
  return chunks.join("\r\n");
}

interface IcsInput {
  slot: Slot;
  artist: Artist;
  stage?: Stage;
  prodId?: string;
}

export function slotToVEvent({ slot, artist, stage }: IcsInput): string {
  const summary = `${artist.name}${stage ? " @ " + stage.name : ""}`;
  const lines = [
    "BEGIN:VEVENT",
    `UID:festivadget-${slot.id}@festivadget`,
    `DTSTAMP:${toIcsStamp(slot.start)}`,
    `DTSTART:${toIcsStamp(slot.start)}`,
    `DTEND:${toIcsStamp(slot.end)}`,
    `SUMMARY:${escapeText(summary)}`,
    stage ? `LOCATION:${escapeText(stage.name)}` : "",
    slot.note ? `DESCRIPTION:${escapeText(slot.note)}` : "",
    "BEGIN:VALARM",
    "TRIGGER:-PT15M",
    "ACTION:DISPLAY",
    `DESCRIPTION:${escapeText("Bald: " + summary)}`,
    "END:VALARM",
    "END:VEVENT",
  ].filter(Boolean);
  return lines.map(fold).join("\r\n");
}

export function buildIcs(events: string[], prodId = "-//Festivadget//DE"): string {
  return [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    `PRODID:${prodId}`,
    "CALSCALE:GREGORIAN",
    "METHOD:PUBLISH",
    ...events,
    "END:VCALENDAR",
  ].join("\r\n");
}

/** Startet den Download einer .ics-Datei im Browser. */
export function downloadIcs(filename: string, icsContent: string): void {
  const blob = new Blob([icsContent], { type: "text/calendar;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename.endsWith(".ics") ? filename : `${filename}.ics`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}
