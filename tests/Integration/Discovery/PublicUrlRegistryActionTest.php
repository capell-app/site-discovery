<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\DiscoveryFoundation\Actions\BuildPublicUrlRegistryAction;
use Capell\DiscoveryFoundation\Contracts\PublicUrlContributor;
use Capell\DiscoveryFoundation\Data\PublicUrlData;
use Capell\DiscoveryFoundation\Data\PublicUrlRegistryEntryData;
use Capell\DiscoveryFoundation\Enums\PublicUrlContentType;
use Capell\DiscoveryFoundation\Enums\PublicUrlIndexability;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

uses(SiteDiscoveryTestCase::class);

beforeEach(function (): void {
    $this->language = Language::query()->create([
        'name' => 'English',
        'locale' => 'en',
        'code' => 'en',
        'flag' => 'gb-eng',
        'status' => true,
        'default' => true,
        'order' => 1,
    ]);

    $this->site = Site::factory()
        ->language($this->language)
        ->withTranslations($this->language)
        ->create();
});

it('includes existing CMS page URLs from the built-in page contributor', function (): void {
    Page::factory()
        ->site($this->site)
        ->withTranslations($this->language, ['title' => 'Registry Page'])
        ->meta('priority', 0.8)
        ->meta('changefreq', 'daily')
        ->create();

    $entry = BuildPublicUrlRegistryAction::run()->first();

    expect($entry)->toBeInstanceOf(PublicUrlRegistryEntryData::class)
        ->and($entry?->canonicalUrl)->toStartWith('http')
        ->and($entry?->sourcePackage)->toBe('capell-app/discovery-foundation')
        ->and($entry?->routeName)->toBe('capell.pages.show')
        ->and($entry?->siteId)->toBe((int) $this->site->getKey())
        ->and($entry?->languageId)->toBe((int) $this->language->getKey())
        ->and($entry?->contentType)->toBe(PublicUrlContentType::Page)
        ->and($entry?->priority)->toBe('0.8')
        ->and($entry?->changeFrequency)->toBe('daily')
        ->and($entry?->isSitemapEligible)->toBeTrue()
        ->and($entry?->isAiDiscoveryEligible)->toBeTrue();
});

it('builds normalized registry entries from public URL contributors', function (): void {
    $lastModified = Date::parse('2026-05-01 12:34:56', 'Europe/London');
    $contributor = new readonly class($this->site, $this->language, $lastModified) implements PublicUrlContributor
    {
        public function __construct(
            private Site $site,
            private Language $language,
            private Carbon $lastModified,
        ) {}

        /**
         * @return Collection<int, PublicUrlData>
         */
        public function publicUrls(): Collection
        {
            return collect([
                new PublicUrlData(
                    canonicalUrl: ' https://Example.test/about/ ',
                    sourcePackage: 'capell-app/core',
                    site: $this->site,
                    language: $this->language,
                    routeName: 'capell.pages.show',
                    lastModified: $this->lastModified,
                    robotsDirectives: ['INDEX', 'follow', 'follow'],
                    contentType: PublicUrlContentType::Page,
                    isSitemapEligible: true,
                    isAiDiscoveryEligible: true,
                    priority: '0.7',
                    changeFrequency: 'weekly',
                    title: ' About Capell ',
                ),
            ]);
        }
    };

    app()->instance('site-discovery-public-url-registry-page-contributor', $contributor);
    app()->tag(['site-discovery-public-url-registry-page-contributor'], PublicUrlContributor::TAG);

    $entries = BuildPublicUrlRegistryAction::run();
    $entry = $entries->first();

    expect($entries)->toHaveCount(1)
        ->and($entry)->toBeInstanceOf(PublicUrlRegistryEntryData::class)
        ->and($entry?->canonicalUrl)->toBe('https://example.test/about')
        ->and($entry?->sourcePackage)->toBe('capell-app/core')
        ->and($entry?->siteId)->toBe((int) $this->site->getKey())
        ->and($entry?->languageId)->toBe((int) $this->language->getKey())
        ->and($entry?->languageCode)->toBe('en')
        ->and($entry?->routeName)->toBe('capell.pages.show')
        ->and($entry?->lastModified)->toBeInstanceOf(CarbonImmutable::class)
        ->and($entry?->lastModified?->toAtomString())->toBe($lastModified->toImmutable()->toAtomString())
        ->and($entry?->indexability)->toBe(PublicUrlIndexability::Indexable)
        ->and($entry?->robotsDirectives)->toBe(['index', 'follow'])
        ->and($entry?->contentType)->toBe(PublicUrlContentType::Page)
        ->and($entry?->isSitemapEligible)->toBeTrue()
        ->and($entry?->isAiDiscoveryEligible)->toBeTrue()
        ->and($entry?->priority)->toBe('0.7')
        ->and($entry?->changeFrequency)->toBe('weekly')
        ->and($entry?->title)->toBe('About Capell');
});

