<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class SitemapVideoData extends Data
{
    public function __construct(
        public readonly string $thumbnailLoc,
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $contentLoc = null,
        public readonly ?string $playerLoc = null,
        public readonly ?int $duration = null,
        #[WithCast(DateTimeInterfaceCast::class, DATE_ATOM)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, DATE_ATOM)]
        public readonly ?CarbonImmutable $publicationDate = null,
    ) {}
}
