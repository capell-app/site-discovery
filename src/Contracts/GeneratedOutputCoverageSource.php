<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Contracts;

use Capell\SiteDiscovery\Data\PublicUrlRegistryEntryData;
use Illuminate\Support\Collection;

interface GeneratedOutputCoverageSource
{
    public const string TAG = 'capell-site-discovery:generated-output-coverage-sources';

    public const string AI_DISCOVERY = 'ai_discovery';

    public const string SEARCH = 'search';

    public const string HTML_CACHE = 'html_cache';

    public const string AGENT_DELIVERY = 'agent_delivery';

    public function key(): string;

    /**
     * @param  Collection<int, PublicUrlRegistryEntryData>  $registryEntries
     * @return Collection<int, string>
     */
    public function coveredUrls(Collection $registryEntries): Collection;
}
