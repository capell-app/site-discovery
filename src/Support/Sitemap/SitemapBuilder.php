<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\Sitemap;

use Capell\Core\Data\PublicRead\PublicPageReadCandidateData;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Support\PublicRead\PublicPageReadEligibilityRegistry;
use Capell\SiteDiscovery\Contracts\Sitemapable;
use Capell\SiteDiscovery\Data\SitemapPageData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SitemapBuilder
{
    /**
     * @var Collection<array-key, mixed>
     */
    protected Collection $pages;

    /**
     * Map of composite key to index in $pages.
     * Key is either "{pageableType}:{pageId}" when both present, or "url:{url}" as a fallback.
     *
     * @var array<string,int>
     */
    protected array $indexByKey = [];

    public function __construct(
        protected Site $site,
        protected SiteDomain $domain,
        protected Language $language,
        protected bool $withEditUrl = false,
    ) {
        $this->pages = collect();

        /**
         * @var class-string<Sitemapable> $sitemapPageType
         */
        foreach (resolve(SitemapPageRegistry::class)->all() as $sitemapPageType) {
            $this->addPages(
                new $sitemapPageType(
                    language: $this->language,
                    site: $this->site,
                    domain: $this->domain,
                    withEditUrl: $this->withEditUrl,
                ),
            );
        }
    }

    public function addPages(Sitemapable $sitemapPageType): void
    {
        $results = $sitemapPageType->fetch();

        if ($results->isEmpty()) {
            return;
        }

        $this->publiclyReadable($results)->each(function (SitemapPageData $item): void {
            $this->upsertTopLevel($item);
        });
    }

    /**
     * @return Collection<array-key, mixed>
     */
    public function build(): Collection
    {
        return $this->pages
            ->sortByDesc('priority')
            ->values();
    }

    /**
     * The public sitemap lists only addresses an anonymous visitor may read.
     * A restricted node is dropped and its readable descendants take its
     * place, so a public page under a restricted parent stays listed, and each
     * restricted alternate is dropped. Every address in the tree is checked in
     * one batch. The editor sitemap (with edit links) keeps every page.
     *
     * @param  iterable<mixed>  $nodes
     * @return Collection<int, SitemapPageData>
     */
    private function publiclyReadable(iterable $nodes): Collection
    {
        $nodes = $this->pageNodes($nodes);

        if ($this->withEditUrl) {
            return collect($nodes);
        }

        $candidates = [];
        $this->collectCandidates($nodes, $candidates);
        $decisions = resolve(PublicPageReadEligibilityRegistry::class)->allowsMany(array_values($candidates));
        $byNode = array_combine(array_keys($candidates), $decisions);

        return collect($this->filterReadable($nodes, $byNode));
    }

    /**
     * @param  iterable<mixed>  $nodes
     * @return list<SitemapPageData>
     */
    private function pageNodes(iterable $nodes): array
    {
        $pages = [];

        foreach ($nodes as $node) {
            if ($node instanceof SitemapPageData) {
                $pages[] = $node;
            }
        }

        return $pages;
    }

    /**
     * @param  list<SitemapPageData>  $nodes
     * @param  array<string, PublicPageReadCandidateData>  $candidates
     */
    private function collectCandidates(array $nodes, array &$candidates): void
    {
        $siteId = is_numeric($this->site->getKey()) ? (int) $this->site->getKey() : null;
        $languageId = is_numeric($this->language->getKey()) ? (int) $this->language->getKey() : null;

        foreach ($nodes as $node) {
            $id = (string) spl_object_id($node);
            $candidates['n' . $id] = new PublicPageReadCandidateData(
                url: $node->url,
                siteId: $siteId,
                languageId: $languageId,
                pageableType: $node->pageableType,
                pageableId: $node->pageId,
                publishedOn: $this->domain->full_url,
            );

            foreach ($node->alternates as $position => $alternate) {
                $candidates['a' . $id . ':' . $position] = new PublicPageReadCandidateData(url: $alternate->href, siteId: $siteId, publishedOn: $this->domain->full_url);
            }

            if ($node->children instanceof Collection) {
                $this->collectCandidates($this->pageNodes($node->children), $candidates);
            }
        }
    }

    /**
     * @param  list<SitemapPageData>  $nodes
     * @param  array<string, bool>  $decisions
     * @return list<SitemapPageData>
     */
    private function filterReadable(array $nodes, array $decisions): array
    {
        $readable = [];

        foreach ($nodes as $node) {
            $id = (string) spl_object_id($node);
            $children = $node->children instanceof Collection
                ? $this->filterReadable($this->pageNodes($node->children), $decisions)
                : null;

            if (($decisions['n' . $id] ?? false) !== true) {
                array_push($readable, ...($children ?? []));

                continue;
            }

            $node->alternates = array_values(array_filter(
                $node->alternates,
                static fn (int $position): bool => ($decisions['a' . $id . ':' . $position] ?? false) === true,
                ARRAY_FILTER_USE_KEY,
            ));
            $node->children = $children === null ? null : collect($children);
            $readable[] = $node;
        }

        return $readable;
    }

    private function upsertTopLevel(SitemapPageData $item): void
    {
        $key = $this->nodeKey($item);

        $index = $this->indexByKey[$key] ?? null;

        if ($index === null) {
            $this->pages->push($item);
            $newIndex = $this->pages->count() - 1;
            $this->indexByKey[$key] = $newIndex;

            return;
        }

        /** @var SitemapPageData $existing */
        $existing = $this->pages->get($index);
        $existing->children = $this->mergeChildren($existing->children ?? collect(), $item->children ?? collect());

        $existing = $this->mergeNodeAttributes($existing, $item);

        $this->pages->put($index, $existing);
    }

    private function mergeNodeAttributes(SitemapPageData $existing, SitemapPageData $incoming): SitemapPageData
    {
        $existing->label = $incoming->label;

        if ($incoming->lastModified instanceof CarbonImmutable) {
            $existing->lastModified = $incoming->lastModified;
        }

        if ($incoming->changeFrequency !== null) {
            $existing->changeFrequency = $incoming->changeFrequency;
        }

        if ($incoming->priority !== null) {
            $existing->priority = $incoming->priority;
        }

        if ($incoming->editUrl !== null) {
            $existing->editUrl = $incoming->editUrl;
        }

        return $existing;
    }

    /**
     * @param  Collection<int, SitemapPageData>  $existingChildren
     * @param  Collection<int, SitemapPageData>  $incomingChildren
     * @return Collection<int, SitemapPageData>
     */
    private function mergeChildren(Collection $existingChildren, Collection $incomingChildren): Collection
    {
        $byKey = [];
        $merged = [];

        $getKey = static function (SitemapPageData $node): string {
            if ($node->pageableType !== null && $node->pageId !== null) {
                return $node->pageableType . ':' . $node->pageId;
            }

            // fallback to URL as the deterministic identifier
            return 'url:' . $node->url;
        };

        $register = function (SitemapPageData $node, int $idx) use (&$byKey, &$merged, $getKey): void {
            $merged[$idx] = $node;

            $key = $getKey($node);
            $byKey[$key] = $idx;
        };

        foreach ($existingChildren as $idx => $child) {
            $register($child, $idx);
        }

        foreach ($incomingChildren as $child) {
            $key = $getKey($child);

            $matchIdx = $byKey[$key] ?? null;

            if ($matchIdx === null) {
                $merged[] = $child;
                $newIdx = array_key_last($merged);
                $register($child, $newIdx);

                continue;
            }

            $existing = $merged[$matchIdx];
            $existing->children = $this->mergeChildren(
                $existing->children ?? collect(),
                $child->children ?? collect(),
            );
            $existing = $this->mergeNodeAttributes($existing, $child);
            $merged[$matchIdx] = $existing;
        }

        return collect($merged)->values();
    }

    private function nodeKey(SitemapPageData $node): string
    {
        if ($node->pageableType !== null && $node->pageId !== null) {
            return $node->pageableType . ':' . $node->pageId;
        }

        return 'url:' . $node->url;
    }
}
