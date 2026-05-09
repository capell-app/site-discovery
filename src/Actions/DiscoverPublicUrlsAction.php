<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Contracts\DiscoverableUrlSource;
use Capell\SiteDiscovery\Data\DiscoverablePageData;
use Capell\SiteDiscovery\Data\DiscoverableUrlData;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static Collection<int, DiscoverableUrlData> run(Site $site, Language $language)
 */
final class DiscoverPublicUrlsAction
{
    use AsAction;

    /**
     * @return Collection<int, DiscoverableUrlData>
     */
    public function handle(Site $site, Language $language): Collection
    {
        $pageUrls = DiscoverPublicPagesAction::run($site, $language)
            ->map(fn (DiscoverablePageData $page): DiscoverableUrlData => new DiscoverableUrlData(
                loc: $page->url,
                lastModified: $page->lastModified,
                changeFrequency: $page->changeFrequency,
                priority: $page->priority !== null ? number_format($page->priority, 1, '.', '') : null,
            ));

        $contributedUrls = collect(app()->tagged('capell-site-discovery:discoverable-url-sources'))
            ->filter(fn (mixed $source): bool => $source instanceof DiscoverableUrlSource)
            ->flatMap(fn (DiscoverableUrlSource $source): Collection => $source->discover($site, $language));

        return $pageUrls
            ->merge($contributedUrls)
            ->unique(fn (DiscoverableUrlData $url): string => $url->loc)
            ->values();
    }
}
