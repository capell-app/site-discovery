<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Spatie\LaravelData\Data;

final class SitemapAlternateData extends Data
{
    public function __construct(
        public readonly string $hreflang,
        public readonly string $href,
    ) {}
}
