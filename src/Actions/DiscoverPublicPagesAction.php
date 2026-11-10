<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Actions\PublicRead\PageUrlsArePubliclyReadableAction;
use Capell\Core\Data\Database\SqlFragment;
use Capell\Core\Enums\BlueprintGroupEnum;
use Capell\Core\Facades\CapellDatabase;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Support\TableName;
use Capell\SiteDiscovery\Data\DiscoverablePageData;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * @method static Collection<int, DiscoverablePageData> run(Site $site, Language $language, ?SiteDomain $publishingDomain = null)
 */
final class DiscoverPublicPagesAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  SiteDomain|null  $publishingDomain  the domain the output is served from; every address is built, and judged for public readability, on it
     * @return Collection<int, DiscoverablePageData>
     */
    public function handle(Site $site, Language $language, ?SiteDomain $publishingDomain = null): Collection
    {
        $query = Page::query();
        $priority = CapellDatabase::for($query->getModel())->queryDialect()->jsonExtract(
            SqlFragment::raw($query->getQuery()->getGrammar()->wrap(TableName::resolve('pages') . '.meta')),
            '$.priority',
        );
        new SqlFragment(
            $priority->sql . ' AS meta_priority',
            $priority->bindings,
        )->applySelect($query->getQuery());

        return $query->addSelect(TableName::resolve('pages') . '.*')
            ->with([
                'translation' => fn (BuilderContract $query): BuilderContract => $query->where('language_id', $language->id),
                'pageUrls' => fn (BuilderContract $query): BuilderContract => $query
                    ->where('status', true)
                    ->whereNull('type'),
                'pageUrls.language',
                'pageUrls.siteDomain',
                'translations',
            ])
            ->withWhereHas(
                'pageUrl',
                fn (BuilderContract $query): BuilderContract => $query
                    ->where('language_id', $language->id)
                    ->where('status', true)
                    ->whereNull('type'),
            )
            ->withWhereHas(
                'blueprint',
                fn (BuilderContract $query): BuilderContract => $query
                    ->where(
                        fn (Builder $query): Builder => $query->whereNull('group')
                            ->orWhereIn('group', config('capell.core.sitemap.type_groups', [BlueprintGroupEnum::Default->value])),
                    )
                    ->enabled()
                    ->visible()
                    ->accessible(),
            )
            ->where($query->qualifyColumn('site_id'), $site->id)
            ->where(
                fn (Builder $query): Builder => $query->whereNull(TableName::resolve('pages') . '.meta')
                    ->orWhereJsonDoesntContain(TableName::resolve('pages') . '.meta->hidden', true),
            )
            ->where(
                fn (Builder $query): Builder => $query->whereNull(TableName::resolve('pages') . '.meta->robots')
                    ->orWhereJsonDoesntContain(TableName::resolve('pages') . '.meta->robots', 'noindex'),
            )
            ->publishedDate()
            ->ordered()
            ->get()
            ->filter(fn (Page $page): bool => ! $this->hasNoIndexDirective($page->meta ?? [])
                && ! $this->hasNoIndexDirective($page->translation->meta ?? []))
            ->map(function (Page $page) use ($site, $publishingDomain): DiscoverablePageData {
                /** @var PageUrl|null $pageUrl */
                $pageUrl = $page->getRelationValue('pageUrl');
                $page->setRelation('site', $site);
                Page::setResolvedPageUrlSiteDomain($page, $site);

                // The default-domain match above is only a fallback: the output is served from, and judged on, the publishing domain.
                if ($publishingDomain instanceof SiteDomain) {
                    $pageUrl?->setRelation('siteDomain', $publishingDomain);
                }

                $pageId = $page->getKey();
                $updatedAt = $page->getAttribute('updated_at');

                if (! is_int($pageId)) {
                    throw new RuntimeException('Expected the discoverable page to have an integer key.');
                }

                return new DiscoverablePageData(
                    pageId: $pageId,
                    title: trim(strip_tags($page->translation->title ?? $page->translation->label ?? $page->name ?? '')),
                    url: $pageUrl === null ? '' : ($pageUrl->full_url ?? ''),
                    lastModified: $updatedAt instanceof CarbonInterface ? $updatedAt : null,
                    priority: is_numeric($page->meta['priority'] ?? null) ? (float) $page->meta['priority'] : null,
                    changeFrequency: is_string($page->meta['changefreq'] ?? null) ? $page->meta['changefreq'] : null,
                    page: $page,
                );
            })
            ->filter(fn (DiscoverablePageData $page): bool => $page->url !== '')
            ->values()
            ->pipe($this->publiclyReadable(...));
    }

    /**
     * Discovery outputs (llms.txt, AI discovery, sitemaps, the public URL
     * registry) list only pages an anonymous visitor may read. Every address
     * is checked in one batch.
     *
     * @param  Collection<int, DiscoverablePageData>  $pages
     * @return Collection<int, DiscoverablePageData>
     */
    private function publiclyReadable(Collection $pages): Collection
    {
        $decisions = PageUrlsArePubliclyReadableAction::run($pages
            ->map(static fn (DiscoverablePageData $page): mixed => $page->page?->getRelationValue('pageUrl'))
            ->filter(static fn (mixed $pageUrl): bool => $pageUrl instanceof PageUrl)
            ->all());
        $position = 0;

        return $pages
            ->filter(static function (DiscoverablePageData $page) use ($decisions, &$position): bool {
                if (! $page->page?->getRelationValue('pageUrl') instanceof PageUrl) {
                    return false;
                }

                return $decisions[$position++];
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function hasNoIndexDirective(array $meta): bool
    {
        $directives = $meta['robots'] ?? [];

        if (is_string($directives)) {
            return strtolower(trim($directives)) === 'noindex';
        }

        if (! is_array($directives)) {
            return false;
        }

        return collect($directives)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) ? $value === true : is_string($value))
            ->map(fn (mixed $value, mixed $key): string => is_string($key) ? $key : (string) $value)
            ->map(fn (string $directive): string => strtolower(trim($directive)))
            ->contains('noindex');
    }
}
