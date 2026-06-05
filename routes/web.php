<?php

declare(strict_types=1);

use Capell\SiteDiscovery\Http\Controllers\SitemapXmlController;
use Illuminate\Support\Facades\Route;

$xmlPath = trim((string) config('capell.sitemap.xml_path', '/sitemap-xml'), '/');
$xmlPath = $xmlPath !== '' ? $xmlPath : 'sitemap-xml';

Route::name('capell-frontend.')
    ->middleware(['web'])
    ->group(function () use ($xmlPath): void {
        Route::get($xmlPath, SitemapXmlController::class)
            ->name('sitemap-xml');

        Route::get('{sitemapPrefix}/' . $xmlPath, SitemapXmlController::class)
            ->where('sitemapPrefix', '.*')
            ->name('sitemap-xml.prefixed');
    });
