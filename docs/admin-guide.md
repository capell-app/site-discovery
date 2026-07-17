# Using Site Discovery

This guide is for editors who manage how pages are found and owners deciding what to expose to crawlers. Every step uses the labels you see on screen.

## Using Site Discovery (editor how-to)

### How to review discovery status

1. Open **Monitoring > Public URL Registry**.
2. Review each public URL's **Indexability**, **Sitemap** status, and generated-output status.
3. Filter by site, language, source package, indexability, sitemap eligibility, or missing outputs to focus the review.

### How to open the sitemap workflow for a page

1. Open the page you want from your **Pages** list.
2. Use the **Sitemap** action at the top of the page to open the shared sitemap workflow.
3. Review the page's underlying publishing and robots/SEO metadata there or in the page's normal editing controls; Site Discovery does not add a separate indexability editor.

![An editor uses the package-added sitemap action on the core Pages resource.](screenshots/page-sitemap-action.png)

### How to open the sitemap workflow for a site

1. Open the site you want from your **Sites** list.
2. Use the **Sitemap** action to open the shared sitemap workflow scoped to that site.
3. Use this to review the site's discovery output; ask a developer to run `capell:xml-sitemap --site=<id>` when an operational XML regeneration is needed.

![An editor uses the package-added sitemap action on the core Sites resource.](screenshots/site-sitemap-action.png)

### How to review a URL missing generated output

1. Open **Monitoring > Public URL Registry**.
2. Filter to URLs with missing outputs or quality errors.
3. Check whether the URL is indexable and sitemap eligible, then correct its underlying page or SEO metadata if needed.
4. Re-check after the sitemap workflow or XML generation has run.

![An administrator generates or reviews sitemap output after pages are in place.](screenshots/sitemap-generation-tool.png)

### How to correct a page's indexability

1. Open the underlying page or the host application's SEO controls.
2. Change its robots metadata to **Indexable** or **Noindex** according to the site's publishing policy.
3. Save, then return to **Public URL Registry** to confirm the reported state after outputs refresh.

### How to check what has been indexed

1. In **Monitoring > Public URL Registry**, filter by sitemap state or indexability.
2. Look for pages marked **Missing from sitemap** or **Noindex** that you actually want found.
3. Fix any that are set the wrong way.

### How to audit your public URLs across the site

1. Go to **Monitoring > Public URL Registry**.
2. Review each public URL and whether the expected outputs (sitemap, search, cached copy, and so on) are present.
3. Use this when you want one place that shows the discovery status for every public address.

![An administrator audits generated-output parity for public URLs across installed packages.](screenshots/public-url-registry-page.png)

### How to find URLs missing outputs or failing quality checks

1. In **Public URL Registry**, filter to URLs with missing outputs or quality errors.
2. Work through anything flagged, for example a page missing from the sitemap.
3. Re-check after you fix each one so the list clears.

![An administrator filters the registry to find URLs missing generated outputs or failing sitemap quality checks.](screenshots/public-url-quality-report.png)

## Rolling out Site Discovery (for owners)

### Turn on first

- **A complete, accurate sitemap.** Review important published URLs in **Public URL Registry** and correct any underlying robots metadata before anything else.

### Add when needed

| Need                             | Enable                                             |
| -------------------------------- | -------------------------------------------------- |
| Keep private pages out of search | **Noindex** in the underlying page or SEO controls |
| Find missing generated outputs   | **Public URL Registry** filters                    |

### Don't enable yet

- Don't hide pages you actually want found. Only **Noindex** content that should stay private.

### Who does what

| Role       | First useful screen                                        |
| ---------- | ---------------------------------------------------------- |
| Editor     | The underlying page/SEO controls: set robots metadata      |
| Site owner | **Monitoring > Public URL Registry**: review output parity |

## Troubleshooting for editors

| What you see                         | What it means                                                                  | What to do                                                                                   |
| ------------------------------------ | ------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------- |
| A page isn't appearing in search     | It is set to **Noindex**, is not sitemap eligible, or output has not refreshed | Correct underlying robots metadata and review the sitemap workflow                           |
| A private page shows up in search    | It is **Indexable** when it shouldn't be                                       | Set it to **Noindex** in the underlying page/SEO controls                                    |
| "Missing from sitemap" on a key page | The current generated sitemap output does not include an eligible URL          | Check its registry state, then run the sitemap workflow or ask a developer to regenerate XML |
