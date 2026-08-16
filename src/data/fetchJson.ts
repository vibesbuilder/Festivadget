// Basis-Pfad der Inhaltsdaten. Relativ zu BASE_URL, damit Subpfad-Deployments funktionieren.
const DATA_BASE = `${import.meta.env.BASE_URL}data`;

export function dataUrl(file: string): string {
  return `${DATA_BASE}/${file}`;
}

export async function fetchJson<T>(file: string, signal?: AbortSignal): Promise<T> {
  const res = await fetch(dataUrl(file), { signal });
  if (!res.ok) {
    throw new Error(`Konnte ${file} nicht laden (HTTP ${res.status})`);
  }
  return (await res.json()) as T;
}

// Wie fetchJson, aber liefert null wenn die Datei fehlt (404). So lässt sich
// „Datei nicht vorhanden" von „leer ([])" unterscheiden (z. B. admin-news.json).
export async function fetchJsonOrNull<T>(file: string, signal?: AbortSignal): Promise<T | null> {
  const res = await fetch(dataUrl(file), { signal });
  if (res.status === 404) return null;
  if (!res.ok) {
    throw new Error(`Konnte ${file} nicht laden (HTTP ${res.status})`);
  }
  return (await res.json()) as T;
}
