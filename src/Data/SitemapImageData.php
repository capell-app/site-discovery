<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Spatie\LaravelData\Data;

final class SitemapImageData extends Data
{
    public function __construct(
        public readonly string $loc,
        public readonly ?string $caption = null,
        public readonly ?string $title = null,
        public readonly ?string $license = null,
    ) {}
}
