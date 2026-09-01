<?php

declare(strict_types=1);

use Capell\Admin\Support\Extensions\ExtensionPageRegistry;
use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionRoute;
use Capell\Core\Contracts\Extensions\RunsScheduledExtensionJob;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\DiscoveryFoundation\Contracts\PublicUrlContributor;
use Capell\DiscoveryFoundation\Data\PublicUrlData;
use Capell\DiscoveryFoundation\Data\PublicUrlRegistryEntryData;
use Capell\DiscoveryFoundation\Enums\PublicUrlIndexability;
use Capell\SiteDiscovery\Actions\BuildGeneratedOutputParityReportAction;
use Capell\SiteDiscovery\Actions\BuildPublicUrlRegistryAction;
use Capell\SiteDiscovery\Actions\GenerateSitemapAction;
use Capell\SiteDiscovery\Actions\ValidateSitemapQualityAction;
use Capell\SiteDiscovery\Contracts\GeneratedOutputCoverageSource;
use Capell\SiteDiscovery\Enums\GeneratedOutputParityStatus;
use Capell\SiteDiscovery\Enums\PublicUrlOutput;
use Capell\SiteDiscovery\Filament\Pages\PublicUrlRegistryPage;
use Capell\SiteDiscovery\Manifest\PublicUrlRegistryPageContribution;
use Capell\SiteDiscovery\Manifest\SiteDiscoveryFrontendRoutesContribution;
use Capell\SiteDiscovery\Manifest\SiteDiscoveryIncrementalSitemapScheduleContribution;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(SiteDiscoveryTestCase::class);
uses(CreatesAdminUser::class);

beforeEach(function (): void {
    config([
        'capell.sitemap.disk' => 'local',
        'capell.sitemap.directory' => 'sitemaps_public_url_registry_test',
    ]);

    Storage::disk('local')->deleteDirectory('sitemaps_public_url_registry_test');
    Storage::disk('local')->makeDirectory('sitemaps_public_url_registry_test');
});

afterEach(function (): void {
    Storage::disk('local')->deleteDirectory('sitemaps_public_url_registry_test');
});

it('builds generated output parity rows from registry entries', function (): void {
    $entry = new PublicUrlRegistryEntryData(
        canonicalUrl: 'https://example.com/present',
        sourcePackage: 'capell-app/test',
        siteKey: 1,
        languageKey: 1,
        siteId: 1,
        languageId: 1,
    );

    $report = BuildGeneratedOutputParityReportAction::run(
        registryEntries: [$entry],
        sitemapUrls: ['https://example.com/present/'],
        aiDiscoveryUrls: [],
        searchUrls: ['https://example.com/present'],
    );

    expect($report->totalUrls)->toBe(1)
        ->and($report->missingOutputUrls)->toBe(1)
        ->and($report->rows[0]->sitemapStatus)->toBe(GeneratedOutputParityStatus::Present)
        ->and($report->rows[0]->aiDiscoveryStatus)->toBe(GeneratedOutputParityStatus::Missing)
        ->and($report->rows[0]->searchStatus)->toBe(GeneratedOutputParityStatus::Present)
        ->and($report->rows[0]->htmlCacheStatus)->toBe(GeneratedOutputParityStatus::Unknown)
        ->and($report->rows[0]->agentDeliveryStatus)->toBe(GeneratedOutputParityStatus::Unknown)
        ->and($report->rows[0]->errors)->toBe(['missing_ai_discovery']);
});

it('marks non-indexable registry entries as not eligible for generated outputs', function (): void {
    $entry = new PublicUrlRegistryEntryData(
        canonicalUrl: 'https://example.com/private',
        sourcePackage: 'capell-app/test',
        siteKey: 1,
        languageKey: 1,
        siteId: 1,
        languageId: 1,
        indexability: PublicUrlIndexability::NoIndex,
        isSitemapEligible: false,
        isAiDiscoveryEligible: false,
    );

    $report = BuildGeneratedOutputParityReportAction::run(
        registryEntries: [$entry],
        sitemapUrls: [],
        aiDiscoveryUrls: [],
        searchUrls: [],
        htmlCacheUrls: [],
        agentDeliveryUrls: [],
    );

    expect($report->missingOutputUrls)->toBe(0)
        ->and($report->rows[0]->sitemapStatus)->toBe(GeneratedOutputParityStatus::NotEligible)
        ->and($report->rows[0]->aiDiscoveryStatus)->toBe(GeneratedOutputParityStatus::NotEligible)
        ->and($report->rows[0]->searchStatus)->toBe(GeneratedOutputParityStatus::NotEligible)
        ->and($report->rows[0]->htmlCacheStatus)->toBe(GeneratedOutputParityStatus::NotEligible)
        ->and($report->rows[0]->agentDeliveryStatus)->toBe(GeneratedOutputParityStatus::NotEligible);
});

