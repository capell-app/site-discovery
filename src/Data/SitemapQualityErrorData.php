<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Capell\SiteDiscovery\Enums\SitemapQualityError;
use Spatie\LaravelData\Data;

final class SitemapQualityErrorData extends Data
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly SitemapQualityError $code,
        public readonly ?string $url,
        public readonly string $message,
        public readonly array $context = [],
    ) {}
}
