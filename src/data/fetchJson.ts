// Base path of the content data. Relative to BASE_URL so subpath deployments work.
const DATA_BASE = `${import.meta.env.BASE_URL}data`;

export function dataUrl(file: string): string {
  return `${DATA_BASE}/${file}`;
}

export async function fetchJson<T>(file: string, signal?: AbortSignal): Promise<T> {
  const res = await fetch(dataUrl(file), { signal });
  if (!res.ok) {
    throw new Error(`Failed to load ${file} (HTTP ${res.status})`);
  }
  return (await res.json()) as T;
}

// Like fetchJson, but returns null when the file is missing (404). This tells
// "file absent" apart from "empty ([])" (e.g. admin-news.json).
export async function fetchJsonOrNull<T>(file: string, signal?: AbortSignal): Promise<T | null> {
  const res = await fetch(dataUrl(file), { signal });
  if (res.status === 404) return null;
  if (!res.ok) {
    throw new Error(`Failed to load ${file} (HTTP ${res.status})`);
  }
  return (await res.json()) as T;
}
