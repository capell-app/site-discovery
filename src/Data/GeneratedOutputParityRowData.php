<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Capell\SiteDiscovery\Enums\GeneratedOutputParityStatus;
use Capell\SiteDiscovery\Enums\PublicUrlContentType;
use Capell\SiteDiscovery\Enums\PublicUrlIndexability;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class GeneratedOutputParityRowData extends Data
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public readonly string $canonicalUrl,
        public readonly string $sourcePackage,
        public readonly int|string $siteKey,
        public readonly int|string $languageKey,
        public readonly ?int $siteId,
        public readonly ?int $languageId,
        public readonly ?string $languageCode,
        public readonly ?string $routeName,
        public readonly ?CarbonImmutable $lastModified,
        public readonly PublicUrlIndexability $indexability,
        public readonly PublicUrlContentType $contentType,
        public readonly bool $isSitemapEligible,
        public readonly bool $isAiDiscoveryEligible,
        public readonly GeneratedOutputParityStatus $sitemapStatus,
        public readonly GeneratedOutputParityStatus $aiDiscoveryStatus,
        public readonly GeneratedOutputParityStatus $searchStatus,
        public readonly GeneratedOutputParityStatus $htmlCacheStatus,
        public readonly GeneratedOutputParityStatus $agentDeliveryStatus,
        public readonly array $errors = [],
    ) {}

    public function hasMissingOutput(): bool
    {
        return in_array(GeneratedOutputParityStatus::Missing, [
            $this->sitemapStatus,
            $this->aiDiscoveryStatus,
            $this->searchStatus,
            $this->htmlCacheStatus,
            $this->agentDeliveryStatus,
        ], true);
    }
}
