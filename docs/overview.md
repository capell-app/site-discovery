# Site Discovery

<!-- prettier-ignore-start -->

## What it does for you

Site Discovery builds the public HTML and XML sitemap outputs for enabled Capell sites and provides a registry for checking whether each canonical public URL appears in the outputs that should contain it. It reads indexability and robots decisions from the underlying content and SEO providers; it does not provide its own editor for those values.

## Where to work

- Open **Monitoring > Public URL Registry** to review canonical URLs by source package, site, language, indexability, sitemap eligibility, AI-discovery eligibility, and generated-output status.
- Use **Sitemap** from the existing **Pages** or **Sites** screens to open Capell's shared sitemap workflow. These actions are links to that workflow, not direct regeneration buttons for the current record.
- The package also contributes the global sitemap generation tool and the public HTML sitemap page type. The XML route uses the configured `capell.sitemap.xml_path`, which defaults to `/sitemap-xml` and also supports a site path prefix.

## Set up discovery safely

- Package setup creates a missing sitemap page for each existing site, and a completed Capell installation runs the same check. Confirm each enabled site has a domain with a language before generating XML; a site without a domain, or a domain without a language, cannot be generated.
- XML sets and incremental state are written to the filesystem configured by `capell.sitemap.disk` and `capell.sitemap.directory` (`local` and `sitemaps` by default). A rebuild writes every site domain and language into an isolated staging set, validates the complete XML/index inventory and required metadata, then promotes one current-set manifest. The public route reads only that promoted set, with flat-file fallback for installations upgrading from an earlier release.
- Grant `View:PublicUrlRegistryPage` only to users who may see the canonical URL inventory. The current registry action aggregates every registered contributor and the page does not apply the admin's assigned-site scope, so this permission exposes rows across sites.
- Keep a queue worker running. Page saves and deletions are handled by queued listeners, which then dispatch a delayed per-site incremental regeneration job. New-site regeneration is queued as well.

## Understand the generation lifecycle

- A page save or deletion requests regeneration for its owning site. Requests are debounced for 60 seconds by default, and duplicate pending work is coalesced. A page with no owning site is ignored.
- The per-site incremental job is unique and has one attempt. It skips publication when every domain is unchanged; when any domain changes, it publishes one complete replacement set for every domain and language on the site. A failed job is therefore not retried by this package; inspect the queue failure and run a fresh generation after correcting the cause.
- The optional incremental schedule is disabled by default. When enabled through `capell-site-discovery.incremental_sitemap_schedule.enabled`, it runs `capell:xml-sitemap --incremental` at 02:30 daily by default, without overlap and on one server. The frequency can instead be one of the supported interval values or a configured cron expression. The Laravel scheduler must be running for this safety sweep.
- `capell:xml-sitemap` rebuilds every enabled site, while `--site=<id>` limits it and `--incremental` skips sites whose recorded URL state is unchanged. Full and incremental rebuilds leave the currently served set in place until a complete replacement passes validation.
- The global admin sitemap tool queues one atomic generation job per enabled site. Only a global admin or configured super-admin may start it. If the queue is stopped or a job fails, the previous complete sitemap set remains available; inspect the failed job and rebuild after correcting the cause.
- Jobs started by the global admin tool are lock-protected per site. A second admin-tool job waits for the configured lock interval and then fails with an "already running" error instead of writing over the active run; event jobs use their separate uniqueness lock and the optional schedule uses its no-overlap lock.

Generated XML is split into a sitemap index and chunk files when a domain exceeds the configured maximum URLs per file (50,000 by default). Public XML responses are cached for one day and support ETag revalidation, so allow for downstream cache age after rebuilding.

For operational recovery, correct the generation or storage failure, run `capell:xml-sitemap --site=42`, then inspect the public response with `curl -I https://example.com/sitemap-xml`. A healthy response is `200` and includes an ETag in the required weak SHA-256 form, for example `ETag: W/"0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"`. Repeating the header request with `curl -I -H 'If-None-Match: W/"0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"' https://example.com/sitemap-xml` returns `304` when that exact set is still current. A failed rebuild must leave the previous body and ETag available; rerun the rebuild rather than deleting sitemap files or the current-set manifest.

## Read the Public URL Registry

- **Present** means the current generated output contains the normalised canonical URL.
- **Missing** means the URL is eligible for that available output but was not found in it. Regenerate the relevant sitemap or downstream output, then reload the report.
- **Not eligible** reflects the URL's indexability, robots, content type, or package-provided eligibility. Change those values on the underlying page or SEO screen, not in the registry.
- **Unknown** means no coverage provider is available for that output; it is not proof that the URL is missing.

The report reads the current XML files and current output contributors. It is a parity snapshot, not a historical crawl or a record of what a search engine has indexed. Sitemap quality validation excludes URLs with URL-specific quality errors from generation, so use the registry's missing-output filter together with the underlying page data when investigating a gap.

## Optional IndexNow notifications

IndexNow is disabled by default. If `capell-site-discovery.indexnow.enabled` is enabled and a key is configured, the queued page save/delete listener submits the page's enabled public URLs, site host, key, and key-location URL to the configured external IndexNow endpoint. This is an intentional disclosure of already-public URLs and the authentication key to that service.

The client accepts only HTTPS endpoints on its built-in trusted-host list, refuses redirects, pins the resolved public address for the request, applies a 10-second timeout by default, and redacts configured secrets from connection-error messages. A missing key, rejected endpoint, connection failure, or non-success response produces a failed result, but the lifecycle listener does not persist or retry that result. Treat IndexNow as best-effort notification rather than proof of search-engine receipt.

---

For how to use Site Discovery, see the [admin guide](admin-guide.md).
For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
