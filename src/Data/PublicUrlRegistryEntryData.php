<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Capell\SiteDiscovery\Enums\PublicUrlContentType;
use Capell\SiteDiscovery\Enums\PublicUrlIndexability;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class PublicUrlRegistryEntryData extends Data
{
    /**
     * @param  array<int, string>  $robotsDirectives
     */
    public function __construct(
        public readonly string $canonicalUrl,
        public readonly string $sourcePackage,
        public readonly int|string $siteKey,
        public readonly int|string $languageKey,
        public readonly ?int $siteId = null,
        public readonly ?int $languageId = null,
        public readonly ?string $languageCode = null,
        public readonly ?string $routeName = null,
        public readonly ?CarbonImmutable $lastModified = null,
        public readonly PublicUrlIndexability $indexability = PublicUrlIndexability::Indexable,
        public readonly array $robotsDirectives = [],
        public readonly PublicUrlContentType $contentType = PublicUrlContentType::Page,
        public readonly bool $isSitemapEligible = true,
        public readonly bool $isAiDiscoveryEligible = true,
        public readonly ?string $priority = null,
        public readonly ?string $changeFrequency = null,
    ) {}
}
