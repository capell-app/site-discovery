<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Http\Controllers;

use Capell\SiteDiscovery\Actions\BuildSitemapXmlResponseAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SitemapXmlController extends BaseController
{
    public function __invoke(Request $request, ?string $sitemapPrefix = null): Response|StreamedResponse
    {
        return BuildSitemapXmlResponseAction::run($request, $sitemapPrefix);
    }
}
