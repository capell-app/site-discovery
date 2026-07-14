<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Listeners\Sitemap;

use Capell\Core\Events\CapellInstalled;
use Capell\Core\Facades\CapellCore;
use Capell\SiteDiscovery\Actions\EnsureSitemapPagesAction;
use Capell\SiteDiscovery\Providers\SiteDiscoveryServiceProvider;

final class EnsureSitemapPagesAfterCapellInstalled
{
    public function handle(CapellInstalled $event): void
    {
        if (! CapellCore::isPackageInstalled(SiteDiscoveryServiceProvider::$packageName)) {
            return;
        }

        EnsureSitemapPagesAction::run();
    }
}
