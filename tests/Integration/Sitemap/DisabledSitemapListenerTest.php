<?php

declare(strict_types=1);

use Capell\Core\Events\PageDeleted;
use Capell\Core\Events\PageSaved;
use Capell\Core\Events\SiteCreated;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\RequestSiteSitemapRegenerationAction;
use Capell\SiteDiscovery\Jobs\RegenerateSiteSitemapIncrementallyJob;
use Capell\SiteDiscovery\Listeners\Sitemap\RegenerateSitemapsOnPageDeleted;
use Capell\SiteDiscovery\Listeners\Sitemap\RegenerateSitemapsOnPageSaved;
use Capell\SiteDiscovery\Listeners\Sitemap\RegenerateSitemapsOnSiteCreated;
use Capell\SiteDiscovery\Providers\SiteDiscoveryServiceProvider;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Events\CallQueuedListener;

use function Illuminate\Support\defer;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(SiteDiscoveryTestCase::class);

it('queues no page sitemap listener while Site Discovery is disabled, and queues once after re-enable', function (string $listener): void {
    $site = Site::factory()->withTranslations()->create();
    $page = Page::factory()->site($site)->withTranslations()->create();
    Queue::fake();
    $dispatch = static fn (): mixed => $listener === RegenerateSitemapsOnPageSaved::class
        ? event(new PageSaved($page, ['title' => 'Updated title']))
        : event(new PageDeleted($page, ['reason' => 'admin cleanup']));
    $pushed = static fn (CallQueuedListener $job): bool => $job->class === $listener;

    CapellCore::markPackageDisabled(SiteDiscoveryServiceProvider::$packageName);
    $dispatch();

    Queue::assertNotPushed(CallQueuedListener::class, $pushed);

    CapellCore::markPackageInstalled(SiteDiscoveryServiceProvider::$packageName);
    $dispatch();

    $matches = 0;
    Queue::assertPushed(CallQueuedListener::class, static function (CallQueuedListener $job) use ($pushed, &$matches): bool {
        $matches += $pushed($job) ? 1 : 0;

        return $pushed($job);
    });
    expect($matches)->toBe(1);
})->with([RegenerateSitemapsOnPageSaved::class, RegenerateSitemapsOnPageDeleted::class]);

it('lets an already-queued page sitemap job do nothing after Site Discovery is disabled, and regenerate after re-enable', function (string $listener): void {
    app()->register(BusServiceProvider::class);
    Bus::fake();
    Cache::flush();

    CapellCore::markPackageDisabled(SiteDiscoveryServiceProvider::$packageName);
    $site = Site::factory()->withTranslations()->create();
    $page = Page::factory()->site($site)->withTranslations()->create();
    $run = static fn (): mixed => $listener === RegenerateSitemapsOnPageSaved::class
        ? (new RegenerateSitemapsOnPageSaved)->handle(new PageSaved($page, ['title' => 'Updated title']))
        : (new RegenerateSitemapsOnPageDeleted)->handle(new PageDeleted($page, ['reason' => 'admin cleanup']));
    $run();
    defer()->invoke();

    Bus::assertNotDispatched(RegenerateSiteSitemapIncrementallyJob::class);
    Bus::assertNotDispatchedAfterResponse(RegenerateSiteSitemapIncrementallyJob::class);
    expect(Cache::has(RequestSiteSitemapRegenerationAction::pendingKey($site->id)))->toBeFalse();

    CapellCore::markPackageInstalled(SiteDiscoveryServiceProvider::$packageName);
    $run();
    defer()->invoke();

    Bus::assertDispatchedTimes(RegenerateSiteSitemapIncrementallyJob::class, 1);
})->with([RegenerateSitemapsOnPageSaved::class, RegenerateSitemapsOnPageDeleted::class]);

it('generates no new-site sitemap while Site Discovery is disabled, and generates once after re-enable', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->language($language)->withTranslations($language)->create();
    $generator = new class extends XmlSitemapGenerator
    {
        /** @var list<int> */
        public array $incrementalSiteIds = [];

        public function processIncremental(Site $site, ?Closure $start = null, ?Closure $prepare = null, ?Closure $checkpoint = null, ?Closure $end = null): void
        {
            $this->incrementalSiteIds[] = $site->id;
        }
    };
    $listener = new RegenerateSitemapsOnSiteCreated($generator);
    $event = new SiteCreated($site, ['name' => 'Example Site', 'language_id' => $language->id]);

    CapellCore::markPackageDisabled(SiteDiscoveryServiceProvider::$packageName);

    expect($listener->shouldQueue($event))->toBeFalse();
    $listener->handle($event);
    expect($generator->incrementalSiteIds)->toBe([]);

    CapellCore::markPackageInstalled(SiteDiscoveryServiceProvider::$packageName);

    expect($listener->shouldQueue($event))->toBeTrue();
    $listener->handle($event);
    expect($generator->incrementalSiteIds)->toBe([$site->id]);
});

it('requests no sitemap for a new domain while Site Discovery is disabled, and requests once after re-enable', function (): void {
    app()->register(BusServiceProvider::class);
    Bus::fake();
    Cache::flush();

    $site = Site::factory()->language(Language::factory()->create())->create();

    CapellCore::markPackageDisabled(SiteDiscoveryServiceProvider::$packageName);
    SiteDomain::factory()->create(['site_id' => $site->id, 'language_id' => $site->language_id]);
    defer()->invoke();

    Bus::assertNotDispatched(RegenerateSiteSitemapIncrementallyJob::class);

    CapellCore::markPackageInstalled(SiteDiscoveryServiceProvider::$packageName);
    Cache::flush();
    SiteDomain::factory()->create(['site_id' => $site->id, 'language_id' => $site->language_id]);
    defer()->invoke();

    Bus::assertDispatchedTimes(RegenerateSiteSitemapIncrementallyJob::class, 1);
});
