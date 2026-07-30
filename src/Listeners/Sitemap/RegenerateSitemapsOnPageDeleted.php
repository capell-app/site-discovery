<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Listeners\Sitemap;

use Capell\Core\Events\PageDeleted;
use Capell\SiteDiscovery\Actions\NotifyPageUrlChangesAction;
use Capell\SiteDiscovery\Actions\RequestSiteSitemapRegenerationAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegenerateSitemapsOnPageDeleted implements ShouldQueue
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function handle(PageDeleted $event): void
    {
        $site = $event->page->site;

        if ($site === null) {
            return;
        }

        RequestSiteSitemapRegenerationAction::run($site);
        NotifyPageUrlChangesAction::run($event->page);
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(PageDeleted $event): array
    {
        return [(new WithoutOverlapping($this->overlapKey($event->page->site_id)))->releaseAfter(10)->expireAfter(120)->shared()];
    }

    public function failed(PageDeleted $event, ?Throwable $exception): void
    {
        Log::error('Queued page deletion sitemap update failed permanently.', [
            'page_id' => $event->page->getKey(),
            'site_id' => $event->page->site_id,
            'exception_class' => $exception !== null ? $exception::class : null,
            'exception_code' => $exception?->getCode(),
        ]);
    }

    private function overlapKey(mixed $siteId): string
    {
        return is_int($siteId) || is_string($siteId)
            ? 'capell-site-discovery:sitemap-lifecycle:site:' . $siteId
            : 'capell-site-discovery:sitemap-lifecycle:' . self::class;
    }
}