it('deduplicates canonical URLs by site and language scope', function (): void {
    $secondLanguage = Language::query()->create([
        'name' => 'French',
        'locale' => 'fr',
        'code' => 'fr',
        'flag' => 'fr',
        'status' => true,
        'default' => false,
        'order' => 2,
    ]);
    $secondSite = Site::factory()
        ->language($this->language)
        ->withTranslations($this->language)
        ->create();

    $contributor = new readonly class($this->site, $secondSite, $this->language, $secondLanguage) implements PublicUrlContributor
    {
        public function __construct(
            private Site $site,
            private Site $secondSite,
            private Language $language,
            private Language $secondLanguage,
        ) {}

        /**
         * @return Collection<int, PublicUrlData>
         */
        public function publicUrls(): Collection
        {
            return collect([
                new PublicUrlData(
                    canonicalUrl: 'https://example.test/about',
                    sourcePackage: 'capell-app/core',
                    site: $this->site,
                    language: $this->language,
                ),
                new PublicUrlData(
                    canonicalUrl: 'https://EXAMPLE.test/about/',
                    sourcePackage: 'capell-app/blog',
                    site: $this->site,
                    language: $this->language,
                ),
                new PublicUrlData(
                    canonicalUrl: 'https://example.test/about',
                    sourcePackage: 'capell-app/core',
                    site: $this->site,
                    language: $this->secondLanguage,
                ),
                new PublicUrlData(
                    canonicalUrl: 'https://example.test/about',
                    sourcePackage: 'capell-app/core',
                    site: $this->secondSite,
                    language: $this->language,
                ),
            ]);
        }
    };

    app()->instance('site-discovery-public-url-registry-dedupe-contributor', $contributor);
    app()->tag(['site-discovery-public-url-registry-dedupe-contributor'], PublicUrlContributor::TAG);

    $entries = BuildPublicUrlRegistryAction::run();

    expect($entries)->toHaveCount(3)
        ->and($entries->pluck('sourcePackage')->all())->toContain('capell-app/core')
        ->and($entries->pluck('sourcePackage')->all())->not->toContain('capell-app/blog')
        ->and($entries->pluck('languageId')->all())->toContain((int) $this->language->getKey(), (int) $secondLanguage->getKey())
        ->and($entries->pluck('siteId')->all())->toContain((int) $this->site->getKey(), (int) $secondSite->getKey());
});

