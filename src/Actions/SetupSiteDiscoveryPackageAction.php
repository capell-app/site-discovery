<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Contracts\PackageLifecycleAction;
use Capell\Core\Contracts\ProgressReporter;
use Capell\Core\Data\PackageData;
use Capell\Core\Support\Install\NullProgressReporter;
use Lorisleiva\Actions\Concerns\AsObject;

final class SetupSiteDiscoveryPackageAction implements PackageLifecycleAction
{
    use AsObject;

    public function handle(PackageData $package, array $arguments = [], ?ProgressReporter $reporter = null): void
    {
        $reporter ??= new NullProgressReporter;
        $siteCount = (new EnsureSitemapPagesAction)->handle();

        $reporter->report((string) __('capell-site-discovery::package.setup.completed', [
            'count' => $siteCount,
        ]));
    }
}
