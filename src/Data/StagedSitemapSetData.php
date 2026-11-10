<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Spatie\LaravelData\Data;

final class StagedSitemapSetData extends Data
{
    /**
     * @param  list<StagedSitemapDomainData>  $domains
     * @param  int|null  $publicReadValidUntil  the earliest scheduled public-read transition when generation began (a Unix timestamp); the set is stale from then on
     * @param  string|null  $publicReadRevision  the public-read eligibility revision current when generation began, or null when no package restricts public reads
     */
    public function __construct(
        public readonly string $siteKey,
        public readonly string $generationId,
        public readonly string $directory,
        public readonly array $domains,
        public readonly ?string $publicReadRevision = null,
        public readonly ?int $publicReadValidUntil = null,
    ) {}

    public function domain(string $domainKey): ?StagedSitemapDomainData
    {
        foreach ($this->domains as $domain) {
            if ($domain->domainKey === $domainKey) {
                return $domain;
            }
        }

        return null;
    }
}
