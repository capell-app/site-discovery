<?php

declare(strict_types=1);

use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

uses(SiteDiscoveryTestCase::class);

beforeEach(function (): void {
    config([
        'capell.sitemap.disk' => 'array',
        'capell.sitemap.directory' => 'sitemaps_route_test',
        'capell.sitemap.xml_path' => '/sitemap-xml',
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

    get('https://example.com/sitemap-xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="' . $domain->getDomainKey() . '.xml"')
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

    get('https://example.com/uk/sitemap-xml')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="' . $domain->getDomainKey() . '.xml"')
        ->assertSee('https://example.com/uk/about', escape: false);
});

it('keeps prefixed sitemap routing constrained to one path segment', function (): void {
    $singleSegmentRoute = Route::getRoutes()->match(Request::create('/uk/sitemap-xml', 'GET'));
    $multiSegmentRoute = Route::getRoutes()->match(Request::create('/uk/public/sitemap-xml', 'GET'));

    expect($singleSegmentRoute->getName())->toBe('capell-frontend.sitemap-xml.prefixed')
        ->and($multiSegmentRoute->getName())->not->toBe('capell-frontend.sitemap-xml.prefixed');
});

it('serves chunked sitemap XML files by query page', function (): void {
    $domain = SiteDomain::factory()->state([
        'scheme' => 'https',
        'domain' => 'example.com',
        'path' => null,
    ])->create();
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset><url><loc>https://example.com/chunked</loc></url></urlset>';

    Storage::disk('array')->put('sitemaps_route_test/' . $domain->getDomainKey() . '-p2.xml', $xml);

    get('https://example.com/sitemap-xml?p=2')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="' . $domain->getDomainKey() . '-p2.xml"')
        ->assertSee('https://example.com/chunked', escape: false);
});

it('returns not found when the generated sitemap XML file is missing', function (): void {
    SiteDomain::factory()->state([
        'scheme' => 'https',
        'domain' => 'example.com',
        'path' => null,
    ])->create();

    get('https://example.com/sitemap-xml')
        ->assertNotFound();
});

it('returns not modified when the request ETag matches', function (): void {
    $domain = SiteDomain::factory()->state([
        'scheme' => 'https',
        'domain' => 'example.com',
        'path' => null,
    ])->create();
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>';
    $etag = 'W/"' . hash('sha256', $xml) . '"';

    Storage::disk('array')->put('sitemaps_route_test/' . $domain->getDomainKey() . '.xml', $xml);

    get('https://example.com/sitemap-xml', ['If-None-Match' => $etag])
        ->assertStatus(304)
        ->assertHeader('ETag', $etag);
});
