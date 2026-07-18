## What it does for you

Site Discovery builds the public HTML and XML sitemap outputs for enabled Capell sites and provides a registry for checking whether each canonical public URL appears in the outputs that should contain it. It reads indexability and robots decisions from the underlying content and SEO providers; it does not provide its own editor for those values.

## Where to work

- Open **Monitoring > Public URL Registry** to review canonical URLs by source package, site, language, indexability, sitemap eligibility, AI-discovery eligibility, and generated-output status.
- Use **Sitemap** from the existing **Pages** or **Sites** screens to open Capell's shared sitemap workflow. These actions are links to that workflow, not direct regeneration buttons for the current record.
- The package also contributes the global sitemap generation tool and the public HTML sitemap page type. The XML route uses the configured `capell.sitemap.xml_path`, which defaults to `/sitemap-xml` and also supports a site path prefix.

## Set up discovery safely

- Package setup creates a missing sitemap page for each existing site, and a completed Capell installation runs the same check. Confirm each enabled site has a domain with a language before generating XML; a site without a domain, or a domain without a language, cannot be generated.
- XML files and incremental state are written to the filesystem configured by `capell.sitemap.disk` and `capell.sitemap.directory` (`local` and `sitemaps` by default). The web route reads those files; it returns 404 when the matching domain file is absent.
- Grant `View:PublicUrlRegistryPage` only to users who may see the canonical URL inventory. The current registry action aggregates every registered contributor and the page does not apply the admin's assigned-site scope, so this permission exposes rows across sites.
- Keep a queue worker running. Page saves and deletions are handled by queued listeners, which then dispatch a delayed per-site incremental regeneration job. New-site regeneration is queued as well.

## Understand the generation lifecycle

- A page save or deletion requests regeneration for its owning site. Requests are debounced for 60 seconds by default, and duplicate pending work is coalesced. A page with no owning site is ignored.
- The per-site incremental job is unique and has one attempt. It rewrites a domain only when its recorded page state has changed. A failed job is therefore not retried by this package; inspect the queue failure and run a fresh generation after correcting the cause.
- The optional incremental schedule is disabled by default. When enabled through `capell-site-discovery.incremental_sitemap_schedule.enabled`, it runs `capell:xml-sitemap --incremental` at 02:30 daily by default, without overlap and on one server. The frequency can instead be one of the supported interval values or a configured cron expression. The Laravel scheduler must be running for this safety sweep.
- `capell:xml-sitemap` regenerates every enabled site, while `--site=<id>` limits it and `--incremental` preserves unchanged outputs. A non-incremental command deletes that site's existing XML and state before rebuilding it.
- The global admin sitemap tool also deletes existing XML for every enabled site before it queues one generation job per site. Only a global admin or configured super-admin may start it. Do not use this as a harmless refresh: if the queue is stopped or a job fails after deletion, the affected public sitemap routes remain 404 until generation succeeds.
- Jobs started by the global admin tool are lock-protected per site. A second admin-tool job waits for the configured lock interval and then fails with an "already running" error instead of writing over the active run; event jobs use their separate uniqueness lock and the optional schedule uses its no-overlap lock.

Generated XML is split into a sitemap index and chunk files when a domain exceeds the configured maximum URLs per file (50,000 by default). Public XML responses are cached for one day and support ETag revalidation, so allow for downstream cache age after rebuilding.

## Read the Public URL Registry

- **Present** means the current generated output contains the normalised canonical URL.
- **Missing** means the URL is eligible for that available output but was not found in it. Regenerate the relevant sitemap or downstream output, then reload the report.
- **Not eligible** reflects the URL's indexability, robots, content type, or package-provided eligibility. Change those values on the underlying page or SEO screen, not in the registry.
- **Unknown** means no coverage provider is available for that output; it is not proof that the URL is missing.

The report reads the current XML files and current output contributors. It is a parity snapshot, not a historical crawl or a record of what a search engine has indexed. Sitemap quality validation excludes URLs with URL-specific quality errors from generation, so use the registry's missing-output filter together with the underlying page data when investigating a gap.

## Optional IndexNow notifications

IndexNow is disabled by default. If `capell-site-discovery.indexnow.enabled` is enabled and a key is configured, the queued page save/delete listener submits the page's enabled public URLs, site host, key, and key-location URL to the configured external IndexNow endpoint. This is an intentional disclosure of already-public URLs and the authentication key to that service.

The client accepts only HTTPS endpoints on its built-in trusted-host list, refuses redirects, pins the resolved public address for the request, applies a 10-second timeout by default, and redacts configured secrets from connection-error messages. A missing key, rejected endpoint, connection failure, or non-success response produces a failed result, but the lifecycle listener does not persist or retry that result. Treat IndexNow as best-effort notification rather than proof of search-engine receipt.
