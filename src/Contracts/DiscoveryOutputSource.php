<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Contracts;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Data\DiscoveryOutputData;
use Illuminate\Support\Collection;

interface DiscoveryOutputSource
{
    public const string TAG = 'capell-site-discovery:discovery-output-sources';

    /**
     * @return Collection<int, DiscoveryOutputData>
     */
    public function discover(Site $site, Language $language, ?SiteDomain $domain = null): Collection;
}
