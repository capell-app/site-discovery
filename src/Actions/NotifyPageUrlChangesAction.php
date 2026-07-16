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
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Collection<int, UrlChangeNotificationResultData> run(Pageable $page)
 */
final class NotifyPageUrlChangesAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  Pageable<Model>  $page
     * @return Collection<int, UrlChangeNotificationResultData>
     */
    public function handle(Pageable $page): Collection
    {
        if (! $page instanceof Model) {
            return collect();
        }

        $page->loadMissing('pageUrls.language', 'site');

        $site = $page->getRelationValue('site');

        if (! $site instanceof Site) {
            return collect();
        }

        /** @var iterable<int, PageUrl> $pageUrls */
        $pageUrls = $page->getRelation('pageUrls');
        $pageUrls = collect($pageUrls);

        $domainsByLanguage = SiteDomain::query()
            ->where('site_id', $site->id)
            ->whereIn('language_id', $pageUrls->pluck('language_id')->filter()->unique()->values())
            ->get()
            ->keyBy(fn (SiteDomain $siteDomain): int => (int) $siteDomain->language_id);

        $pageUrls->each(function (PageUrl $pageUrl) use ($domainsByLanguage): void {
            $siteDomain = $domainsByLanguage->get((int) $pageUrl->language_id);

            if ($siteDomain instanceof SiteDomain) {
                $pageUrl->setRelation('siteDomain', $siteDomain);
            }
        });

        return $pageUrls
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
