<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Contracts;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Data\UrlChangeNotificationResultData;
use Illuminate\Support\Collection;

interface UrlChangeNotifier
{
    public const string TAG = 'capell-site-discovery:url-change-notifiers';

    /**
     * @param  Collection<int, non-falsy-string>  $urls
     */
    public function notify(Site $site, Language $language, Collection $urls, ?SiteDomain $domain = null): UrlChangeNotificationResultData;
}
