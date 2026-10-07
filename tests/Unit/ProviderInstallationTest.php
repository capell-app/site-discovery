<?php

declare(strict_types=1);

use Capell\Admin\Contracts\Extenders\SiteHeaderActionExtender;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\InstalledRuntimeLifecycle;
use Capell\DiscoveryFoundation\Providers\DiscoveryFoundationServiceProvider;
use Capell\SiteDiscovery\Filament\Extenders\Site\SitemapSiteHeaderActionExtender;
use Capell\SiteDiscovery\Providers\SiteDiscoveryServiceProvider;
use Capell\Tests\Support\PackageInstallationTestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;

it('registers installed runtime once during in-process installation', function (): void {
    /** @return list<class-string<SitemapSiteHeaderActionExtender>> */
    $surface = static function (Application $app): array {
        $contributions = [];
        foreach ($app->tagged(SiteHeaderActionExtender::TAG) as $contribution) {
            if ($contribution instanceof SitemapSiteHeaderActionExtender) {
                $contributions[] = $contribution::class;
            }
        }

        return $contributions;
    };

    $fresh = [];
    PackageInstallationTestCase::assertFreshInstalledBoot('site-discovery', static function (Application $app) use ($surface, &$fresh): void {
        $app->register(DiscoveryFoundationServiceProvider::class);
        CapellCore::forcePackageInstalled(DiscoveryFoundationServiceProvider::$packageName);
        $app->make(InstalledRuntimeLifecycle::class)->refreshForBootstrap(SiteDiscoveryServiceProvider::$packageName);
        $fresh = $surface($app);
        expect($fresh)->toBe([SitemapSiteHeaderActionExtender::class]);
    });

    PackageInstallationTestCase::assertInProcessInstallation('site-discovery', static function (Application $app, Closure $refresh) use ($surface, $fresh): void {
        expect($surface($app))->toBe([]);
        $app->register(DiscoveryFoundationServiceProvider::class);
        CapellCore::forcePackageInstalled(DiscoveryFoundationServiceProvider::$packageName);
        $refresh();
        $app->make(InstalledRuntimeLifecycle::class)->refreshForBootstrap(SiteDiscoveryServiceProvider::$packageName);
        expect($surface($app))->toBe($fresh);

        $schedule = $app->make(Schedule::class);
        $scheduledEvents = $schedule->events();
        $listeners = $app->make(Dispatcher::class)->getRawListeners();
        $refresh();
        expect($surface($app))->toBe($fresh)
            ->and($app->make(Dispatcher::class)->getRawListeners())->toBe($listeners)
            ->and($schedule->events())->toBe($scheduledEvents);
    });
});
