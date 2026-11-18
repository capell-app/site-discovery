<?php

declare(strict_types=1);

use Capell\SiteDiscovery\Http\Controllers\IndexNowKeyFileController;
use Capell\SiteDiscovery\Http\Controllers\LegacySitemapXmlRedirectController;
use Capell\SiteDiscovery\Http\Controllers\SitemapXmlController;
use Capell\SiteDiscovery\Support\Sitemap\SitemapThrottle;
use Capell\SiteDiscovery\Support\Sitemap\SitemapXmlPath;
use Illuminate\Support\Facades\Route;

$xmlPath = SitemapXmlPath::routeUri();
$legacyPath = ltrim(SitemapXmlPath::LEGACY, '/');

Route::name('capell-frontend.')
    ->middleware(['web', 'throttle:' . SitemapThrottle::PURPOSE])
    ->group(function () use ($xmlPath, $legacyPath): void {
        Route::get($xmlPath, SitemapXmlController::class)
            ->name('sitemap-xml');

        Route::get('{sitemapPrefix}/' . $xmlPath, SitemapXmlController::class)
            ->where('sitemapPrefix', '[^/]+')
            ->name('sitemap-xml.prefixed');

        if (! SitemapXmlPath::hasLegacyAlias()) {
            return;
        }

        // Installs that already advertised the legacy path keep working: crawlers and bookmarks are sent to the
        // canonical path with a permanent redirect that preserves the ?p=N chunk parameter.
        Route::get($legacyPath, LegacySitemapXmlRedirectController::class)
            ->name('sitemap-xml.legacy');

        Route::get('{sitemapPrefix}/' . $legacyPath, LegacySitemapXmlRedirectController::class)
            ->where('sitemapPrefix', '[^/]+')
            ->name('sitemap-xml.legacy.prefixed');
    });

// IndexNow verifies ownership by fetching `{key}.txt` from the site root. The route is the exact configured key, so
// it only exists while IndexNow is enabled and can never shadow another file such as llms-full.txt.
$indexNowKey = config('capell-site-discovery.indexnow.key');

if (config('capell-site-discovery.indexnow.enabled') === true && is_string($indexNowKey) && preg_match('/^[A-Za-z0-9-]{8,128}$/', $indexNowKey) === 1) {
    Route::name('capell-frontend.')
        ->middleware(['web', 'throttle:' . SitemapThrottle::PURPOSE])
        ->get($indexNowKey . '.txt', IndexNowKeyFileController::class)
        ->name('indexnow-key');
}
