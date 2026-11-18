<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;

/**
 * Serves the IndexNow ownership file: the configured key, as plain text.
 */
final class IndexNowKeyFileController extends BaseController
{
    public function __invoke(): Response
    {
        $key = config('capell-site-discovery.indexnow.key');

        abort_unless(config('capell-site-discovery.indexnow.enabled') === true && is_string($key) && $key !== '', 404);

        return new Response($key, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
