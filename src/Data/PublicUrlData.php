<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Enums\PublicUrlContentType;
use Capell\SiteDiscovery\Enums\PublicUrlIndexability;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class PublicUrlData extends Data
{
    /**
     * @param  array<array-key, mixed>  $robotsDirectives
     */
    public function __construct(
        public readonly string $canonicalUrl,
        public readonly string $sourcePackage,
        public readonly Site $site,
        public readonly Language $language,
        public readonly ?string $routeName = null,
        public readonly ?CarbonInterface $lastModified = null,
        public readonly PublicUrlIndexability $indexability = PublicUrlIndexability::Indexable,
        public readonly array $robotsDirectives = [],
        public readonly PublicUrlContentType $contentType = PublicUrlContentType::Page,
        public readonly bool $isSitemapEligible = true,
        public readonly bool $isAiDiscoveryEligible = true,
        public readonly ?string $priority = null,
        public readonly ?string $changeFrequency = null,
    ) {}
}
