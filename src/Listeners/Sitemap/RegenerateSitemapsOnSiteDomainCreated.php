<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Listeners\Sitemap;

use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\RequestSiteSitemapRegenerationAction;

/**
 * A site created before any domain exists has no sitemap to publish, so its
 * creation listener skips it. Each new domain adds a sitemap context, so
 * request a (debounced, queued) regeneration when one is attached.
 */
class RegenerateSitemapsOnSiteDomainCreated
{
    public function handle(SiteDomain $siteDomain): void
    {
        $site = $siteDomain->site;

        if ($site === null) {
            return;
        }

        RequestSiteSitemapRegenerationAction::run($site);
    }
}
