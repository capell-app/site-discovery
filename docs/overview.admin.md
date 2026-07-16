## What it does for you

Site Discovery builds discovery outputs for published Capell pages and gives operators one place to review whether public URLs are indexable, sitemap-eligible, and present in generated outputs. It does not provide a package-owned page editor for changing a URL's indexability.

## Your screens

- **Public URL Registry**: a Monitoring page that reviews public URLs, their indexability, and generated-output parity.
- **Sitemap** actions: shortcuts on existing Pages and Sites screens that open the shared sitemap workflow.

## What you can do

- Review which public URLs are indexable and eligible for the sitemap.
- Filter for URLs that are missing sitemap, AI-discovery, search, or cache outputs.
- Open the shared sitemap workflow from an existing Page or Site record.
- Change noindex/robots rules in the underlying page or SEO controls provided by the host application.

## Where to find it

Open **Monitoring > Public URL Registry** to review discovery status. From **Pages** or **Sites**, use **Sitemap** to open the shared sitemap workflow for the relevant record.

## Good to know

- The registry reports discovery state; it does not itself change page metadata or generate a historical search-index record.
- A URL marked **Noindex** or not eligible for the sitemap must be changed through its underlying page/SEO metadata before it can be included.
- **Missing from sitemap** means the current generated sitemap output does not include an otherwise eligible URL; use the sitemap workflow or ask a developer to run the XML sitemap command.
