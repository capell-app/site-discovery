<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Tests\Fixtures;

use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Override;

final class SiteDiscoverySitemapToolFakeXmlSitemapGenerator extends XmlSitemapGenerator
{
    /** @var list<int> */
    public array $deletedSiteIds = [];

    #[Override]
    public function delete(Site $site): void
    {
        $this->deletedSiteIds[] = (int) $site->getKey();
    }
}
