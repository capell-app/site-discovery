<?php

declare(strict_types=1);

use Capell\Core\Enums\UrlTypeEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(SiteDiscoveryTestCase::class);

beforeEach(function (): void {
    config(['capell.sitemap.disk' => 'local', 'capell.sitemap.directory' => 'sitemaps_test']);
    $storage = Storage::disk('local');
    $storage->deleteDirectory('sitemaps_test');
    $storage->makeDirectory('sitemaps_test');
});

afterEach(function (): void {
    Storage::disk('local')->deleteDirectory('sitemaps_test');
});

/**
 * @return array<string, string>
 */
function sitemapXmlByDomainKey(): array
{
    $storage = Storage::disk('local');

    return collect($storage->files('sitemaps_test'))
        ->filter(fn (string $path): bool => str_ends_with($path, '.xml'))
        ->mapWithKeys(function (string $path) use ($storage): array {
            $xml = $storage->get($path);

            throw_unless(is_string($xml), RuntimeException::class, 'Expected sitemap XML file contents.');

            return [pathinfo($path, PATHINFO_FILENAME) => $xml];
        })
        ->all();
}

/**
 * @return list<array{hreflang: string, href: string}>
 */
function sitemapAlternates(string $xml, string $loc): array
{
    preg_match('#<url>(?:(?!</url>).)*<loc>' . preg_quote($loc, '#') . '</loc>(?:(?!</url>).)*</url>#s', $xml, $entry);

    if ($entry === []) {
        return [];
    }

    preg_match_all('/<xhtml:link\s[^>]*\/>/', $entry[0], $matches);

    return array_values(collect($matches[0])
        ->map(function (string $tag): array {
            preg_match('/href="([^"]*)"/', $tag, $href);
            preg_match('/hreflang="([^"]*)"/', $tag, $hreflang);

            return ['hreflang' => $hreflang[1] ?? '', 'href' => $href[1] ?? ''];
        })
        ->all());
}

/**
 * @return list<array{hreflang: string, href: string}>
 */
function expectedAlternateCluster(Page $page, Site $site): array
{
    $page->load('pageUrls.language', 'pageUrls.siteDomain');

    $eligible = $page->pageUrls
        ->filter(fn (PageUrl $url): bool => $url->type === null && $url->status)
        ->values();

    if ($eligible->count() < 2) {
        return [];
    }

    $cluster = $eligible
        ->map(fn (PageUrl $url): array => [
            'hreflang' => Str::of($url->language?->locale ?: $url->language?->code ?: '')->lower()->replace('_', '-')->toString(),
            'href' => $url->full_url,
        ])
        ->sortBy('hreflang')
        ->values()
        ->all();

    $default = $eligible->firstWhere('language_id', $site->language_id);

    if ($default instanceof PageUrl) {
        $cluster[] = ['hreflang' => 'x-default', 'href' => $default->full_url];
    }

    return array_values($cluster);
}

it('emits the same alternate cluster in every language sitemap', function (): void {
    $languages = Language::factory()->count(2)->create();
    $site = Site::factory()
        ->language($languages[0])
        ->withTranslations($languages)
        ->create();
    $page = Page::factory()->site($site)->withTranslations($languages)->create();

    (new XmlSitemapGenerator)->generate($site);

    $expected = expectedAlternateCluster($page, $site);
    $xmls = sitemapXmlByDomainKey();

    expect($xmls)->toHaveCount(2)
        ->and($expected)->toHaveCount(3);

    foreach ($page->pageUrls as $url) {
        $domainKey = $url->siteDomain->getDomainKey();

        expect($xmls)->toHaveKey($domainKey)
            ->and(sitemapAlternates($xmls[$domainKey], $url->full_url))->toBe($expected)
            ->and($xmls[$domainKey])->toContain('xmlns:xhtml="http://www.w3.org/1999/xhtml"');
    }
});

it('emits no alternates and no xhtml namespace for a single language site', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();
    $page = Page::factory()->site($site)->withTranslations()->create();

    (new XmlSitemapGenerator)->generate($site);

    $xml = collect(sitemapXmlByDomainKey())->first();

    expect($xml)->toBeString()
        ->and(sitemapAlternates((string) $xml, $page->pageUrl->full_url))->toBe([])
        ->and($xml)->not->toContain('xmlns:xhtml')
        ->and($xml)->not->toContain('<xhtml:link');
});

it('excludes disabled and redirect page urls from sitemap alternates', function (): void {
    $languages = Language::factory()->count(4)->create();
    $site = Site::factory()
        ->language($languages[0])
        ->withTranslations($languages)
        ->create();
    $page = Page::factory()->site($site)->withTranslations($languages)->create();

    $disabled = $page->pageUrls->firstWhere('language_id', $languages[2]->getKey());
    $disabled->update(['status' => false]);

    $redirect = $page->pageUrls->firstWhere('language_id', $languages[3]->getKey());
    $redirect->update(['type' => UrlTypeEnum::Redirect]);

    (new XmlSitemapGenerator)->generate($site);

    $page->load('pageUrls.siteDomain');
    $canonical = $page->pageUrls->firstWhere('language_id', $languages[0]->getKey());
    $disabled = $page->pageUrls->firstWhere('language_id', $languages[2]->getKey());
    $redirect = $page->pageUrls->firstWhere('language_id', $languages[3]->getKey());
    $xmls = sitemapXmlByDomainKey();
    $alternates = sitemapAlternates($xmls[$canonical->siteDomain->getDomainKey()], $canonical->full_url);

    expect(collect($alternates)->pluck('href'))
        ->not->toContain($disabled->full_url)
        ->not->toContain($redirect->full_url)
        ->toHaveCount(3);
});

it('omits x-default when the default language has no page url', function (): void {
    $languages = Language::factory()->count(3)->create();
    $site = Site::factory()
        ->language($languages[0])
        ->withTranslations($languages)
        ->create();
    $page = Page::factory()->site($site)->withTranslations($languages)->create();

    $page->pageUrls->firstWhere('language_id', $site->language_id)->delete();

    (new XmlSitemapGenerator)->generate($site);

    $remaining = $page->load('pageUrls.siteDomain')->pageUrls->firstWhere('language_id', $languages[1]->getKey());
    $xmls = sitemapXmlByDomainKey();
    $alternates = sitemapAlternates($xmls[$remaining->siteDomain->getDomainKey()], $remaining->full_url);

    expect(collect($alternates)->pluck('hreflang'))
        ->not->toContain('x-default')
        ->toHaveCount(2);
});

it('matches the head alternate cluster contract for the same page', function (): void {
    $languages = Language::factory()->count(3)->create();
    $site = Site::factory()
        ->language($languages[0])
        ->withTranslations($languages)
        ->create();
    $page = Page::factory()->site($site)->withTranslations($languages)->create();

    (new XmlSitemapGenerator)->generate($site);

    $xmls = sitemapXmlByDomainKey();
    $expected = expectedAlternateCluster($page, $site);

    foreach ($page->pageUrls as $url) {
        expect(sitemapAlternates($xmls[$url->siteDomain->getDomainKey()], $url->full_url))->toBe($expected);
    }
});
