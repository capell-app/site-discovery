<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Exceptions;

/**
 * Public-read eligibility changed while a sitemap set was being generated, so
 * the set may list pages that are now restricted and is not published.
 */
final class SitemapPublicReadRevisionChangedException extends SitemapGeneratorException {}
