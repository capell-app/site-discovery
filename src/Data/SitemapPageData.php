<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Capell\Core\Actions\GetEditPageResourceUrlAction;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Models\Translation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class SitemapPageData extends Data
{
    /**
     * @param  list<SitemapAlternateData>  $alternates
     */
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
        public array $alternates = [],
    ) {
        $this->alternates = array_values(array_map(
            static fn (mixed $alternate): SitemapAlternateData => $alternate instanceof SitemapAlternateData
                ? $alternate
                : SitemapAlternateData::from($alternate),
            $this->alternates,
        ));
    }

    public static function fromPage(Pageable $page, bool $withEditUrl = false, bool $withAlternates = false): self
    {
        $translation = self::loadedRelation($page, 'translation');
        $children = self::loadedChildren($page);

        return new self(
            label: $translation instanceof Translation ? ($translation->label ?? $page->name) : $page->name,
            url: self::pageUrl($page),
            children: $page->hasPageHierarchy() && $children !== null
                ? $children
                    ->filter(fn (Page $child): bool => self::hasPersistedPageUrl($child))
                    ->map(fn (Page $child): SitemapPageData => self::fromPage($child, withEditUrl: $withEditUrl, withAlternates: $withAlternates))
                    ->values()
                : null,
            lastModified: self::resolveLastModified($page),
            changeFrequency: $page->meta['cache_time'] ?? 'always',
            priority: $page->meta['priority'] ?? 0.5,
            editUrl: $withEditUrl ? GetEditPageResourceUrlAction::run($page) : null,
            pageableType: $page->getMorphClass(),
            pageId: $page->id,
            alternates: $withAlternates ? self::alternates($page) : [],
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

    /**
     * @return list<SitemapAlternateData>
     */
    private static function alternates(Pageable $page): array
    {
        $pageUrls = self::requiredRelation($page, 'pageUrls');
        $site = self::requiredRelation($page, 'site');
        $translations = self::requiredRelation($page, 'translations');

        throw_unless($pageUrls instanceof Collection, RuntimeException::class, 'Sitemap alternates require the pageUrls relation to resolve to a collection.');
        throw_unless($site instanceof Site, RuntimeException::class, 'Sitemap alternates require the site relation to resolve to a site.');
        throw_unless($translations instanceof Collection, RuntimeException::class, 'Sitemap alternates require the translations relation to resolve to a collection.');

        $translatedLanguageIds = $translations
            ->filter(fn (mixed $translation): bool => $translation instanceof Translation)
            ->map(fn (Translation $translation): int => $translation->language_id)
            ->all();

        $eligible = $pageUrls
            ->filter(fn (mixed $pageUrl): bool => $pageUrl instanceof PageUrl
                && in_array($pageUrl->language_id, $translatedLanguageIds, true)
                && self::alternateLanguage($pageUrl) instanceof Language)
            ->values();

        if ($eligible->count() < 2) {
            return [];
        }

        // The filter above already proved every eligible URL resolves a Language,
        // so the null branch here would be unreachable rather than defensive.
        $alternates = $eligible
            ->map(static function (PageUrl $pageUrl): SitemapAlternateData {
                $language = self::alternateLanguage($pageUrl);

                throw_unless($language instanceof Language, RuntimeException::class, 'Sitemap alternates require an eligible page URL to resolve a language.');

                return new SitemapAlternateData(
                    hreflang: self::hreflang($language),
                    href: $pageUrl->full_url,
                );
            })
            ->sortBy(fn (SitemapAlternateData $alternate): string => $alternate->hreflang);

        $sorted = array_values($alternates->all());

        $default = $eligible->first(fn (PageUrl $pageUrl): bool => $pageUrl->language_id === $site->language_id);

        if ($default instanceof PageUrl) {
            $sorted[] = new SitemapAlternateData(hreflang: 'x-default', href: $default->full_url);
        }

        return $sorted;
    }

    /**
     * The eligible language for an alternate, or null when the URL is not eligible.
     */
    private static function alternateLanguage(PageUrl $pageUrl): ?Language
    {
        if ($pageUrl->type !== null || ! $pageUrl->status) {
            return null;
        }

        if (! self::requiredRelation($pageUrl, 'siteDomain') instanceof SiteDomain) {
            return null;
        }

        $language = self::requiredRelation($pageUrl, 'language');

        return $language instanceof Language ? $language : null;
    }

    private static function hreflang(Language $language): string
    {
        // locale is nullable; fall back to the code so a language configured
        // without one still emits a usable hreflang rather than an empty string.
        $locale = $language->locale ?: $language->code;

        return Str::of($locale)->lower()->replace('_', '-')->toString();
    }

    private static function requiredRelation(mixed $model, string $relation): mixed
    {
        throw_if(
            ! $model instanceof Model || ! $model->relationLoaded($relation),
            RuntimeException::class,
            sprintf('Sitemap alternates require the "%s" relation to be eager loaded.', $relation),
        );

        return $model->getRelation($relation);
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
