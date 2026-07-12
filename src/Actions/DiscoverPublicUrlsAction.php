<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Contracts\DiscoverableUrlSource;
use Capell\SiteDiscovery\Data\DiscoverablePageData;
use Capell\SiteDiscovery\Data\DiscoverableUrlData;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static Collection<int, DiscoverableUrlData> run(Site $site, Language $language, bool $includePages = true, ?SiteDomain $domain = null)
 */
final class DiscoverPublicUrlsAction
{
    use AsAction;

    /**
     * @return Collection<int, DiscoverableUrlData>
     */
    public function handle(Site $site, Language $language, bool $includePages = true, ?SiteDomain $domain = null): Collection
    {
        $pageUrls = $includePages
            ? DiscoverPublicPagesAction::run($site, $language)
                ->map(fn (DiscoverablePageData $page): DiscoverableUrlData => new DiscoverableUrlData(
                    loc: $page->url,
                    lastModified: $page->lastModified,
                    changeFrequency: $page->changeFrequency,
                    priority: $page->priority !== null ? number_format($page->priority, 1, '.', '') : null,
                ))
            : collect();

        $contributedUrls = collect(app()->tagged('capell-site-discovery:discoverable-url-sources'))
            ->filter(fn (mixed $source): bool => $source instanceof DiscoverableUrlSource)
            ->flatMap(fn (DiscoverableUrlSource $source): Collection => $source->discover($site, $language, $domain))
            ->filter(fn (DiscoverableUrlData $url): bool => ! $domain instanceof SiteDomain || $this->belongsToDomain($url, $domain));

        return $pageUrls
            ->merge($contributedUrls)
            ->unique(fn (DiscoverableUrlData $url): string => $url->loc)
            ->values();
    }

    private function belongsToDomain(DiscoverableUrlData $url, SiteDomain $domain): bool
    {
        $baseUrl = rtrim($domain->full_url, '/');

        return $url->loc === $baseUrl || str_starts_with($url->loc, $baseUrl . '/');
    }
}
