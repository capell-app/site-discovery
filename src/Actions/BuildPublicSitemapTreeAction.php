<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Data\SitemapPageData;
use Capell\SiteDiscovery\Support\Sitemap\SitemapBuilder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use UnexpectedValueException;

/**
 * @method static Collection<int, SitemapPageData> run(Site $site, SiteDomain $domain, Language $language)
 */
final class BuildPublicSitemapTreeAction
{
    use AsAction;

    /**
     * @return Collection<int, SitemapPageData>
     */
    public function handle(Site $site, SiteDomain $domain, Language $language): Collection
    {
        return (new SitemapBuilder(
            site: $site,
            domain: $domain,
            language: $language,
            withEditUrl: false,
        ))
            ->build()
            ->map(fn (mixed $page): SitemapPageData => $this->publicNodeFromMixed($page))
            ->values();
    }

    private function publicNodeFromMixed(mixed $page): SitemapPageData
    {
        if (! $page instanceof SitemapPageData) {
            throw new UnexpectedValueException('Sitemap builder returned an invalid page node.');
        }

        return $this->publicNode($page);
    }

    private function publicNode(SitemapPageData $page): SitemapPageData
    {
        return new SitemapPageData(
            label: $page->label,
            url: $page->url,
            children: $page->children
                ?->map(fn (mixed $child): SitemapPageData => $this->publicNodeFromMixed($child))
                ->values(),
            lastModified: null,
            changeFrequency: null,
            priority: null,
            editUrl: null,
            pageableType: null,
            pageId: null,
        );
    }
}
