<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\DiscoveryFoundation\Actions\DiscoverPublicPagesAction as FoundationDiscoverPublicPagesAction;
use Capell\DiscoveryFoundation\Data\DiscoverablePageData as FoundationDiscoverablePageData;
use Capell\SiteDiscovery\Data\DiscoverablePageData;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Site Discovery's view of the pages Discovery Foundation publishes.
 *
 * Discovery Foundation owns the rules for which pages are public (published,
 * visible, indexable, readable by an anonymous visitor). This action only
 * adapts its result for the sitemap and discovery outputs, so the two
 * packages cannot drift.
 *
 * @method static Collection<int, DiscoverablePageData> run(Site $site, Language $language, ?SiteDomain $publishingDomain = null, ?array<int, int|string> $pageIds = null)
 */
final class DiscoverPublicPagesAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  SiteDomain|null  $publishingDomain  the domain the output is served from; every address is built, and judged for public readability, on it
     * @param  array<int, int>|null  $pageIds  restrict discovery to these pages
     * @return Collection<int, DiscoverablePageData>
     */
    public function handle(Site $site, Language $language, ?SiteDomain $publishingDomain = null, ?array $pageIds = null): Collection
    {
        return FoundationDiscoverPublicPagesAction::run($site, $language, $pageIds, $publishingDomain)
            ->map(static fn (FoundationDiscoverablePageData $page): DiscoverablePageData => new DiscoverablePageData(
                pageId: $page->pageId,
                title: $page->title,
                url: $page->url,
                lastModified: $page->lastModified,
                priority: $page->priority,
                changeFrequency: $page->changeFrequency,
                page: $page->page,
            ))
            ->values();
    }
}