it('discovers generated sitemap URLs from configured sitemap XML files', function (): void {
    Storage::disk('local')->put(
        'sitemaps_public_url_registry_test/example.xml',
        '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/from-sitemap/</loc></url></urlset>',
    );

    $entry = new PublicUrlRegistryEntryData(
        canonicalUrl: 'https://example.com/from-sitemap',
        sourcePackage: 'capell-app/test',
        siteKey: 1,
        languageKey: 1,
        siteId: 1,
        languageId: 1,
    );

    $report = BuildGeneratedOutputParityReportAction::run(registryEntries: [$entry]);

    expect($report->rows[0]->sitemapStatus)->toBe(GeneratedOutputParityStatus::Present);
});

it('uses tagged generated output coverage sources when explicit output URLs are not passed', function (): void {
    $entry = new PublicUrlRegistryEntryData(
        canonicalUrl: 'https://example.com/covered-by-ai',
        sourcePackage: 'capell-app/test',
        siteKey: 1,
        languageKey: 1,
        siteId: 1,
        languageId: 1,
    );

    app()->instance('site-discovery-public-url-registry-page-test-ai-coverage', new class implements GeneratedOutputCoverageSource
    {
        public function key(): string
        {
            return GeneratedOutputCoverageSource::AI_DISCOVERY;
        }

        /**
         * @param  Collection<int, PublicUrlRegistryEntryData>  $registryEntries
         * @return Collection<int, string>
         */
        public function coveredUrls(Collection $registryEntries): Collection
        {
            return $registryEntries
                ->pluck('canonicalUrl')
                ->filter(fn (mixed $url): bool => is_string($url))
                ->values();
        }
    });
    app()->tag(['site-discovery-public-url-registry-page-test-ai-coverage'], GeneratedOutputCoverageSource::TAG);

    $report = BuildGeneratedOutputParityReportAction::run(registryEntries: [$entry]);

    expect($report->rows[0]->aiDiscoveryStatus)->toBe(GeneratedOutputParityStatus::Present)
        ->and($report->rows[0]->searchStatus)->toBe(GeneratedOutputParityStatus::Unknown);
});

it('registers the public url registry as a site discovery extension page', function (): void {
    $extensionPages = collect(resolve(ExtensionPageRegistry::class)->entries())
        ->pluck('page');

    expect($extensionPages)->toContain(PublicUrlRegistryPage::class)
        ->and(PublicUrlRegistryPage::getNavigationLabel())->toBe(__('capell-site-discovery::generic.public_url_registry'))
        ->and(PublicUrlRegistryPage::getNavigationGroup())->toBe(__('capell-admin::navigation.group_monitoring'));
});

it('declares the public url registry page in the package manifest', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3) . '/capell.json'),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    throw_unless(is_array($manifest), RuntimeException::class, 'Expected Site Discovery manifest array.');
    throw_unless(is_array($manifest['contributes'] ?? null), RuntimeException::class, 'Expected Site Discovery contributions array.');

    $contributes = $manifest['contributes'];

    expect($contributes)->toContain([
        'type' => 'admin-page',
        'class' => PublicUrlRegistryPageContribution::class,
        'pageClass' => PublicUrlRegistryPage::class,
        'labelKey' => 'capell-site-discovery::generic.public_url_registry',
        'permission' => 'View:PublicUrlRegistryPage',
    ])
        ->and($contributes)->toContain([
            'type' => 'route',
            'class' => SiteDiscoveryFrontendRoutesContribution::class,
        ])
        ->and($contributes)->toContain([
            'type' => 'scheduled-job',
            'class' => SiteDiscoveryIncrementalSitemapScheduleContribution::class,
            'command' => 'capell:xml-sitemap --incremental',
            'name' => 'capell-site-discovery:incremental-sitemap',
            'frequencyConfig' => 'capell-site-discovery.incremental_sitemap_schedule',
            'defaultFrequency' => 'dailyAt:02:30',
            'enabledWhen' => 'capell-site-discovery.incremental_sitemap_schedule.enabled=true',
        ])
        ->and($manifest['actions'])->toMatchArray([
            'buildGeneratedOutputParityReport' => BuildGeneratedOutputParityReportAction::class,
            'buildPublicUrlRegistry' => BuildPublicUrlRegistryAction::class,
            'generateSitemap' => GenerateSitemapAction::class,
            'validateSitemapQuality' => ValidateSitemapQualityAction::class,
        ])
        ->and(class_implements(PublicUrlRegistryPageContribution::class))->toContain(ExtensionContribution::class)
        ->and(class_implements(SiteDiscoveryFrontendRoutesContribution::class))->toContain(RegistersExtensionRoute::class)
        ->and(class_implements(SiteDiscoveryIncrementalSitemapScheduleContribution::class))->toContain(RunsScheduledExtensionJob::class)
        ->and($manifest['contributionTraceability']['deferredContributions'])->toBe([]);
});

