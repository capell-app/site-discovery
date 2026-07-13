<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Capell\Core\Actions\GetEditPageResourceUrlAction;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Translation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class SitemapPageData extends Data
{
    public function __construct(
        public string $label,
        public string $url,
        /** @var Collection<int, SitemapPageData> */
        public ?Collection $children = null,
        #[WithCast(DateTimeInterfaceCast::class, DATE_ATOM)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, DATE_ATOM)]
        public ?CarbonImmutable $lastModified = null,
        public ?string $changeFrequency = null,
        public ?float $priority = 0.5,
        public ?string $editUrl = null,
        #[MapInputName('pageable_type')]
        public ?string $pageableType = null,
        #[MapInputName('pageable_id')]
        public ?int $pageId = null,
    ) {}

    public static function fromPage(Pageable $page, bool $withEditUrl = false): self
    {
        $translation = self::loadedRelation($page, 'translation');
        $children = self::loadedChildren($page);

        return new self(
            label: $translation instanceof Translation ? ($translation->label ?? $page->name) : $page->name,
            url: self::pageUrl($page),
            children: $page->hasPageHierarchy() && $children !== null
                ? $children
                    ->filter(fn (Page $child): bool => self::hasPersistedPageUrl($child))
                    ->map(fn (Page $child): SitemapPageData => self::fromPage($child, withEditUrl: $withEditUrl))
                    ->values()
                : null,
            lastModified: self::resolveLastModified($page),
            changeFrequency: $page->meta['cache_time'] ?? 'always',
            priority: $page->meta['priority'] ?? 0.5,
            editUrl: $withEditUrl ? GetEditPageResourceUrlAction::run($page) : null,
            pageableType: $page->getMorphClass(),
            pageId: $page->id,
        );
    }

    public static function resolveLastModified(Pageable $page): CarbonImmutable
    {
        $lastModified = collect([
            $page->published_at ?? null,
            $page->visible_from ?? null,
            $page->updated_at ?? null,
            $page->created_at ?? null,
        ])
            ->filter()
            ->map(fn (mixed $date): ?CarbonImmutable => CarbonImmutable::make($date))
            ->filter(fn (?CarbonImmutable $date): bool => $date instanceof CarbonImmutable)
            ->sort()
            ->last();

        return $lastModified instanceof CarbonImmutable ? $lastModified : CarbonImmutable::now();
    }

    public static function hasPersistedPageUrl(Pageable $page): bool
    {
        return self::isPersistedPageUrl(self::loadedRelation($page, 'pageUrl'));
    }

    private static function pageUrl(Pageable $page): string
    {
        $pageUrl = self::loadedRelation($page, 'pageUrl');

        throw_if(! $pageUrl instanceof PageUrl || ! $pageUrl->exists, RuntimeException::class, 'Sitemap page requires a persisted page URL.');

        return $pageUrl->full_url;
    }

    private static function isPersistedPageUrl(mixed $pageUrl): bool
    {
        return $pageUrl instanceof PageUrl && $pageUrl->exists;
    }

    private static function loadedRelation(Pageable $page, string $relation): mixed
    {
        return $page instanceof Model && $page->relationLoaded($relation)
            ? $page->getRelation($relation)
            : null;
    }

    /**
     * @return Collection<int, Page>|null
     */
    private static function loadedChildren(Pageable $page): ?Collection
    {
        if (! $page instanceof Model || ! $page->relationLoaded('children')) {
            return null;
        }

        $children = $page->getRelation('children');

        return $children instanceof Collection ? $children : null;
    }
}
