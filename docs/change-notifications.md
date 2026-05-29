# Change Notifications

Site Discovery owns public URL change notification mechanics. The package exposes a provider-neutral action plus an optional IndexNow notifier.

## Boundary

Use change notifications only for URLs that are already public and discoverable. Do not submit draft URLs, admin URLs, preview URLs, signed editor URLs, unpublished translations, or internal package routes.

Site Discovery should not decide whether content is "good" for AI search. It only discovers public URLs and notifies configured submission providers when those URLs change.

## IndexNow

The built-in IndexNow notifier is disabled by default.

Configure it in the host app:

```php
'capell-site-discovery' => [
    'indexnow' => [
        'enabled' => true,
        'key' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_KEY'),
        'key_location' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_KEY_LOCATION'),
    ],
],
```

If `key_location` is not set, the notifier uses `https://{host}/{key}.txt`.

## Calling The Contract

Packages should call the action after they know which public URLs changed:

```php
use Capell\SiteDiscovery\Actions\NotifyPublicUrlChangesAction;

NotifyPublicUrlChangesAction::run($site, $language, [
    'https://example.com/updated-page',
]);
```

The action filters non-HTTP URLs, removes duplicates, and calls notifiers tagged with `UrlChangeNotifier::TAG`.

## Operational Notes

- Queue or debounce bulk updates before calling notification providers.
- Pair notifications with sitemap invalidation so search engines can also fetch canonical sitemap state.
- Treat provider failures as retryable operational signals, not page-publishing failures.
