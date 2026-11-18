<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\RequestSiteSitemapRegenerationAction;
use Capell\SiteDiscovery\Jobs\RegenerateSiteSitemapIncrementallyJob;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(SiteDiscoveryTestCase::class);

beforeEach(function (): void {
    config([
        'capell.sitemap.disk' => 'array',
        'capell.sitemap.directory' => 'sitemaps_regeneration_job_test',
    ]);

    Storage::fake('array');
    Cache::flush();
});

it('queues another regeneration for a page saved after the first run has started', function (): void {
    Queue::fake();
    $this->travelTo(now()->startOfMinute());
    $site = Site::factory()->default()->withTranslations()->create();
    SiteDomain::factory()->state(['site_id' => $site->id])->create();
    // Creating the domain already requested a regeneration; start from a clean debounce state.
    Cache::flush();

    expect(RequestSiteSitemapRegenerationAction::run($site))->toBeTrue();

    // The first run starts at the end of the debounce delay, before the claim's grace window has ended.
    $this->travel(61)->seconds();
    $job = new RegenerateSiteSitemapIncrementallyJob($site->getKey());
    // A worker releases the until-processing unique lock before running the job.
    (new UniqueLock(Cache::driver()))->release($job);
    $job->handle();

    $this->travel(14)->seconds();

    expect(RequestSiteSitemapRegenerationAction::run($site))->toBeTrue();
    Queue::assertPushed(RegenerateSiteSitemapIncrementallyJob::class, 2);
});

it('still collapses saves made before the first run starts into one regeneration', function (): void {
    Queue::fake();
    $site = Site::factory()->default()->withTranslations()->create();
    SiteDomain::factory()->state(['site_id' => $site->id])->create();
    // Creating the domain already requested a regeneration; start from a clean debounce state.
    Cache::flush();

    expect(RequestSiteSitemapRegenerationAction::run($site))->toBeTrue()
        ->and(RequestSiteSitemapRegenerationAction::run($site))->toBeFalse();
    Queue::assertPushed(RegenerateSiteSitemapIncrementallyJob::class, 1);
    expect(Cache::has(RequestSiteSitemapRegenerationAction::pendingKey((int) $site->getKey())))->toBeTrue();
});
