import type { ContentSourcesConfig, SourceBinding } from "../../content-sources.config";

// Shared adapter interface (§6.2). Every adapter delivers records already mapped
// to the schema (§7) as unknown[] (validation in build-data).
export interface SourceAdapter {
  fetchDomain(
    domain: string,
    binding: SourceBinding,
    cfg: ContentSourcesConfig,
  ): Promise<unknown[]>;
}

export type { ContentSourcesConfig, SourceBinding };
