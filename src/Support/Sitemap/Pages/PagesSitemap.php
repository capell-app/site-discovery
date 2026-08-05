<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\Sitemap\Pages;

use Aimeos\Nestedset\Collection as NestedsetCollection;
use Capell\Core\Enums\CacheEnum;
use Capell\Core\Models\Page;
use Capell\SiteDiscovery\Actions\DiscoverPublicPagesAction;
use Capell\SiteDiscovery\Data\DiscoverablePageData;
use Capell\SiteDiscovery\Data\SitemapPageData;
use Capell\SiteDiscovery\Support\Sitemap\AbstractSitemapPages;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use LogicException;

class PagesSitemap extends AbstractSitemapPages
{
    private const string PAYLOAD_VERSION = 'v2';

    /**
     * @return list<string>
     */
    public static function payloadCacheKeys(int $siteId, int $languageId): array
    {
        return [
            self::payloadCacheKey($siteId, $languageId),
            self::payloadCacheKey($siteId, $languageId, withEditUrl: true),
        ];
    }

    public static function payloadCacheKey(int $siteId, int $languageId, bool $withEditUrl = false): string
    {
        return CacheEnum::sitemapPages($siteId, $languageId)
            . '.' . self::PAYLOAD_VERSION
            . ($withEditUrl ? '.with-edit-urls' : '.public');
    }

    /**
     * @return Collection<array-key, mixed>
     */
    public function fetch(): Collection
    {
        throw_if($this->site->id === null, LogicException::class, 'Site ID is null in DefaultPages::fetch(). Ensure the Site model is persisted and loaded.');

        throw_if($this->language->id === null, LogicException::class, 'Language ID is null in DefaultPages::fetch(). Ensure the Language model is persisted and loaded.');

        $cacheKey = $this->cacheKey($this->site->id, $this->language->id);
        $payload = Cache::get($cacheKey);

        if (! is_array($payload)) {
            $payload = $this->uncachedPages()
                ->map(fn (SitemapPageData $page): array => $page->toArray())
                ->all();

            Cache::put($cacheKey, $payload, 3600);
        }

        return collect($payload)
            ->filter(fn (mixed $page): bool => is_array($page))
            ->map(fn (array $page): SitemapPageData => SitemapPageData::from($page));
    }

    public function format(Page $page): SitemapPageData
    {
        return SitemapPageData::fromPage($page, withEditUrl: $this->withEditUrl, withAlternates: true);
    }

    /**
     * @return Collection<int, SitemapPageData>
     */
    private function uncachedPages(): Collection
    {
        return DiscoverPublicPagesAction::run($this->site, $this->language)
            ->map(fn (DiscoverablePageData $data): ?Page => $data->page)
            ->filter(fn (?Page $page): bool => $page instanceof Page)
            ->pipe(fn (Collection $pages): NestedsetCollection => new NestedsetCollection($pages->all()))
            ->pipe(fn (NestedsetCollection $pages): Collection => collect($pages->toTree()))
            ->filter(fn (Page $page): bool => SitemapPageData::hasPersistedPageUrl($page))
            ->map(fn (Page $page): SitemapPageData => $this->format($page));
    }

    private function cacheKey(int $siteId, int $languageId): string
    {
        return self::payloadCacheKey($siteId, $languageId, $this->withEditUrl);
    }
}