it('defaults to the repair queue and keeps the full matrix under row details', function (): void {
    test()->actingAsAdmin();

    $language = Language::factory()->create(['code' => 'en']);
    $siteDomain = SiteDomain::factory()->state([
        'domain' => 'example.com',
        'language_id' => $language->id,
        'scheme' => 'https',
        'path' => null,
    ])->create();
    $site = $siteDomain->site;

    app()->instance('site-discovery-public-url-registry-page-test-source', new readonly class($site, $language) implements PublicUrlContributor
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
                    canonicalUrl: 'https://example.com/missing-public',
                    sourcePackage: 'capell-app/public',
                    site: $this->site,
                    language: $this->language,
                    routeName: 'public.missing',
                ),
                new PublicUrlData(
                    canonicalUrl: 'https://example.com/noindex-private',
                    sourcePackage: 'capell-app/private',
                    site: $this->site,
                    language: $this->language,
                    indexability: PublicUrlIndexability::NoIndex,
                    isSitemapEligible: false,
                    isAiDiscoveryEligible: false,
                ),
            ]);
        }
    });
    app()->tag(['site-discovery-public-url-registry-page-test-source'], PublicUrlContributor::TAG);

    Livewire::test(PublicUrlRegistryPage::class)
        ->assertSuccessful()
        ->assertSet('queueView', PublicUrlRegistryPage::VIEW_NEEDS_ATTENTION)
        ->assertSee(__('capell-site-discovery::generic.public_url_registry'))
        // The default view is the repair queue: only the URL missing from an
        // eligible output appears, and the noindex URL is not repair work.
        ->assertSee('https://example.com/missing-public')
        ->assertDontSee('https://example.com/noindex-private')
        // Each issue states the reason, the responsible area, and a next step.
        ->assertSee(PublicUrlOutput::Sitemap->missingReason())
        ->assertSee(PublicUrlOutput::Sitemap->responsibleArea())
        ->assertSee(PublicUrlOutput::Sitemap->missingNextStep())
        ->assertSee(__('capell-site-discovery::generic.next_step'))
        // The complete matrix stays available under row details.
        ->assertSee(__('capell-site-discovery::generic.full_output_matrix'))
        ->assertSee(GeneratedOutputParityStatus::Missing->getLabel())
        // All URLs is an explicit alternate view.
        ->set('queueView', PublicUrlRegistryPage::VIEW_ALL)
        ->assertSee('https://example.com/missing-public')
        ->assertSee('https://example.com/noindex-private');
});

it('filters the repair queue by output and by advanced filters', function (): void {
    test()->actingAsAdmin();

    $language = Language::factory()->create(['code' => 'en']);
    $siteDomain = SiteDomain::factory()->state([
        'domain' => 'example.com',
        'language_id' => $language->id,
        'scheme' => 'https',
        'path' => null,
    ])->create();
    $site = $siteDomain->site;

    app()->instance('site-discovery-public-url-registry-page-filter-source', new readonly class($site, $language) implements PublicUrlContributor
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
                    canonicalUrl: 'https://example.com/first',
                    sourcePackage: 'capell-app/public',
                    site: $this->site,
                    language: $this->language,
                ),
                new PublicUrlData(
                    canonicalUrl: 'https://example.com/second',
                    sourcePackage: 'capell-app/other',
                    site: $this->site,
                    language: $this->language,
                ),
            ]);
        }
    });
    app()->tag(['site-discovery-public-url-registry-page-filter-source'], PublicUrlContributor::TAG);

    Livewire::test(PublicUrlRegistryPage::class)
        ->assertSee('https://example.com/first')
        ->assertSee('https://example.com/second')
        ->set('outputFilter', PublicUrlOutput::Sitemap->value)
        ->assertSee('https://example.com/first')
        ->set('outputFilter', '')
        ->call('toggleAdvancedFilters')
        ->assertSet('showAdvancedFilters', true)
        ->set('sourcePackageFilter', 'capell-app/other')
        ->assertSee('https://example.com/second')
        ->assertDontSee('https://example.com/first')
        ->call('clearFilters')
        ->assertSet('sourcePackageFilter', '')
        ->assertSee('https://example.com/first')
        ->assertSee('https://example.com/second');
});

it('explains an empty repair queue instead of showing a filter message', function (): void {
    test()->actingAsAdmin();

    Livewire::test(PublicUrlRegistryPage::class)
        ->assertSuccessful()
        ->assertSee(__('capell-site-discovery::generic.no_urls_need_attention'));
});
