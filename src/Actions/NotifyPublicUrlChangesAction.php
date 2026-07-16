<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Contracts\UrlChangeNotifier;
use Capell\SiteDiscovery\Data\DiscoverableUrlData;
use Capell\SiteDiscovery\Data\UrlChangeNotificationResultData;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Collection<int, UrlChangeNotificationResultData> run(Site $site, Language $language, iterable<int, DiscoverableUrlData|string> $urls, ?SiteDomain $domain = null)
 */
final class NotifyPublicUrlChangesAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  iterable<int, DiscoverableUrlData|string>  $urls
     * @return Collection<int, UrlChangeNotificationResultData>
     */
    public function handle(Site $site, Language $language, iterable $urls, ?SiteDomain $domain = null): Collection
    {
        $publicUrls = $this->publicUrls($urls);

        if ($publicUrls->isEmpty()) {
            return collect();
        }

        return collect(app()->tagged(UrlChangeNotifier::TAG))
            ->filter(fn (mixed $notifier): bool => $notifier instanceof UrlChangeNotifier)
            ->map(fn (UrlChangeNotifier $notifier): UrlChangeNotificationResultData => $notifier->notify($site, $language, $publicUrls, $domain))
            ->values();
    }

    /**
     * @param  iterable<int, DiscoverableUrlData|string>  $urls
     * @return Collection<int, non-falsy-string>
     */
    private function publicUrls(iterable $urls): Collection
    {
        return collect($urls)
            ->map(fn (DiscoverableUrlData|string $url): string => $url instanceof DiscoverableUrlData ? $url->loc : $url)
            ->map(fn (string $url): string => trim($url))
            ->filter(fn (string $url): bool => str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))
            ->unique()
            ->values();
    }
}
