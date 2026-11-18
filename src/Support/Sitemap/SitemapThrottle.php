<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\Sitemap;

use Capell\Core\Support\Http\PublicEndpointThrottle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sitemaps are public and a cache miss reads the whole file, so crawlers and scrapers share a
 * per-address budget, as declared in capell.json `security.throttles`. The limit is read from
 * configuration each time a request is throttled.
 */
final class SitemapThrottle
{
    public const string PURPOSE = 'capell-site-discovery-sitemap';

    public static function register(): void
    {
        RateLimiter::for(self::PURPOSE, static fn (Request $request): array => self::throttle()->limitsFor($request));
    }

    public static function throttle(): PublicEndpointThrottle
    {
        $configured = config('capell-site-discovery.sitemap_rate_limit.per_minute', 60);

        return PublicEndpointThrottle::for(self::PURPOSE)
            ->allow(is_numeric($configured) ? max(1, (int) $configured) : 60)
            ->perSeconds(60)
            ->ignoringActor();
    }
}
