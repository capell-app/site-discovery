<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Enums\SitemapCacheKey;
use Capell\SiteDiscovery\Livewire\Tools\SitemapTool;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Capell\Tests\Fixtures\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

uses(SiteDiscoveryTestCase::class);

final class SiteDiscoverySitemapToolFakeXmlSitemapGenerator extends XmlSitemapGenerator
{
    /** @var list<int> */
    public array $deletedSiteIds = [];

    #[Override]
    public function delete(Site $site): void
    {
        $this->deletedSiteIds[] = (int) $site->getKey();
    }
}

it('requires a global admin before queueing sitemap generation', function (): void {
    siteDiscoverySitemapToolAuthReturning(User::factory()->create());

    expect(fn () => (new SitemapTool)->generate())
        ->toThrow(AuthorizationException::class);
});

it('deletes stale sitemap files and queues generation for enabled sites', function (): void {
    app()->register(BusServiceProvider::class);
    Bus::fake();
    Cache::forget(SitemapCacheKey::Generating->value);

    $user = User::factory()->create();
    $user->assignRole('super_admin');
    siteDiscoverySitemapToolAuthReturning($user);

    $firstSite = Site::factory()->default()->withTranslations()->create(['name' => 'Primary']);
    $secondSite = Site::factory()->withTranslations()->create(['name' => 'Secondary']);
    $disabledSite = Site::factory()->disabled()->withTranslations()->create(['name' => 'Disabled']);
    $generator = new SiteDiscoverySitemapToolFakeXmlSitemapGenerator;

    app()->instance(XmlSitemapGenerator::class, $generator);

    (new SitemapTool)->generate();

    expect($generator->deletedSiteIds)->toContain($firstSite->getKey(), $secondSite->getKey())
        ->and($generator->deletedSiteIds)->not->toContain($disabledSite->getKey())
        ->and(Cache::get(SitemapCacheKey::Generating->value))->toBe(2);
});

function siteDiscoverySitemapToolAuthReturning(?User $user): void
{
    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('user')->andReturn($user);

    Filament::shouldReceive('auth')->andReturn($guard);
}
