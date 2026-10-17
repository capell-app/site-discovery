<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;
use Illuminate\Foundation\Application;

it('matches fresh installed boot after in-process installation and repeated refresh', function (): void {
    InstalledRuntimeParity::assertPackage('site-discovery');
});

it('makes the translated default sitemap available before application boot completes', function (): void {
    InstalledRuntimeState::run(static function (): void {
        InstalledRuntimeTestCase::boot(InstalledRuntimeParity::manifest('site-discovery'), true, static function (Application $app): void {
            $app->booted(static function (): void {
                expect(CapellCore::getDefaultPage('sitemap')->label)->toBe('Sitemap');
            });
        }, sourceRoot: dirname(__DIR__, 3));
    });
});
