<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Spatie\LaravelData\Data;

final class StagedSitemapSetData extends Data
{
    /**
     * @param  list<StagedSitemapDomainData>  $domains
     */
    public function __construct(
        public readonly string $siteKey,
        public readonly string $generationId,
        public readonly string $directory,
        public readonly array $domains,
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
