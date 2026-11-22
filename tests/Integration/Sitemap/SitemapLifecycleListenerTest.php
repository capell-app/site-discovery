<?php

declare(strict_types=1);

use Capell\Core\Actions\PageSavedAction;
use Capell\Core\Actions\SiteCreatedAction;
use Capell\Core\Events\PageDeleted;
use Capell\Core\Events\PageSaved;
use Capell\Core\Events\SiteCreated;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\RequestSiteSitemapRegenerationAction;
use Capell\SiteDiscovery\Jobs\RegenerateSiteSitemapIncrementallyJob;
use Capell\SiteDiscovery\Listeners\Sitemap\RegenerateSitemapsOnPageDeleted;
use Capell\SiteDiscovery\Listeners\Sitemap\RegenerateSitemapsOnPageSaved;
use Capell\SiteDiscovery\Listeners\Sitemap\RegenerateSitemapsOnSiteCreated;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Events\CallQueuedListener;

use function Illuminate\Support\defer;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(SiteDiscoveryTestCase::class);

/**
 * @return XmlSitemapGenerator&object{incrementalSiteIds: list<int>}
 */
function seoSuiteLifecycleFakeXmlSitemapGenerator(): XmlSitemapGenerator
{
    return new class extends XmlSitemapGenerator
    {
        /** @var list<int> */
        public array $incrementalSiteIds = [];

        public function processIncremental(
            Site $site,
            ?Closure $start = null,
            ?Closure $prepare = null,
            ?Closure $checkpoint = null,
            ?Closure $end = null,
        ): void {
            $this->incrementalSiteIds[] = $site->id;
        }
    };
}

it('retries and serializes queued sitemap lifecycle listeners', function (
    RegenerateSitemapsOnPageDeleted|RegenerateSitemapsOnPageSaved|RegenerateSitemapsOnSiteCreated $listener,
    PageDeleted|PageSaved|SiteCreated $event,
): void {
    $middleware = $listener->middleware($event);

    expect($listener->tries)->toBe(3)
        ->and($listener->backoff)->toBe([10, 60])
        ->and($middleware)->toHaveCount(1)
        ->and($middleware[0]->key)->toBe('capell-site-discovery:sitemap-lifecycle:site:55')
        ->and($middleware[0]->releaseAfter)->toBe(10)
        ->and($middleware[0]->expiresAfter)->toBe(120)
        ->and($middleware[0]->shareKey)->toBeTrue();
})->with([
    fn (): array => [
        new RegenerateSitemapsOnPageDeleted,
        new PageDeleted((new Page)->forceFill(['site_id' => 55])),
    ],
    fn (): array => [
        new RegenerateSitemapsOnPageSaved,
        new PageSaved((new Page)->forceFill(['site_id' => 55])),
    ],
    fn (): array => [
        new RegenerateSitemapsOnSiteCreated(seoSuiteLifecycleFakeXmlSitemapGenerator()),
        new SiteCreated((new Site)->forceFill(['id' => 55]), []),
    ],
]);

it('reports terminal sitemap lifecycle failures', function (
    RegenerateSitemapsOnPageDeleted|RegenerateSitemapsOnPageSaved|RegenerateSitemapsOnSiteCreated $listener,
    PageDeleted|PageSaved|SiteCreated $event,
    int $expectedModelId,
): void {
    $exception = new RuntimeException('Sensitive database bindings.', 72);

    Log::shouldReceive('error')
        ->once()
        ->withArgs(static fn (string $message, array $context): bool => str_ends_with($message, 'failed permanently.')
            && in_array($expectedModelId, [
                $context['page_id'] ?? null,
                $context['site_id'] ?? null,
            ], true)
            && $context['exception_class'] === RuntimeException::class
            && $context['exception_code'] === 72
            && ! array_key_exists('exception', $context));

    $listener->failed($event, $exception);
})->with([
    fn (): array => [
        new RegenerateSitemapsOnPageDeleted,
        new PageDeleted((new Page)->forceFill(['id' => 42, 'site_id' => 55])),
        42,
    ],
    fn (): array => [
        new RegenerateSitemapsOnPageSaved,
        new PageSaved((new Page)->forceFill(['id' => 42, 'site_id' => 55])),
        42,
    ],
    fn (): array => [
        new RegenerateSitemapsOnSiteCreated(seoSuiteLifecycleFakeXmlSitemapGenerator()),
        new SiteCreated((new Site)->forceFill(['id' => 55]), []),
        55,
    ],
]);

it('queues the page saved sitemap listener when a page is saved', function (): void {
    Queue::fake();

    $site = Site::factory()->withTranslations()->create();
    $page = Page::factory()->site($site)->withTranslations()->create();

    PageSavedAction::run($page, ['title' => 'Updated title']);

    Queue::assertPushed(
        CallQueuedListener::class,
        static fn (CallQueuedListener $job): bool => $job->class === RegenerateSitemapsOnPageSaved::class,
    );
});

