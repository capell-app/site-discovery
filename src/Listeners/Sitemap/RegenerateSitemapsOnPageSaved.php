<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Listeners\Sitemap;

use Capell\Core\Events\PageSaved;
use Capell\SiteDiscovery\Actions\NotifyPageUrlChangesAction;
use Capell\SiteDiscovery\Actions\RequestSiteSitemapRegenerationAction;
use Illuminate\Contracts\Queue\ShouldQueue;

class RegenerateSitemapsOnPageSaved implements ShouldQueue
{
    public function handle(PageSaved $event): void
    {
        $site = $event->page->site;

        if ($site === null) {
            return;
        }

        RequestSiteSitemapRegenerationAction::run($site);
        NotifyPageUrlChangesAction::run($event->page);
    }
}
