<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Enums\SitemapCacheKey;
use Capell\SiteDiscovery\Jobs\RebuildAllSitemapsJob;
use Capell\SiteDiscovery\Jobs\RebuildSiteSitemapJob;
use Capell\SiteDiscovery\Tests\Fixtures\SiteDiscoverySitemapToolFakeXmlSitemapGenerator;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

uses(SiteDiscoveryTestCase::class);

it('fans enabled sites out into bounded durable rebuild jobs', function (): void {
    app()->register(BusServiceProvider::class);
    Bus::fake();

    $firstSite = Site::factory()->default()->withTranslations()->create(['name' => 'Primary']);
    $secondSite = Site::factory()->withTranslations()->create(['name' => 'Secondary']);
    $disabledSite = Site::factory()->disabled()->withTranslations()->create(['name' => 'Disabled']);
    (new RebuildAllSitemapsJob)->handle();

    Bus::assertDispatched(RebuildSiteSitemapJob::class, 2);
    Bus::assertDispatched(RebuildSiteSitemapJob::class, fn (RebuildSiteSitemapJob $job): bool => $job->siteId === $firstSite->getKey());
    Bus::assertDispatched(RebuildSiteSitemapJob::class, fn (RebuildSiteSitemapJob $job): bool => $job->siteId === $secondSite->getKey());
    Bus::assertNotDispatched(RebuildSiteSitemapJob::class, fn (RebuildSiteSitemapJob $job): bool => $job->siteId === $disabledSite->getKey());

    expect(Cache::get(SitemapCacheKey::Generating->value))->toBe(2);
});

it('clears the progress marker when the rebuild job fails', function (): void {
    Cache::put(SitemapCacheKey::Generating->value, 'queued');

    (new RebuildAllSitemapsJob)->failed(new RuntimeException('filesystem unavailable'));

    expect(Cache::has(SitemapCacheKey::Generating->value))->toBeFalse();
});

it('deletes one sites stale files inside its bounded rebuild job', function (): void {
    app()->register(BusServiceProvider::class);
    Bus::fake();
    $site = Site::factory()->default()->withTranslations()->create();
    $generator = new SiteDiscoverySitemapToolFakeXmlSitemapGenerator;

    (new RebuildSiteSitemapJob((int) $site->getKey()))->handle($generator);

    expect($generator->deletedSiteIds)->toBe([(int) $site->getKey()]);
});
