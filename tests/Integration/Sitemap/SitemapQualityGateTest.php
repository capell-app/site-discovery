<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\ValidateSitemapQualityAction;
use Capell\SiteDiscovery\Contracts\PublicUrlContributor;
use Capell\SiteDiscovery\Data\PublicUrlData;
use Capell\SiteDiscovery\Data\PublicUrlRegistryEntryData;
use Capell\SiteDiscovery\Enums\PublicUrlContentType;
use Capell\SiteDiscovery\Enums\PublicUrlIndexability;
use Capell\SiteDiscovery\Enums\SitemapQualityError;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

uses(SiteDiscoveryTestCase::class);

beforeEach(function (): void {
    config([
        'capell.sitemap.disk' => 'local',
        'capell.sitemap.directory' => 'sitemaps_quality_test',
    ]);

    Storage::disk('local')->deleteDirectory('sitemaps_quality_test');
    Storage::disk('local')->makeDirectory('sitemaps_quality_test');

    $this->language = Language::factory()->create();
    $this->siteDomain = SiteDomain::factory()->state([
        'domain' => 'example.com',
        'language_id' => $this->language->id,
        'scheme' => 'https',
        'path' => null,
    ])->create();
    $this->site = $this->siteDomain->site;

    $this->registerPublicUrls = function (array $urls, string $key): void {
        $urls = array_values($urls);

        $contributor = new readonly class($urls) implements PublicUrlContributor
        {
            /**
             * @param  list<PublicUrlData>  $urls
             */
            public function __construct(
                private array $urls,
            ) {}

            /**
             * @return Collection<int, PublicUrlData>
             */
            public function publicUrls(): Collection
            {
                return collect($this->urls);
            }
        };

        app()->instance($key, $contributor);
        app()->tag([$key], PublicUrlContributor::TAG);
    };
});

afterEach(function (): void {
    Storage::disk('local')->deleteDirectory('sitemaps_quality_test');
});

it('emits only canonical public registry URLs for the matching site domain', function (): void {
    ($this->registerPublicUrls)([
        new PublicUrlData(
            canonicalUrl: 'https://example.com/registry-page',
            sourcePackage: 'capell-app/test',
            site: $this->site,
            language: $this->language,
            routeName: 'test.registry-page',
        ),
        new PublicUrlData(
            canonicalUrl: 'https://other.example.com/registry-page',
            sourcePackage: 'capell-app/test',
            site: $this->site,
            language: $this->language,
            routeName: 'test.other-domain',
        ),
    ], 'site-discovery-quality-canonical-contributor');

    $xml = (new XmlSitemapGenerator)->generate($this->site);

    expect($xml)
        ->toContain('<loc>https://example.com/registry-page</loc>')
        ->not()->toContain('https://other.example.com/registry-page')
        ->not()->toContain('sourcePackage')
        ->not()->toContain('routeName')
        ->not()->toContain('siteId')
        ->not()->toContain('languageId');
});

it('excludes redirects non-indexable and sitemap-ineligible registry URLs', function (): void {
    ($this->registerPublicUrls)([
        new PublicUrlData(
            canonicalUrl: 'https://example.com/public-page',
            sourcePackage: 'capell-app/test',
            site: $this->site,
            language: $this->language,
        ),
        new PublicUrlData(
            canonicalUrl: 'https://example.com/noindex-page',
            sourcePackage: 'capell-app/test',
            site: $this->site,
            language: $this->language,
            indexability: PublicUrlIndexability::NoIndex,
        ),
        new PublicUrlData(
            canonicalUrl: 'https://example.com/redirect-target',
            sourcePackage: 'capell-app/test',
            site: $this->site,
            language: $this->language,
            isSitemapEligible: false,
        ),
        new PublicUrlData(
            canonicalUrl: 'https://example.com/feed.xml',
            sourcePackage: 'capell-app/test',
            site: $this->site,
            language: $this->language,
            contentType: PublicUrlContentType::Feed,
        ),
    ], 'site-discovery-quality-exclusions-contributor');

    $xml = (new XmlSitemapGenerator)->generate($this->site);

    expect($xml)
        ->toContain('https://example.com/public-page')
        ->not()->toContain('https://example.com/noindex-page')
        ->not()->toContain('https://example.com/redirect-target')
        ->not()->toContain('https://example.com/feed.xml');
});

it('emits stable lastmod values when present', function (): void {
    $lastModified = CarbonImmutable::parse('2026-05-01T12:34:56+00:00');

    ($this->registerPublicUrls)([
        new PublicUrlData(
            canonicalUrl: 'https://example.com/lastmod-page',
            sourcePackage: 'capell-app/test',
            site: $this->site,
            language: $this->language,
            lastModified: $lastModified,
        ),
    ], 'site-discovery-quality-lastmod-contributor');

    $xml = (new XmlSitemapGenerator)->generate($this->site);

    expect($xml)->toContain('<lastmod>2026-05-01T12:34:56+00:00</lastmod>');
});

