<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Jobs;

use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Actions\GenerateSitemapIncrementallyAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RegenerateSiteSitemapIncrementallyJob implements ShouldBeUnique, ShouldQueue
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
        $site = Site::query()->find($this->siteId);

        if (! $site instanceof Site) {
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
