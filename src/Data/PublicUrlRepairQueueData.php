<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Spatie\LaravelData\Data;

final class PublicUrlRepairQueueData extends Data
{
    /**
     * @param  list<PublicUrlRepairItemData>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $totalUrls,
        public readonly int $needsAttentionUrls,
        public readonly int $unavailableUrls,
        public readonly int $healthyUrls,
    ) {}

    public function hasWork(): bool
    {
        return $this->needsAttentionUrls > 0;
    }
}
