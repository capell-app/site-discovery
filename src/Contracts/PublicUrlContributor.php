<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Contracts;

use Capell\SiteDiscovery\Data\PublicUrlData;
use Illuminate\Support\Collection;

interface PublicUrlContributor
{
    public const string TAG = 'capell-site-discovery:public-url-contributors';

    /**
     * @return Collection<int, PublicUrlData>
     */
    public function publicUrls(): Collection;
}
