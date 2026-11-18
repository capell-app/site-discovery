<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Jobs;

use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Actions\GenerateSitemapIncrementallyAction;
use Capell\SiteDiscovery\Actions\RequestSiteSitemapRegenerationAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class RegenerateSiteSitemapIncrementallyJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $uniqueFor;

    public function __construct(
        public readonly int $siteId,
    ) {
        $this->uniqueFor = $this->uniqueSeconds();
    }

    public function handle(): void
    {
        // The debounce claim ends as the run starts: a page saved while it runs, or in the grace window after
        // it, must queue another run rather than be swallowed by a claim that has already done its job.
        Cache::forget(RequestSiteSitemapRegenerationAction::pendingKey($this->siteId));

        $site = Site::query()->find($this->siteId);

        // The site may have lost its last domain since the request: there is nothing to
        // publish, and a new domain requests a fresh regeneration.
        if (! $site instanceof Site || ! $site->siteDomains()->exists()) {
            return;
        }

        GenerateSitemapIncrementallyAction::run($site);
    }

    public function uniqueId(): string
    {
        return 'capell-site-discovery:sitemap-regeneration:' . $this->siteId;
    }

    private function uniqueSeconds(): int
    {
        $seconds = config('capell-site-discovery.event_sitemap_regeneration.unique_for_seconds', 900);

        return is_numeric($seconds) ? max(60, (int) $seconds) : 900;
    }
}
