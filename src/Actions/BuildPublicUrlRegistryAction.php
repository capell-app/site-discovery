<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\DiscoveryFoundation\Actions\BuildPublicUrlRegistryAction as FoundationBuildPublicUrlRegistryAction;
use Capell\DiscoveryFoundation\Data\PublicUrlRegistryEntryData;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Collection<int, \Capell\DiscoveryFoundation\Data\PublicUrlRegistryEntryData> run()
 */
final class BuildPublicUrlRegistryAction
{
    use AsFake;
    use AsObject;

    /** @return Collection<int, PublicUrlRegistryEntryData> */
    public function handle(): Collection
    {
        return FoundationBuildPublicUrlRegistryAction::run();
    }
}
