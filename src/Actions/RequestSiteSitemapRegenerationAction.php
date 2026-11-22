<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Jobs\RegenerateSiteSitemapIncrementallyJob;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Queue\BackgroundQueue;
use Illuminate\Queue\DeferredQueue;
use Illuminate\Queue\SyncQueue;

use function Illuminate\Support\defer;

use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static bool run(Site $site)
 */
final class RequestSiteSitemapRegenerationAction
{
    use AsFake;
    use AsObject;

    /**
     * The debounce claim for a site's queued regeneration. The job releases it
     * when it starts, so a save made after that point queues a fresh run.
     */
    public static function pendingKey(int $siteId): string
    {
        return sprintf('capell-site-discovery:sitemap-regeneration:pending:%d', $siteId);
    }

    public function handle(Site $site): bool
    {
        $siteId = $this->siteId($site);

        // A site without a domain has no sitemap to publish. Its first domain requests the
        // regeneration (RegenerateSitemapsOnSiteDomainCreated); claiming the debounce here
        // would only queue a job that fails and hold back that request.
        if ($siteId <= 0 || ! $site->siteDomains()->exists()) {
            return false;
        }

        $delaySeconds = $this->delaySeconds();

        if (! Cache::add(self::pendingKey($siteId), true, $delaySeconds + $this->pendingGraceSeconds())) {
            return false;
        }

        // An inline queue ignores the delay and runs the job at once, and the job releases the claim
        // as it starts, so every save in a burst would regenerate the whole sitemap. Running it as the
        // request, command or queued job ends keeps the claim for the whole burst, which is the
        // debounce's promise. A deferred callback runs once and is then forgotten; an after-response
        // dispatch stays registered on the application and runs again whenever it terminates.
        if ($this->queueRunsJobsInline()) {
            defer(static function () use ($siteId): void {
                dispatch_sync(new RegenerateSiteSitemapIncrementallyJob($siteId));
            })->always();

            return true;
        }

        dispatch(new RegenerateSiteSitemapIncrementallyJob($siteId))
            ->delay(now()->addSeconds($delaySeconds));

        return true;
    }

    private function queueRunsJobsInline(): bool
    {
        $connection = resolve(QueueFactory::class)->connection();

        return $connection instanceof SyncQueue
            && ! $connection instanceof DeferredQueue
            && ! $connection instanceof BackgroundQueue;
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

    private function siteId(Site $site): int
    {
        $key = $site->getKey();

        return is_numeric($key) ? (int) $key : 0;
    }
}