it('conservatively merges duplicate canonical URLs regardless of order', function (): void {
    $olderLastModified = CarbonImmutable::parse('2026-04-01 10:00:00', 'UTC');
    $newerLastModified = CarbonImmutable::parse('2026-05-01 10:00:00', 'UTC');

    $contributor = new readonly class($this->site, $this->language, $olderLastModified, $newerLastModified) implements PublicUrlContributor
    {
        public function __construct(
            private Site $site,
            private Language $language,
            private CarbonImmutable $olderLastModified,
            private CarbonImmutable $newerLastModified,
        ) {}

        /**
         * @return Collection<int, PublicUrlData>
         */
        public function publicUrls(): Collection
        {
            return collect([
                new PublicUrlData(
                    canonicalUrl: 'https://example.test/about',
                    sourcePackage: 'capell-app/core',
                    site: $this->site,
                    language: $this->language,
                    routeName: 'capell.pages.show',
                    lastModified: $this->olderLastModified,
                    robotsDirectives: ['index', 'follow'],
                    isSitemapEligible: true,
                    isAiDiscoveryEligible: true,
                ),
                new PublicUrlData(
                    canonicalUrl: 'https://EXAMPLE.test/about/',
                    sourcePackage: 'capell-app/blog',
                    site: $this->site,
                    language: $this->language,
                    routeName: 'capell.blog.show',
                    lastModified: $this->newerLastModified,
                    indexability: PublicUrlIndexability::NoIndex,
                    robotsDirectives: ['noindex', 'nofollow'],
                    contentType: PublicUrlContentType::Article,
                    isSitemapEligible: true,
                    isAiDiscoveryEligible: false,
                ),
            ]);
        }
    };

    app()->instance('site-discovery-public-url-registry-merge-contributor', $contributor);
    app()->tag(['site-discovery-public-url-registry-merge-contributor'], PublicUrlContributor::TAG);

    $entry = BuildPublicUrlRegistryAction::run()->first();

    expect($entry)->toBeInstanceOf(PublicUrlRegistryEntryData::class)
        ->and($entry?->sourcePackage)->toBe('capell-app/core')
        ->and($entry?->routeName)->toBe('capell.pages.show')
        ->and($entry?->contentType)->toBe(PublicUrlContentType::Page)
        ->and($entry?->indexability)->toBe(PublicUrlIndexability::NoIndex)
        ->and($entry?->isSitemapEligible)->toBeFalse()
        ->and($entry?->isAiDiscoveryEligible)->toBeFalse()
        ->and($entry?->robotsDirectives)->toBe(['index', 'follow', 'noindex', 'nofollow'])
        ->and($entry?->lastModified?->toAtomString())->toBe($newerLastModified->toAtomString());
});

it('forces noindex URLs out of sitemap and AI discovery eligibility', function (): void {
    $contributor = new readonly class($this->site, $this->language) implements PublicUrlContributor
    {
        public function __construct(
            private Site $site,
            private Language $language,
        ) {}

        /**
         * @return Collection<int, PublicUrlData>
         */
        public function publicUrls(): Collection
        {
            return collect([
                new PublicUrlData(
                    canonicalUrl: 'https://example.test/private',
                    sourcePackage: 'capell-app/core',
                    site: $this->site,
                    language: $this->language,
                    indexability: PublicUrlIndexability::NoIndex,
                    isSitemapEligible: true,
                    isAiDiscoveryEligible: true,
                ),
                new PublicUrlData(
                    canonicalUrl: 'https://example.test/robots-private',
                    sourcePackage: 'capell-app/core',
                    site: $this->site,
                    language: $this->language,
                    robotsDirectives: ['noindex' => true, 'nofollow'],
                    isSitemapEligible: true,
                    isAiDiscoveryEligible: true,
                ),
            ]);
        }
    };

    app()->instance('site-discovery-public-url-registry-noindex-contributor', $contributor);
    app()->tag(['site-discovery-public-url-registry-noindex-contributor'], PublicUrlContributor::TAG);

    $entries = BuildPublicUrlRegistryAction::run();

    expect($entries)->toHaveCount(2)
        ->and($entries->every(fn (PublicUrlRegistryEntryData $entry): bool => $entry->indexability === PublicUrlIndexability::NoIndex))->toBeTrue()
        ->and($entries->every(fn (PublicUrlRegistryEntryData $entry): bool => ! $entry->isSitemapEligible))->toBeTrue()
        ->and($entries->every(fn (PublicUrlRegistryEntryData $entry): bool => ! $entry->isAiDiscoveryEligible))->toBeTrue()
        ->and($entries->last()?->robotsDirectives)->toBe(['noindex', 'nofollow']);
});