it('queues owning site sitemap regeneration from the page saved listener', function (): void {
    app()->register(BusServiceProvider::class);
    Bus::fake();
    Cache::flush();

    $site = Site::factory()->withTranslations()->create();
    $page = Page::factory()->site($site)->withTranslations()->create();

    (new RegenerateSitemapsOnPageSaved)->handle(new PageSaved($page, ['title' => 'Updated title']));

    defer()->invoke();
    Bus::assertDispatched(
        RegenerateSiteSitemapIncrementallyJob::class,
        static fn (RegenerateSiteSitemapIncrementallyJob $job): bool => $job->siteId === $site->id,
    );
});

it('queues the page deleted sitemap listener when a page is deleted', function (): void {
    Queue::fake();

    $site = Site::factory()->withTranslations()->create();
    $page = Page::factory()->site($site)->withTranslations()->create();

    event(new PageDeleted($page, ['reason' => 'admin cleanup']));

    Queue::assertPushed(
        CallQueuedListener::class,
        static fn (CallQueuedListener $job): bool => $job->class === RegenerateSitemapsOnPageDeleted::class,
    );
});

it('queues owning site sitemap regeneration from the page deleted listener', function (): void {
    app()->register(BusServiceProvider::class);
    Bus::fake();
    Cache::flush();

    $site = Site::factory()->withTranslations()->create();
    $page = Page::factory()->site($site)->withTranslations()->create();

    (new RegenerateSitemapsOnPageDeleted)->handle(new PageDeleted($page, ['reason' => 'admin cleanup']));

    defer()->invoke();
    Bus::assertDispatched(
        RegenerateSiteSitemapIncrementallyJob::class,
        static fn (RegenerateSiteSitemapIncrementallyJob $job): bool => $job->siteId === $site->id,
    );
});

it('regenerates the new site sitemap after site creation workflow runs', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->language($language)->withTranslations($language)->create();

    $generator = seoSuiteLifecycleFakeXmlSitemapGenerator();
    app()->instance(XmlSitemapGenerator::class, $generator);

    SiteCreatedAction::run($site, [
        'name' => 'Example Site',
        'language_id' => $language->id,
    ]);

    expect($generator->incrementalSiteIds)->toBe([$site->id]);
});

it('ignores saved page events when the page has no owning site', function (): void {
    $generator = seoSuiteLifecycleFakeXmlSitemapGenerator();
    $listener = new RegenerateSitemapsOnPageSaved;

    $listener->handle(new PageSaved(new Page));

    expect($generator->incrementalSiteIds)->toBe([]);
});

it('ignores deleted page events when the page has no owning site', function (): void {
    $generator = seoSuiteLifecycleFakeXmlSitemapGenerator();
    $listener = new RegenerateSitemapsOnPageDeleted;

    $listener->handle(new PageDeleted(new Page));

    expect($generator->incrementalSiteIds)->toBe([]);
});

it('skips sitemap generation for a site created before any domain exists', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->language($language)->create();

    expect($site->siteDomains()->exists())->toBeFalse();

    $listener = new RegenerateSitemapsOnSiteCreated(resolve(XmlSitemapGenerator::class));

    $listener->handle(new SiteCreated($site, ['name' => 'Example Site', 'language_id' => $language->id]));

    expect($site->siteDomains()->exists())->toBeFalse();
});

it('queues sitemap regeneration when a domain is attached to a site', function (): void {
    app()->register(BusServiceProvider::class);
    Bus::fake();
    Cache::flush();

    $language = Language::factory()->create();
    $site = Site::factory()->language($language)->create();

    SiteDomain::factory()->create([
        'site_id' => $site->id,
        'language_id' => $language->id,
    ]);

    defer()->invoke();
    Bus::assertDispatched(
        RegenerateSiteSitemapIncrementallyJob::class,
        static fn (RegenerateSiteSitemapIncrementallyJob $job): bool => $job->siteId === $site->id,
    );
});

it('coalesces sitemap regeneration when several domains are attached at once', function (): void {
    app()->register(BusServiceProvider::class);
    Bus::fake();
    Cache::flush();

    $language = Language::factory()->create();
    $site = Site::factory()->language($language)->create();

    SiteDomain::factory()->count(3)->create([
        'site_id' => $site->id,
        'language_id' => $language->id,
    ]);

    defer()->invoke();
    Bus::assertDispatchedTimes(RegenerateSiteSitemapIncrementallyJob::class, 1);
});

