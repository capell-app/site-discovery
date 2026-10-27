<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\DiscoveryFoundation\Contracts\PublicUrlContributor;
use Capell\DiscoveryFoundation\Data\PublicUrlData;
use Capell\SiteDiscovery\Data\PublicUrlRepairItemData;
use Capell\SiteDiscovery\Filament\Pages\PublicUrlRegistryPage;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(SiteDiscoveryTestCase::class, CreatesAdminUser::class);

it('labels stable site keys with names and domains and filters the original rows', function (): void {
    $this->actingAsAdmin();
    $first = Site::factory()->create(['id' => 31, 'name' => 'North studio']);
    $second = Site::factory()->create(['id' => 82, 'name' => 'South studio']);
    SiteDomain::factory()->create(['site_id' => $first->id, 'domain' => 'north.example']);
    SiteDomain::factory()->create(['site_id' => $second->id, 'domain' => 'south.example']);
    registrySiteLabelReport([31, 82, 999]);
    $page = new PublicUrlRegistryPage;
    expect($page->siteOptions()[31])->toContain('North studio', 'north.example')
        ->and($page->siteOptions()[82])->toContain('South studio', 'south.example')
        ->and($page->siteOptions()[999])->toBe(__('capell-site-discovery::generic.site_unavailable', ['key' => 999]));
    $page->queueView = PublicUrlRegistryPage::VIEW_ALL;
    $page->siteFilter = '82';

    expect(array_column(array_map(fn (PublicUrlRepairItemData $item): array => ['key' => $item->row->siteKey], $page->items()), 'key'))->toBe([82]);
    $page->clearFilters();
    expect($page->items())->toHaveCount(3);
});

it('does not disclose unassigned site labels or repair rows', function (): void {
    $allowed = Site::factory()->create(['id' => 31, 'name' => 'Allowed']);
    Site::factory()->create(['id' => 82, 'name' => 'Private']);
    Permission::findOrCreate('View:PublicUrlRegistryPage');
    $user = $this->createUserWithPermission('View:PublicUrlRegistryPage');
    $role = Role::findOrCreate('registry-site-member');
    DB::table('model_has_roles')->insert(['role_id' => $role->getKey(), 'model_type' => $user->getMorphClass(), 'model_id' => $user->getKey(), 'team_id' => $allowed->id]);
    $this->actingAs($user);
    registrySiteLabelReport([31, 82, 999]);
    $page = new PublicUrlRegistryPage;
    $page->queueView = PublicUrlRegistryPage::VIEW_ALL;
    expect(array_keys($page->siteOptions()))->toBe([31])
        ->and($page->items())->toHaveCount(1)
        ->and($page->queue()->totalUrls)->toBe(1);
});

/** @param list<int> $keys */
function registrySiteLabelReport(array $keys): void
{
    $language = Language::factory()->create();
    app()->instance('registry-label-source', new readonly class($keys, $language) implements PublicUrlContributor
    {
        /** @param list<int> $keys */
        public function __construct(private array $keys, private Language $language) {}

        /** @return Collection<int, PublicUrlData> */
        public function publicUrls(): Collection
        {
            return collect($this->keys)->map(fn (int $key): PublicUrlData => new PublicUrlData(canonicalUrl: 'https://example.com/' . $key, sourcePackage: 'test', site: Site::query()->find($key) ?? (new Site)->forceFill(['id' => $key]), language: $this->language));
        }
    });
    app()->tag(['registry-label-source'], PublicUrlContributor::TAG);
}
