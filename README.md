# Site Discovery

<!-- prettier-ignore-start -->

## What This Plugin Adds

Site Discovery is an **Available**, **No schema impact** Capell package in the **Capell Search & SEO** product group. It ships as `capell-app/site-discovery` and extends these surfaces: admin, frontend, console.

Site Discovery builds a canonical public-URL registry plus XML and HTML sitemap output from published, indexable Capell content.

Admins can inspect which public URLs are indexable and covered by generated outputs. Search engines and other crawlers receive current sitemap routes as content changes.

Evidence: [`src/Actions/BuildPublicUrlRegistryAction.php`](src/Actions/BuildPublicUrlRegistryAction.php), [`src/Actions/GenerateSitemapAction.php`](src/Actions/GenerateSitemapAction.php), [`src/Actions/BuildSitemapXmlResponseAction.php`](src/Actions/BuildSitemapXmlResponseAction.php), [`tests/Feature/SitemapXmlRouteTest.php`](tests/Feature/SitemapXmlRouteTest.php), [`src/Filament/Pages/PublicUrlRegistryPage.php`](src/Filament/Pages/PublicUrlRegistryPage.php), [`tests/Feature/Filament/PublicUrlRegistryPageTest.php`](tests/Feature/Filament/PublicUrlRegistryPageTest.php), [`src/Listeners/Sitemap/RegenerateSitemapsOnPageSaved.php`](src/Listeners/Sitemap/RegenerateSitemapsOnPageSaved.php), [`src/Manifest/SiteDiscoveryFrontendRoutesContribution.php`](src/Manifest/SiteDiscoveryFrontendRoutesContribution.php).

Status details:

- Status: Available
- Tier: free
- Bundle: search-seo
- Composer package: `capell-app/site-discovery`
- Namespace: `Capell\SiteDiscovery`
- Theme key: not applicable

## Why It Matters

**For developers:** URL and discovery-output contracts let packages contribute public records while shared Actions handle deduplication, indexability, sitemap quality, and change notifications.

**For teams:** Teams can see whether published pages are being offered to crawlers and correct missing or hidden URLs before discovery suffers.

Evidence: [`src/Contracts/PublicUrlContributor.php`](src/Contracts/PublicUrlContributor.php), [`src/Contracts/DiscoveryOutputSource.php`](src/Contracts/DiscoveryOutputSource.php), [`src/Actions/ValidateSitemapQualityAction.php`](src/Actions/ValidateSitemapQualityAction.php), [`tests/Integration/Discovery/PublicUrlRegistryActionTest.php`](tests/Integration/Discovery/PublicUrlRegistryActionTest.php), [`docs/overview.admin.md`](docs/overview.admin.md), [`src/Actions/BuildGeneratedOutputParityReportAction.php`](src/Actions/BuildGeneratedOutputParityReportAction.php), [`tests/Integration/Sitemap/SitemapQualityGateTest.php`](tests/Integration/Sitemap/SitemapQualityGateTest.php).

## Screens And Workflow

Screenshot contract: `docs/screenshots.json`.

![Page resource sitemap action](docs/screenshots/page-sitemap-action.png)

![Site resource sitemap action](docs/screenshots/site-sitemap-action.png)

- Page resource sitemap action (admin, required evidence).
- Site resource sitemap action (admin, required evidence).
- Sitemap generation tool (admin, required evidence).
- Public HTML sitemap page (frontend, required evidence).
- Generated XML sitemap output (frontend, required evidence).
- Public URL Registry parity page (admin, required evidence).
- Public URL Registry quality report (admin, required evidence).

## Technical Shape

