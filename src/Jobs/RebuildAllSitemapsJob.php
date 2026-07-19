<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Jobs;

use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Enums\SitemapCacheKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class RebuildAllSitemapsJob implements ShouldBeUnique, ShouldQueue
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

    public function handle(): void
    {
        $siteQuery = Site::query()->enabled()->ordered();
        $siteCount = $siteQuery->count();

        if ($siteCount === 0) {
            Cache::forget(SitemapCacheKey::Generating->value);

            return;
        }

        Cache::put(SitemapCacheKey::Generating->value, $siteCount, now()->addMinutes(60));

        $siteQuery->lazyById()->each(function (Site $site): void {
            $siteId = $site->getKey();
            throw_unless(is_int($siteId), RuntimeException::class, 'Site must have an integer key.');
            RebuildSiteSitemapJob::dispatch($siteId);
        });
    }

    public function failed(?Throwable $throwable): void
    {
        Cache::forget(SitemapCacheKey::Generating->value);

        Log::error('Site Discovery sitemap rebuild could not be queued.', [
            'exception' => $throwable,
        ]);
    }
}
