<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Jobs;

use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Actions\GenerateSitemapAction;
use Capell\SiteDiscovery\Enums\SitemapCacheKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RebuildSiteSitemapJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120];

    public function __construct(public readonly int $siteId) {}

    public function handle(): void
    {
        $site = Site::query()->with('siteDomains')->enabled()->find($this->siteId);

        if (! $site instanceof Site) {
            $this->decrementProgress();

            return;
        }

        GenerateSitemapAction::dispatch($site);
    }

    public function uniqueId(): string
    {
        return (string) $this->siteId;
    }

    public function failed(?Throwable $throwable): void
    {
        $this->decrementProgress();

        Log::error('Site Discovery site sitemap rebuild failed.', [
            'exception' => $throwable,
            'site_id' => $this->siteId,
        ]);
    }

    private function decrementProgress(): void
    {
        $cachedRemaining = Cache::get(SitemapCacheKey::Generating->value, 0);
        $remaining = (is_int($cachedRemaining) ? $cachedRemaining : 0) - 1;

        if ($remaining <= 0) {
            Cache::forget(SitemapCacheKey::Generating->value);

            return;
        }

        Cache::decrement(SitemapCacheKey::Generating->value);
    }
}