it('requests no sitemap for a page change in a site that has no domain yet', function (string $listener): void {
    app()->register(BusServiceProvider::class);
    Bus::fake();

    $site = Site::factory()->language(Language::factory()->create())->create();
    $page = Page::factory()->site($site)->create();
    expect($site->siteDomains()->exists())->toBeFalse();

    $listener === RegenerateSitemapsOnPageSaved::class
        ? (new RegenerateSitemapsOnPageSaved)->handle(new PageSaved($page, ['title' => 'Updated title']))
        : (new RegenerateSitemapsOnPageDeleted)->handle(new PageDeleted($page, ['reason' => 'admin cleanup']));

    defer()->invoke();
    Bus::assertNotDispatched(RegenerateSiteSitemapIncrementallyJob::class);

    // No page change claimed the debounce, so the first domain still requests the sitemap.
    SiteDomain::factory()->create(['site_id' => $site->id, 'language_id' => $site->language_id]);

    defer()->invoke();
    Bus::assertDispatchedTimes(RegenerateSiteSitemapIncrementallyJob::class, 1);
})->with([RegenerateSitemapsOnPageSaved::class, RegenerateSitemapsOnPageDeleted::class]);

it('saves pages before the site domain exists without a failing sitemap job', function (): void {
    Exceptions::fake();
    $log = Log::spy();
    $generator = seoSuiteLifecycleFakeXmlSitemapGenerator();
    app()->instance(XmlSitemapGenerator::class, $generator);

    // A site's pages can be saved before its domain is attached, as site creation does.
    $site = Site::factory()->language(Language::factory()->create())->create();
    $page = Page::factory()->site($site)->create();
    PageSavedAction::run($page, ['title' => 'Updated title']);

    Exceptions::assertNothingReported();
    $log->shouldNotHaveReceived('error');
    expect($generator->incrementalSiteIds)->toBe([]);

    SiteDomain::factory()->create(['site_id' => $site->id, 'language_id' => $site->language_id]);
    defer()->invoke();

    expect($generator->incrementalSiteIds)->toBe([$site->id]);
});

it('regenerates a site sitemap once for a burst of page saves on an inline queue', function (): void {
    config()->set('queue.default', 'sync');
    $generator = seoSuiteLifecycleFakeXmlSitemapGenerator();
    app()->instance(XmlSitemapGenerator::class, $generator);
    $site = Site::factory()->language(Language::factory()->create())->create();
    SiteDomain::factory()->create(['site_id' => $site->id, 'language_id' => $site->language_id]);

    foreach (Page::factory()->site($site)->count(5)->create() as $page) {
        PageSavedAction::run($page, ['title' => 'Updated title']);
    }

    expect($generator->incrementalSiteIds)->toBe([]);

    defer()->invoke();

    expect($generator->incrementalSiteIds)->toBe([$site->id]);
});

it('regenerates an inline queued sitemap once, not again each time the application terminates', function (): void {
    config()->set('queue.default', 'sync');
    $generator = seoSuiteLifecycleFakeXmlSitemapGenerator();
    app()->instance(XmlSitemapGenerator::class, $generator);
    $site = Site::factory()->language(Language::factory()->create())->create();
    SiteDomain::factory()->create(['site_id' => $site->id, 'language_id' => $site->language_id]);
    PageSavedAction::run(Page::factory()->site($site)->create(), ['title' => 'Updated title']);

    // The save's request ends, then two later requests end in the same application.
    defer()->invoke();
    app()->terminate();
    app()->terminate();

    expect($generator->incrementalSiteIds)->toBe([$site->id]);
});

it('keeps the delayed, debounced dispatch for a burst of requests on an asynchronous queue', function (): void {
    // A faked queue holds jobs for later, as an asynchronous connection does, instead of running them inline.
    Queue::fake();
    Bus::fake([RegenerateSiteSitemapIncrementallyJob::class]);
    $site = Site::factory()->language(Language::factory()->create())->create();
    SiteDomain::factory()->create(['site_id' => $site->id, 'language_id' => $site->language_id]);

    foreach (range(1, 5) as $ignored) {
        RequestSiteSitemapRegenerationAction::run($site);
    }

    app()->terminate();

    Bus::assertDispatchedTimes(RegenerateSiteSitemapIncrementallyJob::class, 1);
    Bus::assertDispatched(
        RegenerateSiteSitemapIncrementallyJob::class,
        static fn (RegenerateSiteSitemapIncrementallyJob $job): bool => $job->siteId === $site->id && $job->delay !== null,
    );
    Bus::assertNotDispatchedAfterResponse(RegenerateSiteSitemapIncrementallyJob::class);
});

it('skips a queued regeneration when the site has no domain to publish', function (): void {
    $generator = seoSuiteLifecycleFakeXmlSitemapGenerator();
    app()->instance(XmlSitemapGenerator::class, $generator);
    $site = Site::factory()->language(Language::factory()->create())->create();

    (new RegenerateSiteSitemapIncrementallyJob($site->id))->handle();

    expect($generator->incrementalSiteIds)->toBe([]);
});
