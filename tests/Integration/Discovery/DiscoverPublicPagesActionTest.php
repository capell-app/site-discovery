<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\DiscoverPublicPagesAction;
use Capell\SiteDiscovery\Actions\DiscoverPublicUrlsAction;
use Capell\SiteDiscovery\Contracts\DiscoverableUrlSource;
use Capell\SiteDiscovery\Data\DiscoverablePageData;
use Capell\SiteDiscovery\Data\DiscoverableUrlData;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

uses(SiteDiscoveryTestCase::class);

beforeEach(function (): void {
    Cache::flush();
});

it('excludes pages that are not public discoverable pages', function (): void {
    $language = Language::query()->create([
        'name' => 'English',
        'locale' => 'en',
        'code' => 'en',
        'flag' => 'gb-eng',
        'status' => true,
        'default' => true,
        'order' => 1,
    ]);
    $site = Site::factory()->language($language)->withTranslations($language)->create();

    $publicPage = Page::factory()
        ->site($site)
        ->withTranslations($language, ['title' => 'Public Page'])
        ->create();

    Page::factory()
        ->site($site)
        ->withTranslations($language, ['title' => 'Noindex Page'])
        ->meta('robots', ['noindex'])
        ->create();

    Page::factory()
        ->site($site)
        ->withTranslations($language, ['title' => 'Associative Noindex Page'])
        ->meta('robots', ['noindex' => true])
        ->create();

    Page::factory()
        ->site($site)
        ->withTranslations($language, ['title' => 'Hidden Page'])
        ->meta('hidden', true)
        ->create();

    $disabledUrlPage = Page::factory()
        ->site($site)
        ->withTranslations($language, ['title' => 'Disabled URL Page'])
        ->create();

    $disabledUrlPage->pageUrl?->update(['status' => false]);

    $aliasPage = Page::factory()
        ->site($site)
        ->withTranslations($language, ['title' => 'Alias URL Page'])
        ->create();

    $aliasPage->pageUrl?->update(['type' => 'alias']);

    $pages = DiscoverPublicPagesAction::run($site, $language);

    expect($pages)->toHaveCount(1)
        ->and($pages->first())->toBeInstanceOf(DiscoverablePageData::class)
        ->and($pages->pluck('pageId')->all())->toBe([(int) $publicPage->getKey()])
        ->and($pages->first()?->title)->toBe('Public Page');
});

it('excludes pages with translation noindex directives', function (): void {
    $language = Language::query()->create([
        'name' => 'English',
        'locale' => 'en',
        'code' => 'en',
        'flag' => 'gb-eng',
        'status' => true,
        'default' => true,
        'order' => 1,
    ]);
    $site = Site::factory()->language($language)->withTranslations($language)->create();

    $publicPage = Page::factory()
        ->site($site)
        ->withTranslations($language, ['title' => 'Public Page'])
        ->create();

    Page::factory()
        ->site($site)
        ->withTranslations($language, [
            'title' => 'Translation Noindex Page',
            'meta' => ['robots' => ['noindex']],
        ])
        ->create();

    $pages = DiscoverPublicPagesAction::run($site, $language);

    expect($pages->pluck('pageId')->all())->toBe([(int) $publicPage->getKey()]);
});

it('discovers public URLs from pages and contributor sources', function (): void {
    $language = Language::query()->create([
        'name' => 'English',
        'locale' => 'en',
        'code' => 'en',
        'flag' => 'gb-eng',
        'status' => true,
        'default' => true,
        'order' => 1,
    ]);
    $site = Site::factory()->language($language)->withTranslations($language)->create();

    Page::factory()
        ->site($site)
        ->withTranslations($language, ['title' => 'Public Page'])
        ->meta('priority', 0.7)
        ->meta('changefreq', 'weekly')
        ->create();

    $source = new class implements DiscoverableUrlSource
    {
        /**
         * @return Collection<int, DiscoverableUrlData>
         */
        public function discover(Site $site, Language $language, ?SiteDomain $domain = null): Collection
        {
            return collect([
                new DiscoverableUrlData(loc: ($domain ?? $site->siteDomain)->full_url . '/contributed'),
                new DiscoverableUrlData(loc: ($domain ?? $site->siteDomain)->full_url . '/contributed'),
            ]);
        }
    };

    app()->instance('site-discovery-test-source', $source);
    app()->instance('site-discovery-invalid-source', new stdClass);
    app()->tag([
        'site-discovery-test-source',
        'site-discovery-invalid-source',
    ], 'capell-site-discovery:discoverable-url-sources');

    $urls = DiscoverPublicUrlsAction::run($site, $language);
    $contributedUrl = $site->siteDomain->full_url . '/contributed';
    $pageUrl = $urls->first(fn (DiscoverableUrlData $url): bool => $url->loc !== $contributedUrl);

    expect($urls)->toHaveCount(2)
        ->and($urls->pluck('loc')->all())->toContain($contributedUrl)
        ->and($pageUrl?->priority)->toBe('0.7')
        ->and($pageUrl?->changeFrequency)->toBe('weekly');
});
