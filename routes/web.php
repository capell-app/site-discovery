<?php

declare(strict_types=1);

use Capell\SiteDiscovery\Http\Controllers\SitemapXmlController;
use Illuminate\Support\Facades\Route;

$configuredXmlPath = config('capell.sitemap.xml_path', '/sitemap-xml');
$xmlPath = trim(is_string($configuredXmlPath) || is_numeric($configuredXmlPath) ? (string) $configuredXmlPath : '/sitemap-xml', '/');
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
