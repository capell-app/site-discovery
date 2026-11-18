# Changelog

All notable changes to `capell-app/site-discovery` will be documented in this file.

## Unreleased

- Serve the XML sitemap at the standard `/sitemap.xml` by default, read every sitemap path from `capell.sitemap.xml_path` (page alias, sitemap index chunks, loader, health check), and permanently redirect the previous `/sitemap-xml` path, including path-prefixed and `?p=N` chunk requests, so existing installs keep working. Re-running sitemap page setup moves a stored `sitemap-xml` page alias to the configured path.
- Added typed sitemap extension payloads for hreflang alternates, image, video, and news metadata.
- Changed chunked XML sitemap writing to avoid duplicating the full URL list with `array_chunk`.
- Retired the obsolete unauthenticated Google/Bing sitemap ping roadmap item in favour of the supported IndexNow notifier path.
- Aligned manifest and docs copy with the outcome-led marketplace listing and committed sitemap/registry screenshot gallery.
- Prepared package metadata and documentation for ongoing Capell 0.0.x package work.

## 2026-06-03

- Improved marketplace and Composer package copy for the Site Discovery listing.
- Added diagnostics-style health checks for public URL contributors, sitemap storage, incremental state, and HTML sitemap wiring.
