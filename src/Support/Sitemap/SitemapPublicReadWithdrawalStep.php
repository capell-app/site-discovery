<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\Sitemap;

use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Actions\RequestSiteSitemapRegenerationAction;

/**
 * Published sitemaps stop being served once eligibility changes (their
 * recorded revision no longer matches), so every affected site is regenerated
 * under the new rules. A failure leaves the step owed, and Core retries it.
 */
final class SitemapPublicReadWithdrawalStep
{
    public const string KEY = 'site-discovery.sitemaps';

    public function __invoke(?int $siteId): void
    {
        Site::query()
            ->enabled()
            ->when($siteId !== null, static fn ($query) => $query->whereKey($siteId))
            ->lazyById()
            ->each(static fn (Site $site): mixed => RequestSiteSitemapRegenerationAction::run($site));
    }
}
