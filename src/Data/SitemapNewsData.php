<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class SitemapNewsData extends Data
{
    public function __construct(
        public readonly string $publicationName,
        public readonly string $publicationLanguage,
        public readonly string $title,
        #[WithCast(DateTimeInterfaceCast::class, DATE_ATOM)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, DATE_ATOM)]
        public readonly CarbonImmutable $publicationDate,
    ) {}
}
