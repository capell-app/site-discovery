<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Health;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\SiteDiscovery\Contracts\PublicUrlContributor;
use Capell\SiteDiscovery\Data\SitemapUrlItemData;
use Capell\SiteDiscovery\Support\PublicUrls\CmsPagePublicUrlContributor;
use Capell\SiteDiscovery\Support\Sitemap\Pages\PagesSitemap;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPageRegistry;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPageType;
use Capell\SiteDiscovery\Support\Sitemap\SitemapStateStore;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Throwable;

final class SiteDiscoveryHealthCheck implements ChecksExtensionHealth
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }

    /**
     * @return Collection<int, DoctorCheckResultData>
     */
    public static function runDiagnostics(): Collection
    {
        $check = new self;

        return collect([
            $check->publicUrlContributorCheck(),
            $check->xmlSitemapStorageCheck(),
            $check->incrementalStateCheck(),
            $check->htmlSitemapCheck(),
        ]);
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()
            ->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    public function publicUrlContributorCheck(): DoctorCheckResultData
    {
        $isRegistered = $this->cmsPagePublicUrlContributorIsRegistered();

        return new DoctorCheckResultData(
            label: 'Site Discovery public URL contributor',
            passed: $isRegistered,
            message: $isRegistered
                ? 'The CMS page public URL contributor is registered for site and language scoped discovery.'
                : 'The CMS page public URL contributor is not registered.',
            remediation: $isRegistered
                ? null
                : 'Ensure SiteDiscoveryServiceProvider tags CmsPagePublicUrlContributor with PublicUrlContributor::TAG.',
        );
    }

    public function xmlSitemapStorageCheck(): DoctorCheckResultData
    {
        $isReady = $this->xmlSitemapStorageIsConfigured();

        return new DoctorCheckResultData(
            label: 'Site Discovery XML sitemap storage',
            passed: $isReady,
            message: $isReady
                ? 'The XML sitemap generator resolves and the configured sitemap disk and directory are usable.'
                : 'The XML sitemap generator or configured sitemap disk and directory are not usable.',
            remediation: $isReady
                ? null
                : 'Check capell.sitemap.disk and capell.sitemap.directory, then ensure the configured filesystem disk is available.',
        );
    }

    public function incrementalStateCheck(): DoctorCheckResultData
    {
        $isWorking = $this->incrementalStateComparisonWorks();

        return new DoctorCheckResultData(
            label: 'Site Discovery incremental sitemap state',
            passed: $isWorking,
            message: $isWorking
                ? 'Incremental sitemap state detects changed URL maps and skips unchanged maps.'
                : 'Incremental sitemap state comparison did not distinguish changed and unchanged URL maps.',
            remediation: $isWorking
                ? null
                : 'Check SitemapStateStore URL map and change-detection behaviour.',
        );
    }

    public function htmlSitemapCheck(): DoctorCheckResultData
    {
        $isRegistered = $this->htmlSitemapIsRegistered();

        return new DoctorCheckResultData(
            label: 'Site Discovery HTML sitemap wiring',
            passed: $isRegistered,
            message: $isRegistered
                ? 'The HTML sitemap page registry and Livewire component are registered.'
                : 'The HTML sitemap page registry or Livewire component is not registered.',
            remediation: $isRegistered
                ? null
                : 'Ensure SiteDiscoveryServiceProvider registers the default sitemap page and site.sitemap Livewire component.',
        );
    }

    public function cmsPagePublicUrlContributorIsRegistered(): bool
    {
        return collect(app()->tagged(PublicUrlContributor::TAG))
            ->contains(static fn (mixed $contributor): bool => $contributor instanceof CmsPagePublicUrlContributor);
    }

    public function xmlSitemapStorageIsConfigured(): bool
    {
        $disk = config('capell.sitemap.disk', 'local');
        $directory = config('capell.sitemap.directory', 'sitemaps');

        if (! is_string($disk) || $disk === '' || ! is_string($directory) || trim($directory, '/') === '') {
            return false;
        }

        try {
            if (! resolve(XmlSitemapGenerator::class) instanceof XmlSitemapGenerator) {
                return false;
            }

            $storage = Storage::disk($disk);
            $probePath = trim($directory, '/') . '/.site-discovery-health-check';

            if (! $storage->put($probePath, 'ok')) {
                return false;
            }

            $exists = $storage->exists($probePath);
            $storage->delete($probePath);

            return $exists;
        } catch (Throwable) {
            return false;
        }
    }

    public function incrementalStateComparisonWorks(): bool
    {
        $state = new SitemapStateStore('local', 'sitemaps');
        $unchangedMap = $state->buildUrlMap([
            new SitemapUrlItemData(
                loc: 'https://example.test/',
                lastmod: CarbonImmutable::parse('2026-06-03T00:00:00+00:00'),
            ),
        ]);
        $changedMap = [
            'https://example.test/' => '2026-06-04T00:00:00+00:00',
        ];

        return ! $state->hasChanged($unchangedMap, $unchangedMap)
            && $state->hasChanged($changedMap, $unchangedMap);
    }

    public function htmlSitemapIsRegistered(): bool
    {
        try {
            $registry = resolve(SitemapPageRegistry::class);

            return ($registry->all()['default'] ?? null) === PagesSitemap::class
                && Livewire::exists(SitemapPageType::ComponentView);
        } catch (Throwable) {
            return false;
        }
    }
}
