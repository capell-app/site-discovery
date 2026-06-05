<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Health;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\SiteDiscovery\Actions\BuildSitemapXmlResponseAction;
use Capell\SiteDiscovery\Contracts\PublicUrlContributor;
use Capell\SiteDiscovery\Data\SitemapUrlItemData;
use Capell\SiteDiscovery\Http\Controllers\SitemapXmlController;
use Capell\SiteDiscovery\Support\PublicUrls\CmsPagePublicUrlContributor;
use Capell\SiteDiscovery\Support\Sitemap\AbstractSitemapPages;
use Capell\SiteDiscovery\Support\Sitemap\Pages\PagesSitemap;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPageRegistry;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPageType;
use Capell\SiteDiscovery\Support\Sitemap\SitemapStateStore;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouterRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Throwable;

final class SiteDiscoveryHealthCheck implements ChecksExtensionHealth
{
    private const string PUBLIC_URLS_KEY = 'site-discovery.public-urls';

    private const string XML_SITEMAPS_KEY = 'site-discovery.xml-sitemaps';

    private const string INCREMENTAL_KEY = 'site-discovery.incremental';

    private const string HTML_SITEMAP_KEY = 'site-discovery.html-sitemap';

    /**
     * @var array<int, string>
     */
    private const array INCREMENTAL_SCHEDULE_FREQUENCIES = [
        'everyFiveMinutes',
        'everyTenMinutes',
        'everyFifteenMinutes',
        'everyThirtyMinutes',
        'hourly',
        'dailyAt',
        'cron',
    ];

    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }

    /**
     * @return Collection<int, DoctorCheckResultData>
     */
    public static function runDiagnostics(?string $key = null): Collection
    {
        $check = new self;

        if (is_string($key) && $key !== '') {
            $result = $check->diagnosticForKey($key);

            return $result instanceof DoctorCheckResultData
                ? collect([$result])
                : collect();
        }

        return collect($check->allDiagnostics());
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()
            ->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    public function publicUrlContributorCheck(): DoctorCheckResultData
    {
        $status = $this->publicUrlContributorStatus();
        $isReady = $status['cmsPageContributorRegistered'] && $status['invalidContributors'] === [];

        return new DoctorCheckResultData(
            label: (string) __('capell-site-discovery::package.health.public_urls.label'),
            passed: $isReady,
            message: $isReady
                ? (string) __('capell-site-discovery::package.health.public_urls.passed', [
                    'count' => $status['validContributorCount'],
                ])
                : (string) __('capell-site-discovery::package.health.public_urls.failed', [
                    'count' => $status['validContributorCount'],
                    'invalid' => $status['invalidContributors'] === []
                        ? (string) __('capell-site-discovery::generic.none')
                        : implode(', ', $status['invalidContributors']),
                ]),
            remediation: $isReady
                ? null
                : (string) __('capell-site-discovery::package.health.public_urls.remediation'),
        );
    }

    public function xmlSitemapOutputCheck(): DoctorCheckResultData
    {
        $isReady = $this->xmlSitemapOutputIsReady();

        return new DoctorCheckResultData(
            label: (string) __('capell-site-discovery::package.health.xml_sitemaps.label'),
            passed: $isReady,
            message: $isReady
                ? (string) __('capell-site-discovery::package.health.xml_sitemaps.passed', [
                    'path' => '/' . $this->xmlSitemapPath(),
                ])
                : (string) __('capell-site-discovery::package.health.xml_sitemaps.failed', [
                    'path' => '/' . $this->xmlSitemapPath(),
                ]),
            remediation: $isReady
                ? null
                : (string) __('capell-site-discovery::package.health.xml_sitemaps.remediation'),
        );
    }

    public function xmlSitemapStorageCheck(): DoctorCheckResultData
    {
        return $this->xmlSitemapOutputCheck();
    }

    public function incrementalSitemapCheck(): DoctorCheckResultData
    {
        $isWorking = $this->incrementalSitemapIsReady();

        return new DoctorCheckResultData(
            label: (string) __('capell-site-discovery::package.health.incremental.label'),
            passed: $isWorking,
            message: $isWorking
                ? (string) __('capell-site-discovery::package.health.incremental.passed', [
                    'schedule' => config('capell-site-discovery.incremental_sitemap_schedule.enabled', false) === true
                        ? (string) __('capell-site-discovery::package.health.incremental.schedule_enabled')
                        : (string) __('capell-site-discovery::package.health.incremental.schedule_disabled'),
                ])
                : (string) __('capell-site-discovery::package.health.incremental.failed'),
            remediation: $isWorking
                ? null
                : (string) __('capell-site-discovery::package.health.incremental.remediation'),
        );
    }

    public function incrementalStateCheck(): DoctorCheckResultData
    {
        return $this->incrementalSitemapCheck();
    }

    public function htmlSitemapCheck(): DoctorCheckResultData
    {
        $isRegistered = $this->htmlSitemapTypeIsReady();

        return new DoctorCheckResultData(
            label: (string) __('capell-site-discovery::package.health.html_sitemap.label'),
            passed: $isRegistered,
            message: $isRegistered
                ? (string) __('capell-site-discovery::package.health.html_sitemap.passed')
                : (string) __('capell-site-discovery::package.health.html_sitemap.failed'),
            remediation: $isRegistered
                ? null
                : (string) __('capell-site-discovery::package.health.html_sitemap.remediation'),
        );
    }

    public function cmsPagePublicUrlContributorIsRegistered(): bool
    {
        return $this->publicUrlContributorStatus()['cmsPageContributorRegistered'];
    }

    public function publicUrlContributorsAreReady(): bool
    {
        $status = $this->publicUrlContributorStatus();

        return $status['cmsPageContributorRegistered'] && $status['invalidContributors'] === [];
    }

    /**
     * @return array{cmsPageContributorRegistered: bool, validContributorCount: int, invalidContributors: array<int, string>}
     */
    public function publicUrlContributorStatus(): array
    {
        try {
            $contributors = collect(app()->tagged(PublicUrlContributor::TAG));
        } catch (Throwable $throwable) {
            return [
                'cmsPageContributorRegistered' => false,
                'validContributorCount' => 0,
                'invalidContributors' => [$throwable::class],
            ];
        }

        return $contributors
            ->pipe(function (Collection $contributors): array {
                $validContributors = $contributors
                    ->filter(static fn (mixed $contributor): bool => $contributor instanceof PublicUrlContributor)
                    ->values();
                $invalidContributors = $contributors
                    ->reject(static fn (mixed $contributor): bool => $contributor instanceof PublicUrlContributor)
                    ->map(static fn (mixed $contributor): string => is_object($contributor) ? $contributor::class : get_debug_type($contributor))
                    ->values()
                    ->all();

                return [
                    'cmsPageContributorRegistered' => $validContributors
                        ->contains(static fn (PublicUrlContributor $contributor): bool => $contributor instanceof CmsPagePublicUrlContributor),
                    'validContributorCount' => $validContributors->count(),
                    'invalidContributors' => $invalidContributors,
                ];
            });
    }

    public function xmlSitemapOutputIsReady(): bool
    {
        return $this->xmlSitemapStorageIsConfigured()
            && $this->xmlSitemapRoutesAreRegistered()
            && $this->xmlSitemapResponseCanBeBuilt();
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

    public function xmlSitemapRoutesAreRegistered(): bool
    {
        $route = Route::getRoutes()->getByName('capell-frontend.sitemap-xml');
        $prefixedRoute = Route::getRoutes()->getByName('capell-frontend.sitemap-xml.prefixed');

        return $route instanceof RouterRoute
            && $prefixedRoute instanceof RouterRoute
            && trim($route->uri(), '/') === $this->xmlSitemapPath()
            && str_starts_with($route->getActionName(), SitemapXmlController::class)
            && str_starts_with($prefixedRoute->getActionName(), SitemapXmlController::class);
    }

    public function xmlSitemapResponseCanBeBuilt(): bool
    {
        $disk = config('capell.sitemap.disk', 'local');
        $directory = config('capell.sitemap.directory', 'sitemaps');

        if (! is_string($disk) || $disk === '' || ! is_string($directory) || trim($directory, '/') === '') {
            return false;
        }

        $storage = null;
        $filePath = trim($directory, '/') . '/http-example-test.xml';
        $createdProbeFile = false;

        try {
            $storage = Storage::disk($disk);
            $createdProbeFile = $storage->put(
                $filePath,
                '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
            );

            if (! $createdProbeFile) {
                return false;
            }

            $response = BuildSitemapXmlResponseAction::run(Request::create('http://example.test/' . $this->xmlSitemapPath()));

            return $response->getStatusCode() === 200
                && str_contains((string) $response->headers->get('Content-Type'), 'application/xml');
        } catch (Throwable) {
            return false;
        } finally {
            if ($createdProbeFile && $storage !== null) {
                $storage->delete($filePath);
            }
        }
    }

    public function incrementalSitemapIsReady(): bool
    {
        return $this->incrementalScheduleConfigurationIsValid()
            && $this->incrementalScheduleIsRegisteredWhenEnabled()
            && $this->incrementalStatePersistsAndCompares();
    }

    public function incrementalScheduleConfigurationIsValid(): bool
    {
        $enabled = config('capell-site-discovery.incremental_sitemap_schedule.enabled', false);
        $frequency = config('capell-site-discovery.incremental_sitemap_schedule.frequency', 'dailyAt');
        $dailyAt = config('capell-site-discovery.incremental_sitemap_schedule.daily_at', '02:30');
        $cron = config('capell-site-discovery.incremental_sitemap_schedule.cron');
        $overlapExpiresAfterMinutes = config('capell-site-discovery.incremental_sitemap_schedule.overlap_expires_after_minutes', 65);

        if (! is_bool($enabled) || ! is_string($frequency) || ! in_array($frequency, self::INCREMENTAL_SCHEDULE_FREQUENCIES, true)) {
            return false;
        }

        if (! is_int($overlapExpiresAfterMinutes) || $overlapExpiresAfterMinutes < 10) {
            return false;
        }

        if ($frequency === 'cron') {
            return is_string($cron) && trim($cron) !== '';
        }

        return is_string($dailyAt) && preg_match('/^\d{1,2}:\d{2}$/', $dailyAt) === 1;
    }

    public function incrementalScheduleIsRegisteredWhenEnabled(): bool
    {
        if (config('capell-site-discovery.incremental_sitemap_schedule.enabled', false) !== true) {
            return true;
        }

        try {
            return collect(resolve(Schedule::class)->events())
                ->contains(static fn (ScheduledEvent $event): bool => $event->description === 'capell-site-discovery:incremental-sitemap'
                    && str_contains((string) $event->command, 'capell:xml-sitemap')
                    && str_contains((string) $event->command, '--incremental'));
        } catch (Throwable) {
            return false;
        }
    }

    public function incrementalStatePersistsAndCompares(): bool
    {
        $disk = config('capell.sitemap.disk', 'local');
        $directory = config('capell.sitemap.directory', 'sitemaps');

        if (! is_string($disk) || $disk === '' || ! is_string($directory) || trim($directory, '/') === '') {
            return false;
        }

        $state = new SitemapStateStore($disk, trim($directory, '/'));
        $domainKey = 'health-check-' . bin2hex(random_bytes(8));
        $unchangedMap = $state->buildUrlMap([
            new SitemapUrlItemData(
                loc: 'https://example.test/',
                lastmod: CarbonImmutable::parse('2026-06-03T00:00:00+00:00'),
            ),
        ]);
        $changedMap = [
            'https://example.test/' => '2026-06-04T00:00:00+00:00',
        ];

        try {
            $state->save($domainKey, $unchangedMap);
            $loadedMap = $state->load($domainKey);

            return $loadedMap === $unchangedMap
                && ! $state->hasChanged($unchangedMap, $loadedMap)
                && $state->hasChanged($changedMap, $loadedMap);
        } catch (Throwable) {
            return false;
        } finally {
            $state->delete($domainKey);
        }
    }

    public function incrementalStateComparisonWorks(): bool
    {
        return $this->incrementalStatePersistsAndCompares();
    }

    public function htmlSitemapTypeIsReady(): bool
    {
        return $this->htmlSitemapIsRegistered()
            && is_subclass_of(PagesSitemap::class, AbstractSitemapPages::class);
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

    /**
     * @return array<int, DoctorCheckResultData>
     */
    private function allDiagnostics(): array
    {
        return [
            $this->publicUrlContributorCheck(),
            $this->xmlSitemapOutputCheck(),
            $this->incrementalSitemapCheck(),
            $this->htmlSitemapCheck(),
        ];
    }

    private function diagnosticForKey(string $key): ?DoctorCheckResultData
    {
        return match ($key) {
            self::PUBLIC_URLS_KEY => $this->publicUrlContributorCheck(),
            self::XML_SITEMAPS_KEY => $this->xmlSitemapOutputCheck(),
            self::INCREMENTAL_KEY => $this->incrementalSitemapCheck(),
            self::HTML_SITEMAP_KEY => $this->htmlSitemapCheck(),
            default => null,
        };
    }

    private function xmlSitemapPath(): string
    {
        $xmlPath = trim((string) config('capell.sitemap.xml_path', '/sitemap-xml'), '/');

        return $xmlPath !== '' ? $xmlPath : 'sitemap-xml';
    }
}
