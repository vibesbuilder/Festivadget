import type { ContentSourcesConfig, SourceBinding } from "../../content-sources.config";

// Gemeinsames Adapter-Interface (§6.2). Jeder Adapter liefert bereits auf das
// Schema (§7) gemappte Datensätze als unknown[] (Validierung in build-data).
export interface SourceAdapter {
  fetchDomain(
    domain: string,
    binding: SourceBinding,
    cfg: ContentSourcesConfig,
  ): Promise<unknown[]>;
}

export type { ContentSourcesConfig, SourceBinding };
