<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Http\Controllers;

use Capell\SiteDiscovery\Support\Sitemap\SitemapXmlPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

/**
 * Sends the legacy `/sitemap-xml` path to the canonical sitemap path, keeping any path prefix and `?p=N` chunk.
 */
final class LegacySitemapXmlRedirectController extends BaseController
{
    public function __invoke(Request $request, ?string $sitemapPrefix = null): RedirectResponse
    {
        $target = url(($sitemapPrefix !== null ? '/' . trim($sitemapPrefix, '/') : '') . SitemapXmlPath::canonical());
        $query = $request->getQueryString();

        return redirect()->to($query !== null && $query !== '' ? $target . '?' . $query : $target, 301);
    }
}
