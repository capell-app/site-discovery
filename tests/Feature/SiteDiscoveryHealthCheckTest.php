<?php

declare(strict_types=1);

use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\SiteDiscovery\Health\SiteDiscoveryHealthCheck;
use Capell\SiteDiscovery\Providers\SiteDiscoveryServiceProvider;
use Capell\SiteDiscovery\Support\PublicUrls\CmsPagePublicUrlContributor;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPageRegistry;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

uses(SiteDiscoveryTestCase::class);

afterEach(function (): void {
    Storage::disk('local')->deleteDirectory('sitemaps_health_check');
});

it('reports a compatible capell api version', function (): void {
    expect(SiteDiscoveryHealthCheck::compatibleCapellApiVersion())->toBe('^1.0');
});

it('runs real diagnostics returning check results', function (): void {
    config([
        'capell.sitemap.disk' => 'local',
        'capell.sitemap.directory' => 'sitemaps_health_check',
    ]);

    $results = SiteDiscoveryHealthCheck::runDiagnostics();

    expect($results)->toHaveCount(4)
        ->and($results->every(static fn (mixed $result): bool => $result instanceof DoctorCheckResultData))->toBeTrue();
});

it('runs the single diagnostic matching a manifest health key', function (): void {
    config([
        'capell.sitemap.disk' => 'local',
        'capell.sitemap.directory' => 'sitemaps_health_check',
    ]);

    $results = SiteDiscoveryHealthCheck::runDiagnostics('site-discovery.xml-sitemaps');

    expect($results)->toHaveCount(1)
        ->and($results->first())->toBeInstanceOf(DoctorCheckResultData::class)
        ->and($results->first()?->label)->toBe(__('capell-site-discovery::package.health.xml_sitemaps.label'))
        ->and(SiteDiscoveryHealthCheck::runDiagnostics('site-discovery.unknown'))->toBeEmpty();
});

it('passes when public URL, XML sitemap, incremental, and HTML sitemap wiring are present', function (): void {
    config([
        'capell.sitemap.disk' => 'local',
        'capell.sitemap.directory' => 'sitemaps_health_check',
    ]);

    $results = SiteDiscoveryHealthCheck::runDiagnostics();

    expect(SiteDiscoveryHealthCheck::passed())->toBeTrue()
        ->and($results->every(static fn (DoctorCheckResultData $result): bool => $result->passed))->toBeTrue();
});

it('fails the public URL contributor check when the CMS page contributor is not registered', function (): void {
    app()->bind(CmsPagePublicUrlContributor::class, static fn (): object => new stdClass);

    $check = new SiteDiscoveryHealthCheck;

    expect($check->cmsPagePublicUrlContributorIsRegistered())->toBeFalse()
        ->and($check->publicUrlContributorsAreReady())->toBeFalse()
        ->and($check->publicUrlContributorCheck()->passed)->toBeFalse()
        ->and(SiteDiscoveryHealthCheck::passed())->toBeFalse();
});

it('fails the XML sitemap storage check when the sitemap disk is missing', function (): void {
    config(['capell.sitemap.disk' => 'missing-site-discovery-health-disk']);

    $check = new SiteDiscoveryHealthCheck;

    expect($check->xmlSitemapStorageIsConfigured())->toBeFalse()
        ->and($check->xmlSitemapStorageCheck()->passed)->toBeFalse();
});

it('confirms the XML sitemap route can serve generated XML output', function (): void {
    config([
        'capell.sitemap.disk' => 'local',
        'capell.sitemap.directory' => 'sitemaps_health_check',
        'capell.sitemap.xml_path' => '/sitemap-xml',
    ]);

    $check = new SiteDiscoveryHealthCheck;

    expect($check->xmlSitemapRoutesAreRegistered())->toBeTrue()
        ->and($check->xmlSitemapResponseCanBeBuilt())->toBeTrue()
        ->and($check->xmlSitemapOutputIsReady())->toBeTrue()
        ->and(Storage::disk('local')->exists('sitemaps_health_check/http-example-test.xml'))->toBeFalse();
});

it('confirms incremental state comparison detects changed and unchanged URL maps', function (): void {
    config([
        'capell.sitemap.disk' => 'local',
        'capell.sitemap.directory' => 'sitemaps_health_check',
    ]);

    $check = new SiteDiscoveryHealthCheck;

    expect($check->incrementalStateComparisonWorks())->toBeTrue()
        ->and($check->incrementalStateCheck()->passed)->toBeTrue();
});

it('fails incremental diagnostics when schedule configuration is invalid', function (): void {
    config([
        'capell.sitemap.disk' => 'local',
        'capell.sitemap.directory' => 'sitemaps_health_check',
        'capell-site-discovery.incremental_sitemap_schedule.enabled' => true,
        'capell-site-discovery.incremental_sitemap_schedule.frequency' => 'cron',
        'capell-site-discovery.incremental_sitemap_schedule.cron' => null,
    ]);

    $check = new SiteDiscoveryHealthCheck;

    expect($check->incrementalScheduleConfigurationIsValid())->toBeFalse()
        ->and($check->incrementalSitemapCheck()->passed)->toBeFalse();
});

it('confirms enabled incremental scheduling registers the command event', function (): void {
    config([
        'capell.sitemap.disk' => 'local',
        'capell.sitemap.directory' => 'sitemaps_health_check',
        'capell-site-discovery.incremental_sitemap_schedule.enabled' => true,
        'capell-site-discovery.incremental_sitemap_schedule.frequency' => 'dailyAt',
        'capell-site-discovery.incremental_sitemap_schedule.daily_at' => '02:30',
        'capell-site-discovery.incremental_sitemap_schedule.overlap_expires_after_minutes' => 65,
    ]);

    $schedule = new Schedule;
    app()->instance(Schedule::class, $schedule);

    (new SiteDiscoveryServiceProvider(app()))->registeringPackage();

    $check = new SiteDiscoveryHealthCheck;

    expect($check->incrementalScheduleConfigurationIsValid())->toBeTrue()
        ->and($check->incrementalScheduleIsRegisteredWhenEnabled())->toBeTrue()
        ->and($check->incrementalSitemapIsReady())->toBeTrue();
});

it('fails the HTML sitemap check when the default page registry entry is missing', function (): void {
    app()->forgetInstance(SitemapPageRegistry::class);
    app()->singleton(SitemapPageRegistry::class, static fn (): SitemapPageRegistry => new SitemapPageRegistry);

    $check = new SiteDiscoveryHealthCheck;

    expect($check->htmlSitemapIsRegistered())->toBeFalse()
        ->and($check->htmlSitemapTypeIsReady())->toBeFalse()
        ->and($check->htmlSitemapCheck()->passed)->toBeFalse();
});
