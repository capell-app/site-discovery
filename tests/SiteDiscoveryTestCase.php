<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Tests;

use Capell\Admin\Providers\AdminServiceProvider;
use Capell\Admin\Providers\Filament\AdminPanelProvider;
use Capell\Core\Facades\CapellCore;
use Capell\DiscoveryFoundation\Providers\DiscoveryFoundationServiceProvider;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Frontend\Providers\FrontendServiceProvider;
use Capell\Frontend\Support\State\FrontendState;
use Capell\Navigation\Providers\NavigationServiceProvider;
use Capell\SiteDiscovery\Providers\SiteDiscoveryServiceProvider;
use Capell\Tests\AbstractTestCase;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\ParallelTesting;
use Livewire\LivewireServiceProvider;
use MichalOravec\PaginateRoute\PaginateRouteServiceProvider;
use Override;

class SiteDiscoveryTestCase extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $testToken = getenv('TEST_TOKEN') ?: 'sequential';
        $storageToken = substr(hash('sha256', $testToken . '|' . getmypid()), 0, 16);

        ParallelTesting::resolveTokenUsing(static fn (): string => $storageToken);
    }

    protected function getPackageServiceName(): string
    {
        return 'capell-site-discovery';
    }

    /**
     * @return class-string[]
     */
    #[Override]
    protected function getPackageProviders(mixed $app): array
    {
        return [
            ...parent::getPackageProviders($app),
            AdminServiceProvider::class,
            AdminPanelProvider::class,
            DiscoveryFoundationServiceProvider::class,
            SiteDiscoveryServiceProvider::class,
            FrontendServiceProvider::class,
            LivewireServiceProvider::class,
            NavigationServiceProvider::class,
            PaginateRouteServiceProvider::class,
        ];
    }

    #[Override]
    protected function getEnvironmentSetUp(mixed $app): void
    {
        parent::getEnvironmentSetUp($app);

        $app->scoped(FrontendState::class, fn (): FrontendState => new FrontendState);
        $app->scoped(FrontendContextReader::class, fn (Application $application): FrontendState => $application->make(FrontendState::class));
        CapellCore::forcePackageInstalled(AdminServiceProvider::$packageName);
        CapellCore::registerPackage(
            FrontendServiceProvider::$packageName,
            path: realpath(__DIR__ . '/../../frontend') ?: null,
        );
        CapellCore::forcePackageInstalled(FrontendServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(SiteDiscoveryServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(DiscoveryFoundationServiceProvider::$packageName);

        CapellCore::registerPackage(
            NavigationServiceProvider::$packageName,
            path: realpath(__DIR__ . '/../../navigation') ?: null,
        );
        CapellCore::forcePackageInstalled(NavigationServiceProvider::$packageName);
    }
}
