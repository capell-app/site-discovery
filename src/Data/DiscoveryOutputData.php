<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class DiscoveryOutputData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $url,
        public readonly string $contentType,
        public readonly ?string $label = null,
        public readonly ?string $description = null,
        public readonly ?CarbonInterface $lastModified = null,
    ) {}
}
