<?php

declare(strict_types=1);

use Capell\SiteDiscovery\Enums\SitemapCacheKey;
use Capell\SiteDiscovery\Jobs\RebuildAllSitemapsJob;
use Capell\SiteDiscovery\Livewire\Tools\SitemapTool;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Capell\Tests\Fixtures\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

uses(SiteDiscoveryTestCase::class);

it('requires a global admin before queueing sitemap generation', function (): void {
    siteDiscoverySitemapToolAuthReturning(User::factory()->create());

    expect(fn () => (new SitemapTool)->generate())
        ->toThrow(AuthorizationException::class);
});

it('queues the sitemap rebuild without scanning sites or deleting files in the Livewire request', function (): void {
    app()->register(BusServiceProvider::class);
    Bus::fake();
    Cache::forget(SitemapCacheKey::Generating->value);

    $user = User::factory()->create();
    $user->assignRole('super_admin');
    siteDiscoverySitemapToolAuthReturning($user);

    (new SitemapTool)->generate();

    Bus::assertDispatched(RebuildAllSitemapsJob::class);

    expect(Cache::get(SitemapCacheKey::Generating->value))->toBe('queued');
});

function siteDiscoverySitemapToolAuthReturning(?User $user): void
{
    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('user')->andReturn($user);

    Filament::shouldReceive('auth')->andReturn($guard);
}
