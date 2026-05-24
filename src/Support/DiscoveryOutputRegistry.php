<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Contracts\DiscoveryOutputSource;
use Capell\SiteDiscovery\Data\DiscoveryOutputData;
use Illuminate\Support\Collection;

final class DiscoveryOutputRegistry
{
    /** @var list<DiscoveryOutputSource> */
    private array $sources = [];

    public function register(DiscoveryOutputSource $source): self
    {
        $this->sources[] = $source;

        return $this;
    }

    /**
     * @return Collection<int, DiscoveryOutputData>
     */
    public function discover(Site $site, Language $language, ?SiteDomain $domain = null): Collection
    {
        return collect($this->sources)
            ->flatMap(fn (DiscoveryOutputSource $source): Collection => $source->discover($site, $language, $domain))
            ->filter(fn (DiscoveryOutputData $output): bool => $this->isPublicHttpOutput($output))
            ->values();
    }

    private function isPublicHttpOutput(DiscoveryOutputData $output): bool
    {
        return $output->key !== ''
            && (str_starts_with($output->url, 'https://') || str_starts_with($output->url, 'http://'))
            && $output->contentType !== '';
    }
}
