<?php

declare(strict_types=1);

use Capell\Core\Models\SiteDomain;
use Capell\Core\Support\PublicRead\PublicReadProjectionHeaders;
use Capell\SiteDiscovery\Http\Controllers\IndexNowKeyFileController;
use Capell\SiteDiscovery\Support\Sitemap\SitemapXmlPath;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(SiteDiscoveryTestCase::class);

beforeEach(function (): void {
    config([
        'capell.sitemap.disk' => 'array',
        'capell.sitemap.directory' => 'sitemaps_route_test',
        'capell.sitemap.xml_path' => '/sitemap.xml',
    ]);

    Storage::fake('array');
});

it('serves the generated sitemap XML file for the request domain', function (): void {
    $domain = SiteDomain::factory()->state([
        'scheme' => 'https',
        'domain' => 'example.com',
        'path' => null,
    ])->create();
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset><url><loc>https://example.com/about</loc></url></urlset>';

    Storage::disk('array')->put('sitemaps_route_test/' . $domain->getDomainKey() . '.xml', $xml);

    get('https://example.com/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertHeader('Content-Disposition', 'inline; filename="' . $domain->getDomainKey() . '.xml"')
        ->assertSee('https://example.com/about', escape: false)
        ->assertDontSee('capell-site-discovery', escape: false)
        ->assertDontSee('capell-sitemap', escape: false)
        ->assertDontSee('admin', escape: false)
        ->assertDontSee('editor', escape: false);
});

it('serves generated sitemap XML for path-prefixed site domains', function (): void {
    $domain = SiteDomain::factory()->state([
        'scheme' => 'https',
        'domain' => 'example.com',
        'path' => '/uk',
    ])->create();
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset><url><loc>https://example.com/uk/about</loc></url></urlset>';

    Storage::disk('array')->put('sitemaps_route_test/' . $domain->getDomainKey() . '.xml', $xml);

    get('https://example.com/uk/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="' . $domain->getDomainKey() . '.xml"')
        ->assertSee('https://example.com/uk/about', escape: false);
});

it('serves the canonical domain sitemap on explicit ports without mixing publications', function (string $scheme, int $port, ?string $path): void {
    $domain = SiteDomain::factory()->state([
        'scheme' => $scheme,
        'domain' => 'example.com',
        'port' => $port,
        'path' => $path,
    ])->create();
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset><url><loc>' . $scheme . '://example.com:' . $port . $path . '/about</loc></url></urlset>';
    Storage::disk('array')->put('sitemaps_route_test/' . $domain->getDomainKey() . '.xml', $xml);
    if (! in_array($port, [80, 443], true)) {
        Storage::disk('array')->put('sitemaps_route_test/' . $scheme . '-example-com.xml', '<urlset><url><loc>https://other-publication.test</loc></url></urlset>');
    }

    get($scheme . '://example.com:' . $port . $path . '/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertHeader('Content-Disposition', 'inline; filename="' . $domain->getDomainKey() . '.xml"')
        ->assertContent($xml)
        ->assertHeader('ETag', 'W/' . PublicReadProjectionHeaders::etag($xml))
        ->assertHeader('Cache-Control', 'no-cache, public');
})->with([
    'HTTP non-default port' => ['http', 8545, null],
    'HTTPS non-default port and prefix' => ['https', 8443, '/uk'],
    'HTTP explicit default port' => ['http', 80, null],
    'HTTPS explicit default port' => ['https', 443, null],
]);

it('keeps prefixed sitemap routing constrained to one path segment', function (): void {
    $singleSegmentRoute = Route::getRoutes()->match(Request::create('/uk/sitemap.xml', 'GET'));

    expect($singleSegmentRoute->getName())->toBe('capell-frontend.sitemap-xml.prefixed');

    $multiSegmentName = null;

    try {
        $multiSegmentName = Route::getRoutes()->match(Request::create('/uk/public/sitemap.xml', 'GET'))->getName();
    } catch (NotFoundHttpException) {
        // A file-extension path outside the prefixed route is not claimed by any other route.
    }

    expect($multiSegmentName)->not->toBe('capell-frontend.sitemap-xml.prefixed');
});

it('serves chunked sitemap XML files by query page', function (): void {
    $domain = SiteDomain::factory()->state([
        'scheme' => 'https',
        'domain' => 'example.com',
        'path' => null,
    ])->create();
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset><url><loc>https://example.com/chunked</loc></url></urlset>';

    Storage::disk('array')->put('sitemaps_route_test/' . $domain->getDomainKey() . '-p2.xml', $xml);

    get('https://example.com/sitemap.xml?p=2')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="' . $domain->getDomainKey() . '-p2.xml"')
        ->assertSee('https://example.com/chunked', escape: false);
});

it('returns not found when the generated sitemap XML file is missing', function (): void {
    SiteDomain::factory()->state([
        'scheme' => 'https',
        'domain' => 'example.com',
        'path' => null,
    ])->create();

    get('https://example.com/sitemap.xml')
        ->assertNotFound();
});

it('returns not modified when the request ETag matches', function (): void {
    $domain = SiteDomain::factory()->state([
        'scheme' => 'https',
        'domain' => 'example.com',
        'path' => null,
    ])->create();
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>';
    $etag = 'W/' . PublicReadProjectionHeaders::etag($xml);

    Storage::disk('array')->put('sitemaps_route_test/' . $domain->getDomainKey() . '.xml', $xml);

    get('https://example.com/sitemap.xml', ['If-None-Match' => $etag])
        ->assertStatus(304)
        ->assertHeader('ETag', $etag);
});

it('serves the standard sitemap.xml path on a default install', function (): void {
    // Core still ships the legacy value as its config default; the package must still serve /sitemap.xml.
    config(['capell.sitemap.xml_path' => '/sitemap-xml']);

    $domain = SiteDomain::factory()->state(['scheme' => 'https', 'domain' => 'example.com', 'path' => null])->create();
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset><url><loc>https://example.com/default</loc></url></urlset>';
    Storage::disk('array')->put('sitemaps_route_test/' . $domain->getDomainKey() . '.xml', $xml);

    get('https://example.com/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertContent($xml);
    expect(SitemapXmlPath::canonical())->toBe('/sitemap.xml');
});

it('redirects the legacy sitemap-xml path to sitemap.xml permanently', function (string $legacy, string $canonical): void {
    get($legacy)
        ->assertStatus(301)
        ->assertRedirect($canonical);
})->with([
    'root' => ['https://example.com/sitemap-xml', 'https://example.com/sitemap.xml'],
    'chunk query is preserved' => ['https://example.com/sitemap-xml?p=2', 'https://example.com/sitemap.xml?p=2'],
    'path prefix' => ['https://example.com/uk/sitemap-xml', 'https://example.com/uk/sitemap.xml'],
    'path prefix with chunk' => ['https://example.com/uk/sitemap-xml?p=3', 'https://example.com/uk/sitemap.xml?p=3'],
]);

it('registers the sitemap routes from the configured path with a legacy redirect', function (): void {
    config(['capell.sitemap.xml_path' => '/discovery/map.xml']);

    $router = resolve('router');
    $before = $router->getRoutes()->getRoutes();
    $router->setRoutes(new RouteCollection);
    require dirname(__DIR__, 2) . '/routes/web.php';
    $router->getRoutes()->refreshNameLookups();

    try {
        expect(SitemapXmlPath::canonical())->toBe('/discovery/map.xml')
            ->and($router->getRoutes()->getByName('capell-frontend.sitemap-xml')?->uri())->toBe('discovery/map.xml')
            ->and($router->getRoutes()->getByName('capell-frontend.sitemap-xml.legacy')?->uri())->toBe('sitemap-xml')
            ->and($router->getRoutes()->getByName('capell-frontend.sitemap-xml.legacy.prefixed')?->uri())->toBe('{sitemapPrefix}/sitemap-xml');
    } finally {
        $restored = new RouteCollection;
        foreach ($before as $route) {
            $restored->add($route);
        }
        $router->setRoutes($restored);
        $router->getRoutes()->refreshNameLookups();
    }
});

it('resolves the sitemap path from configuration rather than a hardcoded value', function (?string $configured, string $expected): void {
    config(['capell.sitemap.xml_path' => $configured]);

    expect(SitemapXmlPath::canonical())->toBe($expected);
})->with([
    'unset' => [null, '/sitemap.xml'],
    'empty' => ['', '/sitemap.xml'],
    'slash only' => ['/', '/sitemap.xml'],
    'legacy value' => ['/sitemap-xml', '/sitemap.xml'],
    'canonical' => ['/sitemap.xml', '/sitemap.xml'],
    'custom without slash' => ['maps/site.xml', '/maps/site.xml'],
]);

it('rate limits the sitemap routes and serves the IndexNow key file only for the configured key while enabled', function (): void {
    config([
        'capell-site-discovery.indexnow.enabled' => true,
        'capell-site-discovery.indexnow.key' => 'abcdef1234567890',
    ]);

    $router = resolve('router');
    $before = $router->getRoutes()->getRoutes();
    $router->setRoutes(new RouteCollection);
    require dirname(__DIR__, 2) . '/routes/web.php';
    $router->getRoutes()->refreshNameLookups();

    try {
        $sitemap = $router->getRoutes()->getByName('capell-frontend.sitemap-xml');
        $keyFile = $router->getRoutes()->getByName('capell-frontend.indexnow-key');

        expect($sitemap?->gatherMiddleware())->toContain('throttle:capell-site-discovery-sitemap')
            ->and($keyFile?->uri())->toBe('abcdef1234567890.txt')
            ->and($keyFile?->gatherMiddleware())->toContain('throttle:capell-site-discovery-sitemap');
    } finally {
        $restored = new RouteCollection;
        foreach ($before as $route) {
            $restored->add($route);
        }
        $router->setRoutes($restored);
        $router->getRoutes()->refreshNameLookups();
    }

    $controller = new IndexNowKeyFileController;

    expect($controller()->getContent())->toBe('abcdef1234567890')
        ->and($controller()->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8');

    config(['capell-site-discovery.indexnow.enabled' => false]);

    expect(fn () => $controller())->toThrow(NotFoundHttpException::class);
});

it('registers no IndexNow key file route while IndexNow is disabled', function (): void {
    config(['capell-site-discovery.indexnow.enabled' => false, 'capell-site-discovery.indexnow.key' => 'abcdef1234567890']);

    $router = resolve('router');
    $before = $router->getRoutes()->getRoutes();
    $router->setRoutes(new RouteCollection);
    require dirname(__DIR__, 2) . '/routes/web.php';
    $router->getRoutes()->refreshNameLookups();

    try {
        expect($router->getRoutes()->getByName('capell-frontend.indexnow-key'))->toBeNull();
    } finally {
        $restored = new RouteCollection;
        foreach ($before as $route) {
            $restored->add($route);
        }
        $router->setRoutes($restored);
        $router->getRoutes()->refreshNameLookups();
    }
});
