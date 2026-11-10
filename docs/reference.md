# Site Discovery reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/site-discovery`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Site Discovery is an **Available**, **No schema impact** Capell package in the **Capell Search & SEO** product group. It ships as `capell-app/site-discovery` and extends these surfaces: admin, frontend, console.

- Status: Available
- Tier: free
- Bundle: search-seo
- Composer package: `capell-app/site-discovery`
- Namespace: `Capell\SiteDiscovery`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\SiteDiscovery\Providers\SiteDiscoveryServiceProvider`

### Config files

- `packages/site-discovery/config/capell-site-discovery.php`

### Filament classes

- `SitemapResourceHeaderActionExtender`
- `SitemapSiteHeaderActionExtender`
- `SitemapSiteRecordActionExtender`
- `PublicUrlRegistryPage`

### Livewire components

- `Sitemap`
- `SitemapTool`

### Route files

- `packages/site-discovery/routes/web.php`

### Extension contracts

- `DiscoverableUrlSource`
- `DiscoveryOutputSource`
- `GeneratedOutputCoverageSource`
- `PublicUrlContributor`
- `Sitemapable`
- `UrlChangeNotifier`

### Listeners

- `EnsureSitemapPagesAfterCapellInstalled`
- `RegenerateSitemapsOnPageDeleted`
- `RegenerateSitemapsOnPageSaved`
- `RegenerateSitemapsOnSiteCreated`
- `RegenerateSitemapsOnSiteDomainCreated`

### Actions

- `BuildAuthorisedPublicUrlRepairQueueAction`
- `BuildGeneratedOutputParityReportAction`
- `BuildPublicSitemapTreeAction`
- `BuildPublicUrlRegistryAction`
- `BuildPublicUrlRepairQueueAction`
- `BuildSitemapXmlResponseAction`
- `DiscoverPublicDiscoveryOutputsAction`
- `DiscoverPublicPagesAction`
- `DiscoverPublicUrlsAction`
- `EnsureSitemapPagesAction`
- `GenerateSitemapAction`
- `GenerateSitemapIncrementallyAction`
- `NotifyPageUrlChangesAction`
- `NotifyPublicUrlChangesAction`
- `PromoteStagedSitemapSetAction`
- `RedactIndexNowNotificationErrorMessageAction`
- `RequestSiteSitemapRegenerationAction`
- `SetupSiteDiscoveryPackageAction`
- `ValidateSitemapQualityAction`
- `ValidateStagedSitemapSetAction`

### Data objects

- `DiscoverablePageData`
- `DiscoverableUrlData`
- `DiscoveryOutputData`
- `GeneratedOutputParityReportData`
- `GeneratedOutputParityRowData`
- `PublicUrlData`
- `PublicUrlOutputIssueData`
- `PublicUrlRegistryEntryData`
- `PublicUrlRepairItemData`
- `PublicUrlRepairQueueData`
- `SiteMapData`
- `SitemapAlternateData`
- `SitemapImageData`
- `SitemapNewsData`
- `SitemapPageData`
- `SitemapQualityErrorData`
- `SitemapQualityReportData`
- `SitemapUrlItemData`
- `SitemapVideoData`
- `StagedSitemapDomainData`
- `StagedSitemapSetData`
- `UrlChangeNotificationResultData`

### Jobs

- `RebuildAllSitemapsJob`
- `RebuildSiteSitemapJob`
- `RegenerateSiteSitemapIncrementallyJob`

### Command signatures

- `capell:xml-sitemap`

### Manifest action API

- `buildGeneratedOutputParityReport: Capell\SiteDiscovery\Actions\BuildGeneratedOutputParityReportAction`
- `buildPublicUrlRegistry: Capell\SiteDiscovery\Actions\BuildPublicUrlRegistryAction`
- `buildPublicUrlRepairQueue: Capell\SiteDiscovery\Actions\BuildPublicUrlRepairQueueAction`
- `generateSitemap: Capell\SiteDiscovery\Actions\GenerateSitemapAction`
- `setup: Capell\SiteDiscovery\Actions\SetupSiteDiscoveryPackageAction`
- `validateSitemapQuality: Capell\SiteDiscovery\Actions\ValidateSitemapQualityAction`

### Scheduled commands

- `capell:xml-sitemap --incremental (manifest declared)`

### Console command classes

- `XmlSitemapCommand`

### Manifest contributions

- `admin-page: Capell\SiteDiscovery\Manifest\PublicUrlRegistryPageContribution`
- `route: Capell\SiteDiscovery\Manifest\SiteDiscoveryFrontendRoutesContribution`
- `scheduled-job: Capell\SiteDiscovery\Manifest\SiteDiscoveryIncrementalSitemapScheduleContribution`

### Health checks

- `Capell\SiteDiscovery\Health\SiteDiscoveryHealthCheck`

### Blade views

- `packages/site-discovery/resources/views/components/pages/sitemap.blade.php`
- `packages/site-discovery/resources/views/components/pages/sitemap/page.blade.php`
- `packages/site-discovery/resources/views/filament/pages/public-url-registry.blade.php`
- `packages/site-discovery/resources/views/livewire/page/sitemap.blade.php`
- `packages/site-discovery/resources/views/livewire/tools/sitemap-tool.blade.php`
- `packages/site-discovery/resources/views/screenshots/sitemap.blade.php`
- `packages/site-discovery/resources/views/sitemap/sitemap-page.blade.php`

### Cache tags

- `site-discovery`


## Data Model

This package has no schema impact. It extends Capell through `admin-page` contributions, `route` contributions, and `scheduled-job` contributions instead of declaring package-owned tables.

<!-- prettier-ignore-end -->
