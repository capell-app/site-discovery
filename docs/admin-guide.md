# Using Site Discovery

This guide is for editors who manage how pages are found and owners deciding what to expose to crawlers. Every step uses the labels you see on screen.

## Using Site Discovery (editor how-to)

### How to control what is in your sitemap

1. Go to **Site Discovery**.
2. Review the **Sitemap** list of pages offered to search engines.
3. Include the pages you want found; leave out the ones you don't.

### How to set a page as findable or hidden

1. Open the page's **Indexability** setting.
2. Set it to **Indexable** to allow search engines, or **Noindex** to hide it.
3. Save. The page's search visibility updates.

### How to check what has been indexed

1. In **Site Discovery**, filter by sitemap state or indexability.
2. Look for pages marked **Missing from sitemap** or **Noindex** that you actually want found.
3. Fix any that are set the wrong way.

## Rolling out Site Discovery (for owners)

### Turn on first

- **A complete, accurate sitemap.** Make sure your important pages are included and findable before anything else.

### Add when needed

| Need | Enable |
| --- | --- |
| Keep private pages out of search | **Noindex** on those pages |
| Control AI and search crawlers | The crawler discovery settings |

### Don't enable yet

- Don't hide pages you actually want found. Only **Noindex** content that should stay private.

### Who does what

| Role | First useful screen |
| --- | --- |
| Editor | **Indexability**: set pages findable or hidden |
| Site owner | **Sitemap**: confirm the right pages are listed |

## Troubleshooting for editors

| What you see | What it means | What to do |
| --- | --- | --- |
| A page isn't appearing in search | It is set to **Noindex** or missing from the sitemap | Set it **Indexable** and include it in the **Sitemap** |
| A private page shows up in search | It is **Indexable** when it shouldn't be | Set it to **Noindex** |
| "Missing from sitemap" on a key page | The page isn't being offered to search engines | Include it in the sitemap |
