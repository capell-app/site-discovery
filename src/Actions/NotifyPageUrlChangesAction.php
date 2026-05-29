<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Data\UrlChangeNotificationResultData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static Collection<int, UrlChangeNotificationResultData> run(Pageable $page)
 */
final class NotifyPageUrlChangesAction
{
    use AsAction;

    /**
     * @param  Pageable<Model>  $page
     * @return Collection<int, UrlChangeNotificationResultData>
     */
    public function handle(Pageable $page): Collection
    {
        if (! $page instanceof Model) {
            return collect();
        }

        $page->loadMissing('pageUrls.language', 'pageUrls.siteDomain', 'site');

        $site = $page->getRelationValue('site');

        if (! $site instanceof Site) {
            return collect();
        }

        /** @var iterable<int, PageUrl> $pageUrls */
        $pageUrls = $page->getRelation('pageUrls');

        return collect($pageUrls)
            ->filter(fn (PageUrl $pageUrl): bool => $this->isPublicPageUrl($pageUrl))
            ->groupBy(fn (PageUrl $pageUrl): int => (int) $pageUrl->language_id)
            ->flatMap(function (Collection $languageUrls) use ($site): Collection {
                $firstUrl = $languageUrls->first();

                if (! $firstUrl instanceof PageUrl || ! $firstUrl->language instanceof Language) {
                    return collect();
                }

                $domain = $firstUrl->siteDomain instanceof SiteDomain ? $firstUrl->siteDomain : null;

                return NotifyPublicUrlChangesAction::run(
                    $site,
                    $firstUrl->language,
                    $languageUrls->map(fn (PageUrl $pageUrl): string => $pageUrl->full_url)->values(),
                    $domain,
                );
            })
            ->values();
    }

    private function isPublicPageUrl(PageUrl $pageUrl): bool
    {
        return $pageUrl->getAttribute('status') === true
            && $pageUrl->getAttribute('type') === null;
    }
}