it('generates valid XML for registry sitemap URLs', function (): void {
    ($this->registerPublicUrls)([
        new PublicUrlData(
            canonicalUrl: 'https://example.com/xml-page',
            sourcePackage: 'capell-app/test',
            site: $this->site,
            language: $this->language,
        ),
    ], 'site-discovery-quality-xml-contributor');

    $xml = (new XmlSitemapGenerator)->generate($this->site);
    $document = simplexml_load_string($xml);

    throw_unless($document instanceof SimpleXMLElement, RuntimeException::class, 'Expected generated sitemap XML to be parseable.');

    expect($document->getName())->toBe('urlset');
});

it('excludes signed admin draft and editor URLs from registry sitemap output', function (): void {
    ($this->registerPublicUrls)([
        new PublicUrlData(
            canonicalUrl: 'https://example.com/public-reference',
            sourcePackage: 'capell-app/test',
            site: $this->site,
            language: $this->language,
        ),
        new PublicUrlData(
            canonicalUrl: 'https://example.com/admin/pages/1',
            sourcePackage: 'capell-app/test',
            site: $this->site,
            language: $this->language,
        ),
        new PublicUrlData(
            canonicalUrl: 'https://example.com/editor/pages/1',
            sourcePackage: 'capell-app/test',
            site: $this->site,
            language: $this->language,
        ),
        new PublicUrlData(
            canonicalUrl: 'https://example.com/draft/page',
            sourcePackage: 'capell-app/test',
            site: $this->site,
            language: $this->language,
        ),
        new PublicUrlData(
            canonicalUrl: 'https://example.com/preview/page?signature=abc123&expires=1770000000',
            sourcePackage: 'capell-app/test',
            site: $this->site,
            language: $this->language,
        ),
    ], 'site-discovery-quality-private-contributor');

    $xml = (new XmlSitemapGenerator)->generate($this->site);

    expect($xml)
        ->toContain('https://example.com/public-reference')
        ->not()->toContain('/admin/')
        ->not()->toContain('/editor/')
        ->not()->toContain('/draft/')
        ->not()->toContain('signature=')
        ->not()->toContain('expires=');
});

it('detects duplicate URLs in the sitemap quality gate', function (): void {
    $entries = [
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.com/duplicate',
            sourcePackage: 'capell-app/test',
            siteKey: (int) $this->site->getKey(),
            languageKey: (int) $this->language->getKey(),
            siteId: (int) $this->site->getKey(),
            languageId: (int) $this->language->getKey(),
        ),
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.com/duplicate/',
            sourcePackage: 'capell-app/other',
            siteKey: (int) $this->site->getKey(),
            languageKey: (int) $this->language->getKey(),
            siteId: (int) $this->site->getKey(),
            languageId: (int) $this->language->getKey(),
        ),
    ];

    $report = ValidateSitemapQualityAction::run($entries);

    expect($report->passed)->toBeFalse()
        ->and($report->hasError(SitemapQualityError::DuplicateUrl))->toBeTrue();
});

it('detects non-sitemap registry entries in the sitemap quality gate', function (): void {
    $entries = [
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.com/noindex',
            sourcePackage: 'capell-app/test',
            siteKey: (int) $this->site->getKey(),
            languageKey: (int) $this->language->getKey(),
            siteId: (int) $this->site->getKey(),
            languageId: (int) $this->language->getKey(),
            indexability: PublicUrlIndexability::NoIndex,
        ),
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.com/feed.xml',
            sourcePackage: 'capell-app/test',
            siteKey: (int) $this->site->getKey(),
            languageKey: (int) $this->language->getKey(),
            siteId: (int) $this->site->getKey(),
            languageId: (int) $this->language->getKey(),
            contentType: PublicUrlContentType::Feed,
        ),
    ];

    $report = ValidateSitemapQualityAction::run($entries);

    expect($report->passed)->toBeFalse()
        ->and($report->hasError(SitemapQualityError::InvalidStatus))->toBeTrue()
        ->and($report->hasError(SitemapQualityError::InvalidContentType))->toBeTrue();
});

it('detects missing and stale last modified values in the sitemap quality gate', function (): void {
    $entries = [
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.com/missing-lastmod',
            sourcePackage: 'capell-app/test',
            siteKey: (int) $this->site->getKey(),
            languageKey: (int) $this->language->getKey(),
            siteId: (int) $this->site->getKey(),
            languageId: (int) $this->language->getKey(),
        ),
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.com/stale-lastmod',
            sourcePackage: 'capell-app/test',
            siteKey: (int) $this->site->getKey(),
            languageKey: (int) $this->language->getKey(),
            siteId: (int) $this->site->getKey(),
            languageId: (int) $this->language->getKey(),
            lastModified: CarbonImmutable::parse('2026-04-01T00:00:00+00:00'),
        ),
    ];

    $report = ValidateSitemapQualityAction::run(
        entries: $entries,
        requireLastModified: true,
        staleBefore: CarbonImmutable::parse('2026-05-01T00:00:00+00:00'),
    );

    expect($report->passed)->toBeFalse()
        ->and($report->hasError(SitemapQualityError::MissingLastModified))->toBeTrue()
        ->and($report->hasError(SitemapQualityError::StaleLastModified))->toBeTrue();
});

it('detects malformed XML in the sitemap quality gate', function (): void {
    $report = ValidateSitemapQualityAction::run([], '<urlset><url><loc>https://example.com');

    expect($report->passed)->toBeFalse()
        ->and($report->hasError(SitemapQualityError::MalformedXml))->toBeTrue();
});
