<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Contracts;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Data\DiscoverableUrlData;
use Illuminate\Support\Collection;

interface DiscoverableUrlSource
{
    /**
     * @return Collection<int, DiscoverableUrlData>
     */
    public function discover(Site $site, Language $language): Collection;
}
