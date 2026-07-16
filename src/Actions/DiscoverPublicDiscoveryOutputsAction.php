<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Data\DiscoveryOutputData;
use Capell\SiteDiscovery\Support\DiscoveryOutputRegistry;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Collection<int, DiscoveryOutputData> run(Site $site, Language $language, ?SiteDomain $domain = null)
 */
final class DiscoverPublicDiscoveryOutputsAction
{
    use AsFake;
    use AsObject;

    public function __construct(private readonly DiscoveryOutputRegistry $registry) {}

    /**
     * @return Collection<int, DiscoveryOutputData>
     */
    public function handle(Site $site, Language $language, ?SiteDomain $domain = null): Collection
    {
        return $this->registry
            ->discover($site, $language, $domain)
            ->filter(fn (DiscoveryOutputData $output): bool => ! $domain instanceof SiteDomain || $this->belongsToDomain($output, $domain))
            ->unique(fn (DiscoveryOutputData $output): string => $output->key . '|' . $output->url)
            ->values();
    }

    private function belongsToDomain(DiscoveryOutputData $output, SiteDomain $domain): bool
    {
        $baseUrl = rtrim($domain->full_url, '/');

        return $output->url === $baseUrl || str_starts_with($output->url, $baseUrl . '/');
    }
}
