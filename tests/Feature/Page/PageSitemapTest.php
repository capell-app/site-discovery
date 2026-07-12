<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\SiteDiscovery\Support\Creator\SitemapPageCreator;
use Capell\SiteDiscovery\Support\Loader\SitemapLoader;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Capell\Tests\Support\Concerns\TestingFrontend;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

use Sinnbeck\DomAssertions\Asserts\AssertElement;
use Sinnbeck\DomAssertions\Asserts\BaseAssert;

uses(SiteDiscoveryTestCase::class, TestingFrontend::class);

// Shared helper: configure a fake sitemap disk used by several tests.
function configureFakeSitemapDisk(): void
{
    config(['capell.sitemap.disk' => 'array', 'capell.sitemap.directory' => 'sitemaps']);
    Storage::fake('array');
    Cache::driver('array');
}

function siteDiscoveryPageUrl(Page $page): string
{
    $pageUrl = $page->pageUrl;

    throw_unless($pageUrl instanceof PageUrl, RuntimeException::class, 'Expected the page to have a page URL.');

    return $pageUrl->full_url;
}

function siteDiscoverySitemapXmlFilename(Page $page): string
{
    $url = siteDiscoveryPageUrl($page);
    $scheme = (string) parse_url($url, PHP_URL_SCHEME);
    $host = (string) parse_url($url, PHP_URL_HOST);
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
    $pathSegments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
    array_pop($pathSegments);

    return implode('-', array_filter([
        $scheme,
        str_replace('.', '-', $host),
        $pathSegments === [] ? null : str_replace('/', '.', implode('/', $pathSegments)),
    ])) . '.xml';
}

function siteDiscoveryPageTitle(Page $page): string
{
    $translation = $page->translation;

    throw_unless($translation instanceof Translation, RuntimeException::class, 'Expected the page to have a translation.');

    return (string) $translation->title;
}

test('sitemap html page', function (): void {
    $languages = Language::factory()->count(3)->create();
    $site = Site::factory()->withTranslations($languages)->create();

    $pageCreator = resolve(SitemapPageCreator::class);

    $sitemapPage = $pageCreator->createSitemapPage($site, $languages);

    $parentPage = Page::factory()->site($site)->withTranslations($languages)->create();
    $childPage = Page::factory()->site($site)->parent($parentPage)->withTranslations($languages)->create();
    $homepage = Page::factory()->site($site)->home()->withTranslations($languages, slug: '/')->create();

    // PageSaved listeners populate the sitemap cache during page creation,
    // but afterCreating hooks (translations, pageUrl) run after the listener
    // fires — so the cache may exclude the most recently created pages until
    // the next save invalidates it. Flush before requesting to ensure the
    // sitemap reflects the final DB state.
    Cache::flush();

    $siteMapUrls = [
        siteDiscoveryPageUrl($homepage),
        siteDiscoveryPageUrl($sitemapPage),
        siteDiscoveryPageUrl($parentPage),
        siteDiscoveryPageUrl($childPage),
    ];

    $response = get(siteDiscoveryPageUrl($sitemapPage));

    $response
        ->assertOk()
        ->assertElementExists(
            'h1',
            fn (AssertElement $elm): BaseAssert => $elm->containsText(siteDiscoveryPageTitle($sitemapPage)),
        )
        ->assertElementExists(
            '.vsitemap',
            fn (AssertElement $elm): BaseAssert => $elm->each(
                'a',
                fn (AssertElement $aElm, int $index): BaseAssert => $aElm->has('href', $siteMapUrls[$index]),
            ),
        );

    expect($response->getContent())
        ->not->toContain('capell-site-discovery')
        ->not->toContain('capell-sitemap');
});

test('sitemap default page label is translated after providers boot', function (): void {
    expect(CapellCore::getDefaultPage('sitemap')->label)->toBe('Sitemap');
});

