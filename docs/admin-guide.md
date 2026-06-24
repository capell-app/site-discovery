# Using Site Discovery

This guide is for editors who manage how pages are found and owners deciding what to expose to crawlers. Every step uses the labels you see on screen.

## Using Site Discovery (editor how-to)

### How to control what is in your sitemap

1. Go to **Site Discovery**.
2. Review the **Sitemap** list of pages offered to search engines.
3. Include the pages you want found; leave out the ones you don't.

### How to add a single page to the sitemap

1. Open the page you want from your **Pages** list.
2. Use the **Sitemap** action at the top of the page to add or refresh it in the sitemap.
3. The page is now offered to search engines.

![An editor uses the package-added sitemap action on the core Pages resource.](screenshots/page-sitemap-action.png)

### How to refresh the sitemap for a whole site

1. Open the site you want from your **Sites** list.
2. Use the **Sitemap** action to rebuild the sitemap for that site.
3. All of the site's discoverable pages are offered to search engines.

![An editor uses the package-added sitemap action on the core Sites resource.](screenshots/site-sitemap-action.png)

### How to generate or review sitemap output

1. Go to **Site Discovery** and open the sitemap generation tool.
2. Run it to build the latest sitemap from your current pages.
3. Review the output to confirm the right pages are listed.

![An administrator generates or reviews sitemap output after pages are in place.](screenshots/sitemap-generation-tool.png)

### How to set a page as findable or hidden

1. Open the page's **Indexability** setting.
2. Set it to **Indexable** to allow search engines, or **Noindex** to hide it.
3. Save. The page's search visibility updates.

### How to check what has been indexed

1. In **Site Discovery**, filter by sitemap state or indexability.
2. Look for pages marked **Missing from sitemap** or **Noindex** that you actually want found.
3. Fix any that are set the wrong way.

### How to audit your public URLs across the site

1. Go to **Public URL Registry**.
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

- **A complete, accurate sitemap.** Make sure your important pages are included and findable before anything else.

### Add when needed

| Need                             | Enable                         |
| -------------------------------- | ------------------------------ |
| Keep private pages out of search | **Noindex** on those pages     |
| Control AI and search crawlers   | The crawler discovery settings |

### Don't enable yet

- Don't hide pages you actually want found. Only **Noindex** content that should stay private.

### Who does what

| Role       | First useful screen                             |
| ---------- | ----------------------------------------------- |
| Editor     | **Indexability**: set pages findable or hidden  |
| Site owner | **Sitemap**: confirm the right pages are listed |

## Troubleshooting for editors

| What you see                         | What it means                                        | What to do                                             |
| ------------------------------------ | ---------------------------------------------------- | ------------------------------------------------------ |
| A page isn't appearing in search     | It is set to **Noindex** or missing from the sitemap | Set it **Indexable** and include it in the **Sitemap** |
| A private page shows up in search    | It is **Indexable** when it shouldn't be             | Set it to **Noindex**                                  |
| "Missing from sitemap" on a key page | The page isn't being offered to search engines       | Include it in the sitemap                              |
