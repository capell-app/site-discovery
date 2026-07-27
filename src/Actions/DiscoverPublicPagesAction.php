<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Data\Database\SqlFragment;
use Capell\Core\Enums\BlueprintGroupEnum;
use Capell\Core\Facades\CapellDatabase;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Data\DiscoverablePageData;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * @method static Collection<int, DiscoverablePageData> run(Site $site, Language $language)
 */
final class DiscoverPublicPagesAction
{
    use AsFake;
    use AsObject;

    /**
     * @return Collection<int, DiscoverablePageData>
     */
    public function handle(Site $site, Language $language): Collection
    {
        $query = Page::query();
        $priority = CapellDatabase::for($query->getModel())->queryDialect()->jsonExtract(
            SqlFragment::raw($query->getQuery()->getGrammar()->wrap('pages.meta')),
            '$.priority',
        );

        return $query->select('pages.*')
            ->selectRaw($priority->sql . ' AS meta_priority', $priority->bindings)
            ->with([
                'translation' => fn (BuilderContract $query): BuilderContract => $query->where('language_id', $language->id),
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
                fn (Builder $query): Builder => $query->whereNull('pages.meta')
                    ->orWhereJsonDoesntContain('pages.meta->hidden', true),
            )
            ->where(
                fn (Builder $query): Builder => $query->whereNull('pages.meta->robots')
                    ->orWhereJsonDoesntContain('pages.meta->robots', 'noindex'),
            )
            ->publishedDate()
            ->ordered()
            ->get()
            ->filter(fn (Page $page): bool => ! $this->hasNoIndexDirective($page->meta ?? [])
                && ! $this->hasNoIndexDirective($page->translation->meta ?? []))
            ->map(function (Page $page) use ($site): DiscoverablePageData {
                $page->setRelation('site', $site);
                Page::setResolvedPageUrlSiteDomain($page, $site);
                $pageId = $page->getKey();
                $updatedAt = $page->getAttribute('updated_at');

                if (! is_int($pageId)) {
                    throw new RuntimeException('Expected the discoverable page to have an integer key.');
                }

                return new DiscoverablePageData(
                    pageId: $pageId,
                    title: trim(strip_tags($page->translation->title ?? $page->translation->label ?? $page->name ?? '')),
                    url: $page->pageUrl->full_url ?? '',
                    lastModified: $updatedAt instanceof CarbonInterface ? $updatedAt : null,
                    priority: is_numeric($page->meta['priority'] ?? null) ? (float) $page->meta['priority'] : null,
                    changeFrequency: is_string($page->meta['changefreq'] ?? null) ? $page->meta['changefreq'] : null,
                    page: $page,
                );
            })
            ->filter(fn (DiscoverablePageData $page): bool => $page->url !== '')
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
