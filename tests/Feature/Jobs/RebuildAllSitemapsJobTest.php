<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Actions\GenerateSitemapAction;
use Capell\SiteDiscovery\Enums\SitemapCacheKey;
use Capell\SiteDiscovery\Jobs\RebuildAllSitemapsJob;
use Capell\SiteDiscovery\Jobs\RebuildSiteSitemapJob;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

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

it('queues atomic generation without deleting the currently served set', function (): void {
    app()->register(BusServiceProvider::class);
    Queue::fake();
    $site = Site::factory()->default()->withTranslations()->create();

    (new RebuildSiteSitemapJob((int) $site->getKey()))->handle();

    GenerateSitemapAction::assertPushed(
        callback: static fn (GenerateSitemapAction $action, array $parameters): bool => ($parameters[0] ?? null) instanceof Site
            && $parameters[0]->is($site),
    );
});