it('preserves explicit sitemap ineligibility for indexable URLs', function (): void {
    $contributor = new readonly class($this->site, $this->language) implements PublicUrlContributor
    {
        public function __construct(
            private Site $site,
            private Language $language,
        ) {}

        /**
         * @return Collection<int, PublicUrlData>
         */
        public function publicUrls(): Collection
        {
            return collect([
                new PublicUrlData(
                    canonicalUrl: 'https://example.test/not-in-sitemap',
                    sourcePackage: 'capell-app/core',
                    site: $this->site,
                    language: $this->language,
                    indexability: PublicUrlIndexability::Indexable,
                    isSitemapEligible: false,
                    isAiDiscoveryEligible: true,
                ),
            ]);
        }
    };

    app()->instance('site-discovery-public-url-registry-sitemap-eligibility-contributor', $contributor);
    app()->tag(['site-discovery-public-url-registry-sitemap-eligibility-contributor'], PublicUrlContributor::TAG);

    $entry = BuildPublicUrlRegistryAction::run()->first();

    expect($entry)->toBeInstanceOf(PublicUrlRegistryEntryData::class)
        ->and($entry?->indexability)->toBe(PublicUrlIndexability::Indexable)
        ->and($entry?->isSitemapEligible)->toBeFalse()
        ->and($entry?->isAiDiscoveryEligible)->toBeTrue();
});

it('preserves source package tracking and explicit AI discovery eligibility', function (): void {
    $contributor = new readonly class($this->site, $this->language) implements PublicUrlContributor
    {
        public function __construct(
            private Site $site,
            private Language $language,
        ) {}

        /**
         * @return Collection<int, PublicUrlData>
         */
        public function publicUrls(): Collection
        {
            return collect([
                new PublicUrlData(
                    canonicalUrl: 'https://example.test/ai-reference',
                    sourcePackage: 'capell-app/agent-delivery',
                    site: $this->site,
                    language: $this->language,
                    contentType: PublicUrlContentType::Other,
                    isAiDiscoveryEligible: false,
                ),
            ]);
        }
    };

    app()->instance('site-discovery-public-url-registry-ai-contributor', $contributor);
    app()->tag(['site-discovery-public-url-registry-ai-contributor'], PublicUrlContributor::TAG);

    $entries = BuildPublicUrlRegistryAction::run();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()?->sourcePackage)->toBe('capell-app/agent-delivery')
        ->and($entries->first()?->contentType)->toBe(PublicUrlContentType::Other)
        ->and($entries->first()?->isSitemapEligible)->toBeTrue()
        ->and($entries->first()?->isAiDiscoveryEligible)->toBeFalse();
});

it('rejects malformed and unsupported canonical URLs while preserving root URL normalization', function (): void {
    $contributor = new readonly class($this->site, $this->language) implements PublicUrlContributor
    {
        public function __construct(
            private Site $site,
            private Language $language,
        ) {}

        /**
         * @return Collection<int, PublicUrlData>
         */
        public function publicUrls(): Collection
        {
            return collect([
                new PublicUrlData(
                    canonicalUrl: '/relative',
                    sourcePackage: 'capell-app/core',
                    site: $this->site,
                    language: $this->language,
                ),
                new PublicUrlData(
                    canonicalUrl: 'mailto:editor@example.test',
                    sourcePackage: 'capell-app/core',
                    site: $this->site,
                    language: $this->language,
                ),
                new PublicUrlData(
                    canonicalUrl: 'javascript:alert(1)',
                    sourcePackage: 'capell-app/core',
                    site: $this->site,
                    language: $this->language,
                ),
                new PublicUrlData(
                    canonicalUrl: 'ftp://example.test/file',
                    sourcePackage: 'capell-app/core',
                    site: $this->site,
                    language: $this->language,
                ),
                new PublicUrlData(
                    canonicalUrl: 'https:///missing-host',
                    sourcePackage: 'capell-app/core',
                    site: $this->site,
                    language: $this->language,
                ),
                new PublicUrlData(
                    canonicalUrl: 'https://Example.test/',
                    sourcePackage: 'capell-app/core',
                    site: $this->site,
                    language: $this->language,
                ),
            ]);
        }
    };

    app()->instance('site-discovery-public-url-registry-url-normalization-contributor', $contributor);
    app()->tag(['site-discovery-public-url-registry-url-normalization-contributor'], PublicUrlContributor::TAG);

    $entries = BuildPublicUrlRegistryAction::run();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()?->canonicalUrl)->toBe('https://example.test');
});
