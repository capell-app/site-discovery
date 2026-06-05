# Site Discovery

Site Discovery owns public sitemap, discoverable URL, and generated-output registry foundations for Capell sites.

## At A Glance

- Package: `capell-app/site-discovery`
- Namespace: `Capell\SiteDiscovery\`
- Surfaces: admin actions/tools, frontend sitemap pages, console
- Service providers: `packages/site-discovery/src/Providers/SiteDiscoveryServiceProvider.php`
- Capell dependencies: `capell-app/admin`, `capell-app/core`, `capell-app/frontend`
- Third-party dependencies: none

## Why It Helps Your Capell Workflow

- Resolves public discoverable pages and URLs, then exposes HTML and XML sitemap outputs.
- Provides a discovery-output contract so packages can advertise public machine-readable outputs such as `llms.txt` without coupling Site Discovery to consumer packages.
- Provides a URL change notification contract so packages can submit public URL changes to IndexNow-style services without hard-coding providers into sitemap generation.
- Includes an optional IndexNow notifier, disabled by default until `capell-site-discovery.indexnow.enabled` and a key are configured.
- Helps owners and search tools find the pages Capell intends to publish without each package building its own crawler view.
- Gives developers a shared discovery surface that SEO Suite, Blog, Search, and audits can use consistently.
- Site Discovery is now the canonical source for public URL scope, canonical state, robots eligibility, last modified state, source package, sitemap eligibility, AI Discovery eligibility, and generated-output coverage diagnostics.

## Best Used With

- [SEO Suite](../seo-suite/README.md)
- [Search](../search/README.md)
- [Blog](../blog/README.md)

## What It Adds

- Public discoverable page and URL APIs.
- Canonical public URL registry via `PublicUrlContributor` and `BuildPublicUrlRegistryAction`.
- Sitemap quality validation via `ValidateSitemapQualityAction`.
- Generated-output parity reporting via `BuildGeneratedOutputParityReportAction` and the Public URL Registry admin page.
- HTML sitemap page type and frontend component.
- XML sitemap generation with chunking and incremental state.
- Sitemap admin page, admin actions, and generation tool.
- Lifecycle listeners that regenerate sitemap output when pages or sites change.

## Code Map

| Area      | Path                                    | Purpose                                                             |
| --------- | --------------------------------------- | ------------------------------------------------------------------- |
| Actions   | `packages/site-discovery/src/Actions`   | Domain operations. Test these directly where possible.              |
| Data      | `packages/site-discovery/src/Data`      | Structured payloads, form state, view models, and integration data. |
| Enums     | `packages/site-discovery/src/Enums`     | Persisted states and Filament option values.                        |
| Filament  | `packages/site-discovery/src/Filament`  | Admin resources, pages, widgets, and settings UI.                   |
| Livewire  | `packages/site-discovery/src/Livewire`  | Interactive frontend or admin components.                           |
| Providers | `packages/site-discovery/src/Providers` | Registration, extension hooks, routes, migrations, and resources.   |
| Resources | `packages/site-discovery/resources`     | Views, translations, assets, and package resources.                 |
| Tests     | `packages/site-discovery/tests`         | Package-level Pest coverage.                                        |

## Runtime Surface

- Admin: Page and Site `Sitemap` actions, the sitemap generation tool, and the Public URL Registry parity page.
- Frontend: `/sitemap` HTML sitemap and `/sitemap-xml` XML response.
- Livewire: `Sitemap`, `SitemapTool`.

## Commands

- `capell:xml-sitemap {--site= : Only regenerate sitemaps for this site ID} {--incremental : Skip domains whose pages have not changed since the last run}` (packages/site-discovery/src/Console/Commands/XmlSitemapCommand.php)

## Data And Persistence

- Data objects live in `src/Data/`; use them for payloads, form state, and view models.
- `PagesSitemap` caches serialized page arrays and rebuilds `SitemapPageData` objects when reading from cache. Do not cache the DTO objects directly; stale serialized objects can come back as `__PHP_Incomplete_Class` in host apps.

## Extension Points

- Contracts: `PublicUrlContributor`, `GeneratedOutputCoverageSource`, `DiscoverableUrlSource`, `DiscoveryOutputSource`, `UrlChangeNotifier`, `Sitemapable`.
- Public URL Registry: packages contribute canonical URLs through `PublicUrlContributor`; entries include source package, site/language, route name, last modified date, indexability, robots directives, content type, sitemap eligibility, AI Discovery eligibility, priority, and change frequency.
- Built-in contributor: Site Discovery registers existing CMS page URLs through `CmsPagePublicUrlContributor`.
- Package contributors: Blog, Events, and Campaign Studio register package-local public URL contributors when Site Discovery is available.
- Generated-output parity: the Public URL Registry page compares registry URLs against generated sitemap XML and tagged `GeneratedOutputCoverageSource` implementations. SEO Suite reports AI Discovery coverage, Search reports search-indexable registry coverage, HTML Cache reports cached URL records, and Agent Delivery reports page manifest-resolvable URLs. Outputs without an installed source are marked as unknown.
- IndexNow: enable `capell-site-discovery.indexnow.enabled` and set `capell-site-discovery.indexnow.key` to submit URL changes through the built-in notifier.
- Listeners: `RegenerateSitemapsOnPageDeleted`, `RegenerateSitemapsOnPageSaved`, `RegenerateSitemapsOnSiteCreated`.
- Register Capell extension points, routes, migrations, settings, render hooks, and resources from service providers.

## Install And Setup

- Install with `composer require capell-app/site-discovery` in the host Capell application.
- The core install flow can create the default Sitemap page when this package is installed before `capell:install`.
- When adding the package to an existing core-only app, create the Sitemap page for each existing site before testing `/sitemap`; the current extension install flow does not backfill existing sites automatically.
- Make sure custom web-server static-file rules do not intercept generated sitemap endpoints before Laravel runs. The default XML endpoint is `/sitemap-xml`, which usually avoids extension-based static handlers. If a host app aliases this to `/sitemap.xml`, add an nginx/Apache exception for that path before generic `*.xml` static handling.
- In this repository, verify package changes with `vendor/bin/pest`; do not use `php artisan`.

### Web Server Routing

Site Discovery serves HTML and XML sitemap output through Laravel. Laravel's default Apache rewrite is enough because missing files are sent to `public/index.php`.

For nginx, the normal Laravel front-controller rule is enough for `/sitemap` and `/sitemap-xml`:

```nginx
location / {
    try_files $uri $uri/ /index.php$is_args$args;
}
```

If the host app exposes XML at `/sitemap.xml` and the vhost has a static `*.xml` location, add an exact route before the static block:

```nginx
location = /sitemap.xml {
    try_files /__missing__ /index.php$is_args$args;
}
```

For Apache, keep the standard Laravel rewrite and avoid rewrite exclusions for generated sitemap paths:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

## Approved Improvement Roadmap

These items are approved product direction for Site Discovery and should be planned as package work rather than host-app-only behavior.

- Continue migrating any remaining package-owned dynamic URLs into `PublicUrlContributor` implementations owned by those packages.
- Extend sitemap quality gates with live HTTP status checks when a host app wants to verify generated XML against routed responses.
- Add sitemap index and sharding support by source or content type once URL counts require it. Good shard candidates are CMS pages, marketplace extensions, field notes, docs, packages, and media.
- Extend downstream parity sources as generated-output packages add more persistent URL indexes, keeping package-specific reads inside the owning package.
- Add install or doctor diagnostics for `/sitemap`, `/sitemap-xml`, and host aliases such as `/sitemap.xml`, including status code, content type, XML validity, and web-server static-handler interception.
- Keep URL change notifications provider-neutral; IndexNow should remain one notifier behind the shared `UrlChangeNotifier` contract.

## Docs

- [docs index](docs/README.md)
- [overview.md](docs/overview.md)
- [screenshots.json](docs/screenshots.json)

## Screenshot Coverage

The package screenshot contract covers the package-added Page and Site sitemap actions, the sitemap generation tool, `/sitemap`, and `/sitemap-xml`. Keep each capture focused on the added surface; avoid broad admin screenshots that hide which control belongs to this package.

Public sitemap screenshots should be checked for authoring or package identifiers. The public output must not expose `capell-site-discovery`, `capell-sitemap`, admin URLs, editor metadata, signed editor links, or unpublished pages.

## Testing

Run package tests from the repository root:

```bash
vendor/bin/pest packages/site-discovery/tests --configuration=phpunit.xml
```

## Maintenance Notes

- Put behaviour changes in `src/Actions/`; UI classes, commands, and controllers should call actions instead of owning domain logic.
- Use package `Data` classes at boundaries instead of passing anonymous arrays between layers.
- Use backed enums for persisted values and enum labels for Filament options.
