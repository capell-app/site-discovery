<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\PublicUrls;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\DiscoverPublicPagesAction;
use Capell\SiteDiscovery\Contracts\PublicUrlContributor;
use Capell\SiteDiscovery\Data\DiscoverablePageData;
use Capell\SiteDiscovery\Data\PublicUrlData;
use Capell\SiteDiscovery\Enums\PublicUrlContentType;
use Illuminate\Support\Collection;

final class CmsPagePublicUrlContributor implements PublicUrlContributor
{
    /**
     * @return Collection<int, PublicUrlData>
     */
    public function publicUrls(): Collection
    {
        return SiteDomain::query()
            ->with(['site', 'language'])
            ->get()
            ->filter(fn (SiteDomain $domain): bool => $domain->site instanceof Site && $domain->language instanceof Language)
            ->flatMap(fn (SiteDomain $domain): Collection => $this->publicUrlsForDomain($domain))
            ->unique(fn (PublicUrlData $url): string => $url->site->getKey() . '|' . $url->language->getKey() . '|' . $url->canonicalUrl)
            ->values();
    }

    /**
     * @return Collection<int, PublicUrlData>
     */
    private function publicUrlsForDomain(SiteDomain $domain): Collection
    {
        $site = $domain->site;
        $language = $domain->language;

        if (! $site instanceof Site || ! $language instanceof Language) {
            return collect();
        }

        return DiscoverPublicPagesAction::run($site, $language)
            ->map(fn (DiscoverablePageData $page): PublicUrlData => new PublicUrlData(
                canonicalUrl: $page->url,
                sourcePackage: 'capell-app/site-discovery',
                site: $site,
                language: $language,
                routeName: 'capell.pages.show',
                lastModified: $page->lastModified,
                contentType: PublicUrlContentType::Page,
                isSitemapEligible: true,
                isAiDiscoveryEligible: true,
                priority: $page->priority !== null ? number_format($page->priority, 1, '.', '') : null,
                changeFrequency: $page->changeFrequency,
            ));
    }
}
