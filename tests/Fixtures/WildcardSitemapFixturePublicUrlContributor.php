<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Tests\Fixtures;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\DiscoveryFoundation\Contracts\PublicUrlContributor;
use Capell\DiscoveryFoundation\Data\PublicUrlData;
use Illuminate\Support\Collection;

final readonly class WildcardSitemapFixturePublicUrlContributor implements PublicUrlContributor
{
    public function __construct(
        private Site $site,
        private Language $language,
        private string $baseUrl,
    ) {}

    /**
     * @return Collection<int, PublicUrlData>
     */
    public function publicUrls(): Collection
    {
        return collect([
            new PublicUrlData(
                canonicalUrl: $this->baseUrl . '/blog/tags/*',
                sourcePackage: 'capell-app/site-discovery-test',
                site: $this->site,
                language: $this->language,
            ),
            new PublicUrlData(
                canonicalUrl: $this->baseUrl . '/blog/archives/*',
                sourcePackage: 'capell-app/site-discovery-test',
                site: $this->site,
                language: $this->language,
            ),
            new PublicUrlData(
                canonicalUrl: $this->baseUrl . '/blog',
                sourcePackage: 'capell-app/site-discovery-test',
                site: $this->site,
                language: $this->language,
            ),
        ]);
    }
}
