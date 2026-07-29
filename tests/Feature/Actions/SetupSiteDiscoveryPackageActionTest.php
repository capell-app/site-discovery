<?php

declare(strict_types=1);

use Capell\Core\Data\PackageData;
use Capell\Core\Enums\PackageTypeEnum;
use Capell\Core\Events\CapellInstalled;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Actions\SetupSiteDiscoveryPackageAction;
use Capell\SiteDiscovery\Providers\SiteDiscoveryServiceProvider;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPageType;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;

uses(SiteDiscoveryTestCase::class);

it('backfills one sitemap page and URL per language idempotently', function (): void {
    $languages = Language::factory()->count(2)->create();
    $site = Site::factory()->withTranslations($languages)->create();
    $package = new PackageData(
        name: SiteDiscoveryServiceProvider::$packageName,
        type: PackageTypeEnum::Plugin,
        serviceProviderClass: SiteDiscoveryServiceProvider::class,
        path: dirname(__DIR__, 3),
    );

    SetupSiteDiscoveryPackageAction::run($package);
    SetupSiteDiscoveryPackageAction::run($package);

    $sitemapPages = Page::query()
        ->where('site_id', $site->id)
        ->whereHas('blueprint', static fn (Builder $blueprintQuery): Builder => $blueprintQuery->where('key', SitemapPageType::Key))
        ->get();
    $sitemapPage = $sitemapPages->sole();
    $siteLanguageCount = $site->languages()->count();
    $pageUrls = PageUrl::query()->where('pageable_id', $sitemapPage->id)->where('pageable_type', $sitemapPage->getMorphClass())->get();
    $aliasCount = PageUrl::query()
        ->where('pageable_id', $sitemapPage->id)
        ->where('pageable_type', $sitemapPage->getMorphClass())
        ->where('type', 'alias')
        ->count();

    expect($sitemapPages)->toHaveCount(1)
        ->and($sitemapPage->translations)->toHaveCount($siteLanguageCount)
        ->and($aliasCount)->toBe($siteLanguageCount)
        ->and($pageUrls)->toHaveCount($siteLanguageCount * 2);
});

it('declares the setup lifecycle action in the public manifest', function (): void {
    /** @var array{actions: array{setup: class-string}} $manifest */
    $manifest = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3) . '/capell.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest['actions']['setup'])->toBe(SetupSiteDiscoveryPackageAction::class);
});

it('backfills sitemap pages after a spec creates sites at install finalization', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->withTranslations($language)->create();

    Event::dispatch(new CapellInstalled('/tmp/release-confidence-site-spec.json', true));
    Event::dispatch(new CapellInstalled('/tmp/release-confidence-site-spec.json', true));

    expect(Page::query()
        ->where('site_id', $site->id)
        ->whereHas('blueprint', static fn (Builder $blueprintQuery): Builder => $blueprintQuery->where('key', SitemapPageType::Key))
        ->count())->toBe(1);
});
