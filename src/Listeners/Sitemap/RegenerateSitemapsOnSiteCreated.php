<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Listeners\Sitemap;

use Capell\Core\Events\SiteCreated;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegenerateSitemapsOnSiteCreated implements ShouldQueue
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(private readonly XmlSitemapGenerator $generator) {}

    public function handle(SiteCreated $event): void
    {
        $this->generator->processIncremental($event->site);
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(SiteCreated $event): array
    {
        $siteId = $event->site->getKey();
        $overlapKey = is_int($siteId) || is_string($siteId)
            ? 'capell-site-discovery:sitemap-lifecycle:site:' . $siteId
            : 'capell-site-discovery:sitemap-lifecycle:' . self::class;

        return [(new WithoutOverlapping($overlapKey))->releaseAfter(10)->expireAfter(120)->shared()];
    }

    public function failed(SiteCreated $event, ?Throwable $exception): void
    {
        Log::error('Queued site creation sitemap update failed permanently.', [
            'site_id' => $event->site->getKey(),
            'exception_class' => $exception !== null ? $exception::class : null,
            'exception_code' => $exception?->getCode(),
        ]);
    }
}