- Service providers: `Capell\SiteDiscovery\Providers\SiteDiscoveryServiceProvider`.
- Config files: `packages/site-discovery/config/capell-site-discovery.php`.
- Filament classes: `SitemapResourceHeaderActionExtender`, `SitemapSiteHeaderActionExtender`, `SitemapSiteRecordActionExtender`, `PublicUrlRegistryPage`.
- Livewire components: `Sitemap`, `SitemapTool`.
- Route files: `packages/site-discovery/routes/web.php`.
- Extension contracts: `DiscoverableUrlSource`, `DiscoveryOutputSource`, `GeneratedOutputCoverageSource`, `PublicUrlContributor`, `Sitemapable`, `UrlChangeNotifier`.
- Listeners: `EnsureSitemapPagesAfterCapellInstalled`, `RegenerateSitemapsOnPageDeleted`, `RegenerateSitemapsOnPageSaved`, `RegenerateSitemapsOnSiteCreated`.
- Actions: `BuildGeneratedOutputParityReportAction`, `BuildPublicSitemapTreeAction`, `BuildPublicUrlRegistryAction`, `BuildSitemapXmlResponseAction`, `DiscoverPublicDiscoveryOutputsAction`, `DiscoverPublicPagesAction`, `DiscoverPublicUrlsAction`, `EnsureSitemapPagesAction`, `GenerateSitemapAction`, `GenerateSitemapIncrementallyAction`, `NotifyPageUrlChangesAction`, `NotifyPublicUrlChangesAction`, `and 6 more`.
- Data objects: `DiscoverablePageData`, `DiscoverableUrlData`, `DiscoveryOutputData`, `GeneratedOutputParityReportData`, `GeneratedOutputParityRowData`, `PublicUrlData`, `PublicUrlRegistryEntryData`, `SiteMapData`, `SitemapAlternateData`, `SitemapImageData`, `SitemapNewsData`, `SitemapPageData`, `and 7 more`.
- Jobs: `RebuildAllSitemapsJob`, `RebuildSiteSitemapJob`, `RegenerateSiteSitemapIncrementallyJob`.
- Command signatures: `capell:xml-sitemap`.
- Manifest action API: `buildGeneratedOutputParityReport: Capell\SiteDiscovery\Actions\BuildGeneratedOutputParityReportAction`, `buildPublicUrlRegistry: Capell\SiteDiscovery\Actions\BuildPublicUrlRegistryAction`, `generateSitemap: Capell\SiteDiscovery\Actions\GenerateSitemapAction`, `setup: Capell\SiteDiscovery\Actions\SetupSiteDiscoveryPackageAction`, `validateSitemapQuality: Capell\SiteDiscovery\Actions\ValidateSitemapQualityAction`.
- Scheduled commands: `capell:xml-sitemap --incremental (manifest declared)`.
- Console command classes: `XmlSitemapCommand`.
- Manifest contributions: `admin-page: Capell\SiteDiscovery\Manifest\PublicUrlRegistryPageContribution`, `route: Capell\SiteDiscovery\Manifest\SiteDiscoveryFrontendRoutesContribution`, `scheduled-job: Capell\SiteDiscovery\Manifest\SiteDiscoveryIncrementalSitemapScheduleContribution`.
- Health checks: `Capell\SiteDiscovery\Health\SiteDiscoveryHealthCheck`.
- Blade views: `packages/site-discovery/resources/views/components/pages/sitemap.blade.php`, `packages/site-discovery/resources/views/components/pages/sitemap/page.blade.php`, `packages/site-discovery/resources/views/filament/pages/public-url-registry.blade.php`, `packages/site-discovery/resources/views/livewire/page/sitemap.blade.php`, `packages/site-discovery/resources/views/livewire/tools/sitemap-tool.blade.php`, `packages/site-discovery/resources/views/sitemap/sitemap-page.blade.php`.
- Cache tags: `site-discovery`.

## Data Model

This package has no schema impact. It extends Capell through `admin-page` contributions, `route` contributions, and `scheduled-job` contributions instead of declaring package-owned tables.

## Install Impact

- Required packages: `capell-app/admin`, `capell-app/core`, `capell-app/frontend`.
- Admin navigation: declares `admin-page: PublicUrlRegistryPageContribution`; each Filament page or resource controls its own navigation visibility.
- Admin/editor extensions: none declared.
- Permissions: `View:PublicUrlRegistryPage`.
- Public routes: loads `routes/web.php`; registers `SiteDiscoveryFrontendRoutesContribution`.
- Database changes: no package migrations declared.
- Config: `config/capell-site-discovery.php`.
- Settings: no package settings declared.
- Queues or schedules: scheduled commands `capell:xml-sitemap --incremental (manifest declared)`; queue jobs `RebuildAllSitemapsJob`, `RebuildSiteSitemapJob`, `RegenerateSiteSitemapIncrementallyJob`.
- Cache tags: `site-discovery`.
- Commands: `capell:xml-sitemap`.

## Common Pitfalls

- Keep required Capell packages on compatible v4 releases: `capell-app/admin`, `capell-app/core`, `capell-app/frontend`.
- Review package configuration before production-like verification: `config/capell-site-discovery.php`.
- Review middleware, throttling, signatures, and public-output safety in `routes/web.php` before exposing routes.
- Keep the host Laravel scheduler running so package-registered schedules can execute: `capell:xml-sitemap --incremental (manifest declared)`.
- Keep public Blade and cached HTML free of authoring markers, model IDs, permissions, signed editor URLs, and lazy database queries.
- Custom write integrations must preserve invalidation for `site-discovery` cache tags.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Route returns unexpected output | Route cache, middleware, or signed URL setup does not match the package route file | Check the route files listed in `Technical Shape` | Clear route cache and verify middleware before exposing public routes |
| Background work does not run | Queue worker or declared schedule is not active | Check the jobs and scheduled commands listed in `Technical Shape` | Start the queue worker or host scheduler, then run the focused command or package test |
| Public output leaks unexpected state | Render data, cache variation, or authoring boundary has regressed | Check public Blade, cache tags, and public-output safety tests | Move data loading out of Blade and rerun the package public-output tests |

## Quick Start

1. Install the package: `composer require capell-app/site-discovery`.
2. Review `config/capell-site-discovery.php` before enabling the package.
3. Open the package admin surface at `/screenshot-fixtures/site-discovery/page-sitemap-action` and confirm Site Discovery is available.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Admin guide](docs/admin-guide.md)
- Configuration files: [`config/capell-site-discovery.php`](config/capell-site-discovery.php).
- [Troubleshooting](#troubleshooting)
- [Screenshot contract](docs/screenshots.json)
- [Marketplace assets](docs/assets/marketplace/)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Related packages: [Agent Delivery](../agent-delivery/README.md), [Search](../search/README.md), [Seo Suite](../seo-suite/README.md), [Url Manager](../url-manager/README.md).
- Focused tests: `vendor/bin/pest packages/site-discovery/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
