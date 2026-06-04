<?php

declare(strict_types=1);

use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\SiteDiscovery\Health\SiteDiscoveryHealthCheck;
use Capell\SiteDiscovery\Support\PublicUrls\CmsPagePublicUrlContributor;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPageRegistry;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Support\Facades\Storage;

uses(SiteDiscoveryTestCase::class);

afterEach(function (): void {
    Storage::disk('local')->deleteDirectory('sitemaps_health_check');
});

it('reports a compatible capell api version', function (): void {
    expect(SiteDiscoveryHealthCheck::compatibleCapellApiVersion())->toBe('^4.0');
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
        ->and($check->publicUrlContributorCheck()->passed)->toBeFalse()
        ->and(SiteDiscoveryHealthCheck::passed())->toBeFalse();
});

it('fails the XML sitemap storage check when the sitemap disk is missing', function (): void {
    config(['capell.sitemap.disk' => 'missing-site-discovery-health-disk']);

    $check = new SiteDiscoveryHealthCheck;

    expect($check->xmlSitemapStorageIsConfigured())->toBeFalse()
        ->and($check->xmlSitemapStorageCheck()->passed)->toBeFalse();
});

it('confirms incremental state comparison detects changed and unchanged URL maps', function (): void {
    $check = new SiteDiscoveryHealthCheck;

    expect($check->incrementalStateComparisonWorks())->toBeTrue()
        ->and($check->incrementalStateCheck()->passed)->toBeTrue();
});

it('fails the HTML sitemap check when the default page registry entry is missing', function (): void {
    app()->forgetInstance(SitemapPageRegistry::class);
    app()->singleton(SitemapPageRegistry::class, static fn (): SitemapPageRegistry => new SitemapPageRegistry);

    $check = new SiteDiscoveryHealthCheck;

    expect($check->htmlSitemapIsRegistered())->toBeFalse()
        ->and($check->htmlSitemapCheck()->passed)->toBeFalse();
});
