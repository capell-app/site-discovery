<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class DiscoverableUrlData extends Data
{
    public function __construct(
        public readonly string $loc,
        public readonly ?CarbonInterface $lastModified = null,
        public readonly ?string $changeFrequency = null,
        public readonly ?string $priority = null,
    ) {}
}
