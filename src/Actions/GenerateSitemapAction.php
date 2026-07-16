<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Models;
use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Enums\SitemapCacheKey;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsJob;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * @method static string run(Models\Site $site)
 */
class GenerateSitemapAction
{
    use AsFake;
    use AsJob;
    use AsObject;

    protected string $cacheKey = SitemapCacheKey::Generating->value;

    public function handle(Site $site): string
    {
        try {
            return Cache::lock($this->lockKey($site), 900)
                ->block($this->lockWaitSeconds(), fn (): string => $this->generate($site));
        } catch (LockTimeoutException $lockTimeoutException) {
            throw new Exception('Sitemap generation is already running for this site.', $lockTimeoutException->getCode(), previous: $lockTimeoutException);
        } catch (Throwable $throwable) {
            Log::warning('Site Discovery sitemap generation failed.', [
                'exception' => $throwable,
                'site_id' => $site->getKey(),
            ]);

            throw new Exception('Failed to generate sitemap', $throwable->getCode(), previous: $throwable);
        } finally {
            $this->updateCache();
        }
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function getJobMiddleware(Site $site): array
    {
        return [
            (new WithoutOverlapping($this->lockKey($site)))->expireAfter(900),
        ];
    }

    private function generate(Site $site): string
    {
        $xml = resolve(XmlSitemapGenerator::class)->generate($site);

        Cache::forget(SitemapCacheKey::Sitemaps->value);

        return $xml;
    }

    private function updateCache(): void
    {
        $remaining = Cache::get($this->cacheKey, 0) - 1;

        if ($remaining <= 0) {
            Cache::forget($this->cacheKey);
        } else {
            Cache::decrement($this->cacheKey);
        }
    }

    private function lockKey(Site $site): string
    {
        return sprintf('capell-site-discovery:sitemap:%d', $site->getKey());
    }

    private function lockWaitSeconds(): int
    {
        $seconds = config('capell.sitemap.lock_wait_seconds', 10);

        return is_numeric($seconds) ? max(0, (int) $seconds) : 10;
    }
}
