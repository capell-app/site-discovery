<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Capell\Core\Models\Page;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class DiscoverablePageData extends Data
{
    public function __construct(
        public readonly int $pageId,
        public readonly string $title,
        public readonly string $url,
        public readonly ?CarbonInterface $lastModified = null,
        public readonly ?float $priority = null,
        public readonly ?string $changeFrequency = null,
        public readonly ?Page $page = null,
    ) {}
}
