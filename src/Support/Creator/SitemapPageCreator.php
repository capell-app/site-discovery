<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\Creator;

use Capell\Core\Actions\GetOrCreateResultsLayoutAction;
use Capell\Core\Contracts\ModelInterceptors\PageInterceptorInterface;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\LayoutCreator;
use Capell\Frontend\Enums\RenderingStrategyEnum;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPageType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use RuntimeException;

class SitemapPageCreator
{
    /**
     * @var class-string<Layout>
     */
    protected string $layoutModel = Layout::class;

    /**
     * @var class-string<Page>
     */
    protected string $pageModel = Page::class;

    /**
     * @var class-string<Blueprint>
     */
    protected string $typeModel = Blueprint::class;

    /**
     * @param  Collection<int, Language>  $languages
     */
    public function createSitemapPage(Site $site, ?Collection $languages = null): Page
    {
        $languages ??= $site->languages;
        $type = $this->getOrCreateSitemapType();
        $layout = $this->getLayout(LayoutEnum::Default);
        $existingPage = Page::query()
            ->where('site_id', $site->id)
            ->whereHas(
                'pageUrls',
                static fn (Builder $query): Builder => $query->where('url', 'like', '%/sitemap-xml'),
            )
            ->first();

        $defaults = [
            'layout_id' => $layout->id,
            'meta' => [
                'component' => SitemapPageType::ComponentView,
                'livewire' => true,
                'rendering_strategy' => RenderingStrategyEnum::FullLivewire->value,
            ],
            'site_id' => $site->id,
            'blueprint_id' => $type->id,
            'name' => __('capell-site-discovery::generic.sitemap'),
        ];

        $page = $existingPage instanceof Page
            ? $existingPage
            : CapellCore::createOrUpdateModel(
                $this->pageModel,
                [
                    'site_id' => $site->id,
                    'blueprint_id' => $type->id,
                ],
                fn (array $data): array => CapellCore::mergeModelInterceptorData($defaults, $data),
                PageInterceptorInterface::class,
            );

        if (! $page instanceof Page) {
            throw new RuntimeException('The sitemap page creator did not return a page.');
        }

        $page->forceFill([
            'meta' => [
                ...($page->meta ?? []),
                'component' => SitemapPageType::ComponentView,
                'livewire' => true,
                'rendering_strategy' => RenderingStrategyEnum::FullLivewire->value,
            ],
        ])->save();

        $languages->each(function (Language $language) use ($page): void {
            $translation = $page->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'meta' => [
                    'slug' => 'sitemap',
                ],
                'title' => __('capell-site-discovery::generic.sitemap'),
            ]);

            $urlAttributes = [
                'language_id' => $language->id,
                'site_id' => $page->site_id,
                'type' => 'alias',
            ];
            $url = $page->getParentUrl(language: $language) . $translation->slug . '-xml';

            if ($page->pageUrls()->where($urlAttributes)->where('url', $url)->exists()) {
                return;
            }

            $page->pageUrls()->updateOrCreate($urlAttributes, ['url' => $url]);
        });

        return $page;
    }

    private function getOrCreateSitemapType(): Blueprint
    {
        /** @var Builder<Blueprint> $query */
        $query = $this->typeModel::query();

        $type = $query->where('key', SitemapPageType::Key)->pageType()->first();

        if ($type !== null) {
            return $type;
        }

        return SitemapPageType::createType();
    }

    private function getLayout(LayoutEnum $layoutEnum): Layout
    {
        if ($layoutEnum === LayoutEnum::Results) {
            return GetOrCreateResultsLayoutAction::run();
        }

        $layout = $this->layoutModel::query()->firstWhere('key', $layoutEnum->value);

        if ($layout !== null) {
            return $layout;
        }

        return resolve(LayoutCreator::class)->create($layoutEnum->value);
    }
}
