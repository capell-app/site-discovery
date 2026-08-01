<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * @method static void run(Site $site)
 */
final class GenerateSitemapIncrementallyAction
{
    use AsFake;
    use AsObject;

    public function handle(Site $site): void
    {
        try {
            Cache::lock($this->lockKey($site), 900)
                ->block($this->lockWaitSeconds(), function () use ($site): void {
                    resolve(XmlSitemapGenerator::class)->processIncremental($site);
                });
        } catch (LockTimeoutException $lockTimeoutException) {
            throw new RuntimeException(
                'Sitemap generation is already running for this site.',
                $lockTimeoutException->getCode(),
                previous: $lockTimeoutException,
            );
        }
    }

    private function lockKey(Site $site): string
    {
        $siteKey = $site->getKey();
        throw_unless(is_int($siteKey) || is_string($siteKey), RuntimeException::class, 'Site must have a scalar key.');

        return 'capell-site-discovery:sitemap:' . (is_int($siteKey) ? (string) $siteKey : $siteKey);
    }

    private function lockWaitSeconds(): int
    {
        $seconds = config('capell.sitemap.lock_wait_seconds', 10);

        return is_numeric($seconds) ? max(0, (int) $seconds) : 10;
    }
}
