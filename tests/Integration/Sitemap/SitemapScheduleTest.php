<?php

declare(strict_types=1);

use Capell\SiteDiscovery\Providers\SiteDiscoveryServiceProvider;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;

uses(SiteDiscoveryTestCase::class);

it('does not schedule incremental sitemap regeneration by default', function (): void {
    config()->set('capell-site-discovery.incremental_sitemap_schedule.enabled', false);

    $schedule = new Schedule;
    app()->instance(Schedule::class, $schedule);

    bootSiteDiscoveryProvider();

    $event = siteDiscoveryIncrementalSitemapScheduleEvent($schedule);

    expect($event)->toBeNull();
});

it('registers the opt-in incremental sitemap schedule', function (): void {
    config()->set('capell-site-discovery.incremental_sitemap_schedule.enabled', true);
    config()->set('capell-site-discovery.incremental_sitemap_schedule.frequency', 'dailyAt');
    config()->set('capell-site-discovery.incremental_sitemap_schedule.daily_at', '02:30');
    config()->set('capell-site-discovery.incremental_sitemap_schedule.overlap_expires_after_minutes', 90);

    $schedule = new Schedule;
    app()->instance(Schedule::class, $schedule);

    bootSiteDiscoveryProvider();

    $event = siteDiscoveryIncrementalSitemapScheduleEvent($schedule);

    throw_unless($event instanceof ScheduledEvent, RuntimeException::class, 'Expected incremental sitemap schedule to be registered.');

    expect((string) $event->command)->toContain('capell:xml-sitemap')
        ->toContain('--incremental')
        ->and($event->description)->toBe('capell-site-discovery:incremental-sitemap')
        ->and($event->expression)->toBe('30 2 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(90)
        ->and($event->onOneServer)->toBeTrue();
});

it('allows cron-based incremental sitemap scheduling', function (): void {
    config()->set('capell-site-discovery.incremental_sitemap_schedule.enabled', true);
    config()->set('capell-site-discovery.incremental_sitemap_schedule.frequency', 'cron');
    config()->set('capell-site-discovery.incremental_sitemap_schedule.cron', '17 */6 * * *');

    $schedule = new Schedule;
    app()->instance(Schedule::class, $schedule);

    bootSiteDiscoveryProvider();

    $event = siteDiscoveryIncrementalSitemapScheduleEvent($schedule);

    throw_unless($event instanceof ScheduledEvent, RuntimeException::class, 'Expected incremental sitemap schedule to be registered.');

    expect($event->expression)->toBe('17 */6 * * *');
});

function siteDiscoveryIncrementalSitemapScheduleEvent(Schedule $schedule): ?ScheduledEvent
{
    $event = collect($schedule->events())
        ->first(fn (mixed $scheduledEvent): bool => $scheduledEvent instanceof ScheduledEvent
            && $scheduledEvent->description === 'capell-site-discovery:incremental-sitemap');

    return $event instanceof ScheduledEvent ? $event : null;
}

function bootSiteDiscoveryProvider(): void
{
    $provider = new SiteDiscoveryServiceProvider(app());
    $provider->registeringPackage();
    $provider->callBootedCallbacks();
}
