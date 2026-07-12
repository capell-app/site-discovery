<?php

declare(strict_types=1);

use Capell\Core\Actions\PageSavedAction;
use Capell\Core\Actions\SiteCreatedAction;
use Capell\Core\Events\PageDeleted;
use Capell\Core\Events\PageSaved;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Jobs\RegenerateSiteSitemapIncrementallyJob;
use Capell\SiteDiscovery\Listeners\Sitemap\RegenerateSitemapsOnPageDeleted;
use Capell\SiteDiscovery\Listeners\Sitemap\RegenerateSitemapsOnPageSaved;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
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

    Bus::assertDispatched(
        RegenerateSiteSitemapIncrementallyJob::class,
        static fn (RegenerateSiteSitemapIncrementallyJob $job): bool => $job->siteId === $site->id,
    );
});

it('regenerates the new site sitemap after site creation workflow runs', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->language($language)->create();

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
