<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Jobs\RegenerateSiteSitemapIncrementallyJob;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static bool run(Site $site)
 */
final class RequestSiteSitemapRegenerationAction
{
    use AsObject;

    public function handle(Site $site): bool
    {
        $siteId = (int) $site->getKey();

        if ($siteId <= 0) {
            return false;
        }

        $delaySeconds = $this->delaySeconds();

        if (! Cache::add($this->pendingKey($siteId), true, $delaySeconds + $this->pendingGraceSeconds())) {
            return false;
        }

        RegenerateSiteSitemapIncrementallyJob::dispatch($siteId)
            ->delay(now()->addSeconds($delaySeconds));

        return true;
    }

    private function pendingKey(int $siteId): string
    {
        return sprintf('capell-site-discovery:sitemap-regeneration:pending:%d', $siteId);
    }

    private function delaySeconds(): int
    {
        $seconds = config('capell-site-discovery.event_sitemap_regeneration.debounce_seconds', 60);

        return is_numeric($seconds) ? max(0, (int) $seconds) : 60;
    }

    private function pendingGraceSeconds(): int
    {
        $seconds = config('capell-site-discovery.event_sitemap_regeneration.pending_grace_seconds', 30);

        return is_numeric($seconds) ? max(1, (int) $seconds) : 30;
    }
}