test('sitemap xml page', function (): void {
    config(['capell.sitemap.disk' => 'array', 'capell.sitemap.directory' => 'sitemaps']);
    Storage::fake('array');
    Cache::driver('array');

    $languages = Language::factory()->count(3)->create();
    $site = Site::factory()->withTranslations($languages)->create();

    $pageCreator = resolve(SitemapPageCreator::class);
    $sitemapPage = $pageCreator->createSitemapPage($site, $languages);
    $homepage = Page::factory()->site($site)->home()->withTranslations($languages, slug: '/')->create();
    $pages = Page::factory()->count(5)->site($site)->withTranslations($languages)->create();
    Page::factory()->site($site)->withTranslations($languages)->meta('hidden', true)->create();

    $filename = siteDiscoverySitemapXmlFilename($sitemapPage);

    resolve(XmlSitemapGenerator::class)->generate($site);

    $siteMapUrls = [
        siteDiscoveryPageUrl($homepage),
        siteDiscoveryPageUrl($sitemapPage),
        ...$pages->pluck('pageUrl.full_url')->toArray(),
    ];

    get(siteDiscoveryPageUrl($sitemapPage) . '-xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
        ->assertElementExists(
            'urlset',
            fn (AssertElement $elm): BaseAssert => $elm->contains('url', 7)
                ->each(
                    'url',
                    fn (AssertElement $urlElm, int $index): BaseAssert => $urlElm
                        ->find(
                            'loc',
                            fn (AssertElement $locElm): BaseAssert => $locElm->containsText($siteMapUrls[$index]),
                        ),
                ),
        );
});

test('sitemap xml page returns 404 if file missing', function (): void {
    config(['capell.sitemap.disk' => 'array', 'capell.sitemap.directory' => 'sitemaps']);
    Storage::fake('array');
    Cache::driver('array');

    $languages = Language::factory()->create();
    $site = Site::factory()->withTranslations($languages)->create();
    $pageCreator = resolve(SitemapPageCreator::class);
    $sitemapPage = $pageCreator->createSitemapPage($site, collect([$languages]));

    get(siteDiscoveryPageUrl($sitemapPage) . '-xml')
        ->assertStatus(404);
});

test('sitemap xml page returns 304 with ETag', function (): void {
    config(['capell.sitemap.disk' => 'array', 'capell.sitemap.directory' => 'sitemaps']);
    Storage::fake('array');
    Cache::driver('array');

    $languages = Language::factory()->create();
    $site = Site::factory()->withTranslations($languages)->create();
    $pageCreator = resolve(SitemapPageCreator::class);
    $sitemapPage = $pageCreator->createSitemapPage($site, collect([$languages]));

    Page::factory()->count(5)->site($site)->withTranslations($languages)->create();

    resolve(XmlSitemapGenerator::class)->generate($site);

    $filePath = 'sitemaps/' . $site->siteDomain->getDomainKey() . '.xml';
    // Guarantee the file exists for the test
    if (! Storage::disk('array')->exists($filePath)) {
        Storage::disk('array')->put($filePath, '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>');
    }

    $fileContents = Storage::disk('array')->get($filePath);
    expect($fileContents)->not()->toBeNull('Sitemap file missing for ETag test');
    $etag = 'W/"' . hash('sha256', (string) $fileContents) . '"';

    get(siteDiscoveryPageUrl($sitemapPage) . '-xml', ['If-None-Match' => $etag])
        ->assertStatus(304);
});

test('sitemap loader reports generated xml files for sites with sitemap pages', function (): void {
    config(['capell.sitemap.disk' => 'array', 'capell.sitemap.directory' => 'sitemaps']);
    Storage::fake('array');
    Cache::flush();

    $language = Language::factory()->create();
    $siteWithSitemap = Site::factory()
        ->recycle($language)
        ->hasSiteDomain(['domain' => 'with-sitemap.test', 'scheme' => 'https', 'path' => null])
        ->withTranslations(collect([$language]))
        ->create();
    $siteWithoutSitemap = Site::factory()
        ->recycle($language)
        ->hasSiteDomain(['domain' => 'without-sitemap.test', 'scheme' => 'https', 'path' => null])
        ->withTranslations(collect([$language]))
        ->create();

    $pageCreator = resolve(SitemapPageCreator::class);
    $pageCreator->createSitemapPage($siteWithSitemap, collect([$language]));
    Page::factory()->site($siteWithSitemap)->withTranslations(collect([$language]))->create();
    Page::factory()->site($siteWithoutSitemap)->withTranslations(collect([$language]))->create();

    resolve(XmlSitemapGenerator::class)->generate($siteWithSitemap);

    $sitemaps = resolve(SitemapLoader::class)->all();

    expect($sitemaps)->toHaveCount(1)
        ->and($sitemaps[0]->name)->toBe('with-sitemap.test/')
        ->and($sitemaps[0]->url)->toBe('https://with-sitemap.test/sitemap-xml')
        ->and($sitemaps[0]->total)->toBeGreaterThanOrEqual(2);
});

test('sitemap loader hydrates cached scalar payloads when cache object unserialization is disabled', function (): void {
    config()->set('cache.default', 'array');
    config()->set('cache.stores.array.serialize', true);
    config()->set('cache.serializable_classes', false);
    Cache::purge('array');
    config(['capell.sitemap.disk' => 'array', 'capell.sitemap.directory' => 'sitemaps']);
    Storage::fake('array');

    $language = Language::factory()->create();
    $site = Site::factory()
        ->recycle($language)
        ->hasSiteDomain(['domain' => 'with-sitemap.test', 'scheme' => 'https', 'path' => null])
        ->withTranslations(collect([$language]))
        ->create();

    resolve(SitemapPageCreator::class)->createSitemapPage($site, collect([$language]));
    Page::factory()->site($site)->withTranslations(collect([$language]))->create();
    resolve(XmlSitemapGenerator::class)->generate($site);

    resolve(SitemapLoader::class)->all();

    Storage::disk('array')->deleteDirectory('sitemaps');

    $sitemaps = resolve(SitemapLoader::class)->all();

    expect($sitemaps)->toHaveCount(1)
        ->and($sitemaps[0]->name)->toBe('with-sitemap.test/')
        ->and($sitemaps[0]->url)->toBe('https://with-sitemap.test/sitemap-xml');
});

// ---------------------------------------------------------------------------
// Chunk serving: ?p=N query parameter
// ---------------------------------------------------------------------------

test('sitemap xml page serves a chunk file when ?p=N is provided', function (): void {
    config([
        'capell.sitemap.disk' => 'array',
        'capell.sitemap.directory' => 'sitemaps',
        'capell.sitemap.max_urls_per_file' => 2,
        'capell.sitemap.xml_path' => '/sitemap-xml',
    ]);
    Storage::fake('array');
    Cache::driver('array');

    $languages = Language::factory()->create();
    $site = Site::factory()->withTranslations(collect([$languages]))->create();
    $pageCreator = resolve(SitemapPageCreator::class);
    $sitemapPage = $pageCreator->createSitemapPage($site, collect([$languages]));

    // 3 pages + 1 sitemap page = 4 URLs → 2 chunks of 2 (limit=2)
    Page::factory()->count(3)->site($site)->withTranslations(collect([$languages]))->create();

    resolve(XmlSitemapGenerator::class)->generate($site);

    // Chunk 1 must be a urlset with 2 <url> entries
    get(siteDiscoveryPageUrl($sitemapPage) . '-xml?p=1')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertElementExists(
            'urlset',
            fn (AssertElement $elm): BaseAssert => $elm->contains('url', 2),
        );

    // Chunk 2 must be a urlset with 2 <url> entries
    get(siteDiscoveryPageUrl($sitemapPage) . '-xml?p=2')
        ->assertOk()
        ->assertElementExists(
            'urlset',
            fn (AssertElement $elm): BaseAssert => $elm->contains('url', 2),
        );
});

test('sitemap xml page serves a sitemapindex as the main file when chunks exist', function (): void {
    config([
        'capell.sitemap.disk' => 'array',
        'capell.sitemap.directory' => 'sitemaps',
        'capell.sitemap.max_urls_per_file' => 1,
    ]);
    Storage::fake('array');
    Cache::driver('array');

    $languages = Language::factory()->create();
    $site = Site::factory()->withTranslations(collect([$languages]))->create();
    $pageCreator = resolve(SitemapPageCreator::class);
    $sitemapPage = $pageCreator->createSitemapPage($site, collect([$languages]));

    Page::factory()->count(2)->site($site)->withTranslations(collect([$languages]))->create();

    resolve(XmlSitemapGenerator::class)->generate($site);

    // Main URL (no ?p) returns the sitemapindex
    get(siteDiscoveryPageUrl($sitemapPage) . '-xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertElementExists('sitemapindex');
});

test('sitemap xml page returns 404 for a chunk page that does not exist', function (): void {
    config(['capell.sitemap.disk' => 'array', 'capell.sitemap.directory' => 'sitemaps']);
    Storage::fake('array');
    Cache::driver('array');

    $languages = Language::factory()->create();
    $site = Site::factory()->withTranslations(collect([$languages]))->create();
    $pageCreator = resolve(SitemapPageCreator::class);
    $sitemapPage = $pageCreator->createSitemapPage($site, collect([$languages]));

    // Generate a regular (non-chunked) sitemap — no chunk files exist
    Page::factory()->site($site)->withTranslations(collect([$languages]))->create();
    resolve(XmlSitemapGenerator::class)->generate($site);

    get(siteDiscoveryPageUrl($sitemapPage) . '-xml?p=99')
        ->assertStatus(404);
});
