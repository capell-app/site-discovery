<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Spatie\LaravelData\Data;

final class StagedSitemapDomainData extends Data
{
    /**
     * @param  list<string>  $filenames
     * @param  array<string, string>  $urlState
     */
    public function __construct(
        public readonly string $domainKey,
        public readonly int $languageId,
        public readonly string $mainFilename,
        public readonly array $filenames,
        public readonly int $urlCount,
        public readonly array $urlState,
        public readonly string $etag,
        public readonly string $publicChunkBaseUrl,
        public readonly bool $regenerated = true,
    ) {}
}
