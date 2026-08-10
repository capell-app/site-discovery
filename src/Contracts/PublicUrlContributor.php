<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Contracts;

interface PublicUrlContributor extends \Capell\DiscoveryFoundation\Contracts\PublicUrlContributor
{
    public const string TAG = \Capell\DiscoveryFoundation\Contracts\PublicUrlContributor::TAG;
}
