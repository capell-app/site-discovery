<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Contracts\PackageLifecycleAction;
use Capell\Core\Contracts\ProgressReporter;
use Capell\Core\Data\PackageData;
use Capell\Core\Models\Site;
use Capell\Core\Support\Install\NullProgressReporter;
use Capell\SiteDiscovery\Support\Creator\SitemapPageCreator;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

final class SetupSiteDiscoveryPackageAction implements PackageLifecycleAction
{
    use AsObject;

    public function handle(PackageData $package, array $arguments = [], ?ProgressReporter $reporter = null): void
    {
        $reporter ??= new NullProgressReporter;
        $siteCount = 0;

        Site::query()->orderBy('id')->chunkById(
            100,
            function (Collection $sites) use (&$siteCount): void {
                $pageCreator = resolve(SitemapPageCreator::class);

                foreach ($sites as $site) {
                    $pageCreator->createSitemapPage($site, $site->languages()->get());
                    $siteCount++;
                }
            },
        );

        $reporter->report((string) __('capell-site-discovery::package.setup.completed', [
            'count' => $siteCount,
        ]));
    }
}
