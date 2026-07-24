<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\Sitemap;

use Capell\Core\Enums\CacheEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\BuildPublicUrlRegistryAction;
use Capell\SiteDiscovery\Actions\DiscoverPublicUrlsAction;
use Capell\SiteDiscovery\Actions\ValidateSitemapQualityAction;
use Capell\SiteDiscovery\Data\DiscoverableUrlData;
use Capell\SiteDiscovery\Data\PublicUrlRegistryEntryData;
use Capell\SiteDiscovery\Data\SitemapAlternateData;
use Capell\SiteDiscovery\Data\SitemapImageData;
use Capell\SiteDiscovery\Data\SitemapNewsData;
use Capell\SiteDiscovery\Data\SitemapPageData;
use Capell\SiteDiscovery\Data\SitemapUrlItemData;
use Capell\SiteDiscovery\Data\SitemapVideoData;
use Capell\SiteDiscovery\Exceptions\SitemapGeneratorException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class XmlSitemapGenerator
{
    /**
     * Optional factory for injecting a testable SitemapGenerator instance.
     */
    protected ?Closure $generatorFactory = null;

    /**
     * Set a custom generator factory (for tests).
     */
    public function setGeneratorFactory(?Closure $factory): void
    {
        $this->generatorFactory = $factory;
    }

    /**
     * Delete all sitemap files (main, chunks, state) for every domain of the site.
     */
    public function delete(Site $site): void
    {
        $disk = config('capell.sitemap.disk', 'local');
        $directory = config('capell.sitemap.directory', 'sitemaps');
        $storage = Storage::disk($disk);
        $state = new SitemapStateStore($disk, $directory);

        $this->ensureDirectoryExists($storage, $directory);

        $site->siteDomains->each(function (SiteDomain $domain) use ($directory, $storage, $state): void {
            $domainKey = $domain->getDomainKey();

            // Main sitemap file
            $storage->delete($directory . '/' . $domainKey . '.xml');

            // Chunk files: {domainKey}-p{n}.xml
            foreach ($storage->files($directory) as $file) {
                $basename = basename($file);
                if (str_starts_with($basename, $domainKey . '-p') && str_ends_with($basename, '.xml')) {
                    $storage->delete($file);
                }
            }

            // State file
            $state->delete($domainKey);
        });
    }

    /**
     * Backwards-compatible API to generate the sitemap without progress callbacks.
     */
    public function generate(Site $site): string
    {
        $site->load('siteDomains.language');

        $domain = $site->siteDomains->first();
        if ($domain === null) {
            throw new SitemapGeneratorException('No site domain found for site ID ' . $site->id);
        }

        $disk = config('capell.sitemap.disk', 'local');
        $directory = config('capell.sitemap.directory', 'sitemaps');
        $filename = $domain->getDomainKey() . '.xml';
        $filePath = $directory . '/' . $filename;
        $storage = Storage::disk($disk);
        $primaryDomainTotal = null;

        $this->process(
            $site,
            end: static function (int $total, string $generatedPath) use (&$primaryDomainTotal, $filePath): void {
                if ($generatedPath === $filePath) {
                    $primaryDomainTotal = $total;
                }
            },
        );

        if (! $storage->exists($filePath)) {
            if ($primaryDomainTotal === 0) {
                return $this->toXml([]);
            }

            throw new SitemapGeneratorException(
                '[SitemapGenerator] Sitemap XML file not found: ' . $filePath .
                ' | path_exists=no' .
                ' | dir_exists=' . ($storage->exists($directory) ? 'yes' : 'no') .
                ' | dir_contents=' . json_encode($storage->allFiles($directory)),
            );
        }

        $xml = $storage->get($filePath);

        throw_unless(is_string($xml), SitemapGeneratorException::class, 'Failed to read sitemap XML file: ' . $filePath);

        return $xml;
    }

    /**
     * Generate sitemaps for the given site with optional progress callbacks.
     *
     * The $end closure receives: (int $total, string $filePath)
     */
    public function process(
        Site $site,
        ?Closure $start = null,
        ?Closure $prepare = null,
        ?Closure $checkpoint = null,
        ?Closure $end = null,
    ): void {
        $site->load('siteDomains.language');

        $site->siteDomains->each(function (SiteDomain $domain) use ($site, $start, $prepare, $checkpoint, $end): void {
            $this->generateForDomain($site, $domain, $start, $prepare, $checkpoint, $end);
        });
    }

    /**
     * Incremental variant: only rewrites a domain's sitemap when page state has changed
     * since the last run. Also handles sitemap index generation for large sitemaps.
     *
     * The $end closure receives: (int $total, string $filePath, bool $regenerated)
     * $regenerated is true when the XML was rewritten, false when it was skipped.
     */
    public function processIncremental(
        Site $site,
        ?Closure $start = null,
        ?Closure $prepare = null,
        ?Closure $checkpoint = null,
        ?Closure $end = null,
    ): void {
        $site->load('siteDomains.language');

        $site->siteDomains->each(function (SiteDomain $domain) use ($site, $start, $prepare, $checkpoint, $end): void {
            $this->generateForDomainIncremental($site, $domain, $start, $prepare, $checkpoint, $end);
        });
    }

    protected function generateForDomain(
        Site $site,
        SiteDomain $domain,
        ?Closure $start = null,
        ?Closure $prepare = null,
        ?Closure $checkpoint = null,
        ?Closure $end = null,
    ): void {
        $disk = config('capell.sitemap.disk', 'local');
        $directory = config('capell.sitemap.directory', 'sitemaps');
        $storage = Storage::disk($disk);

        $this->ensureDirectoryExists($storage, $directory);

        if ($this->generatorFactory instanceof Closure) {
            ($this->generatorFactory)($site, $domain, $start, $prepare, $checkpoint, $end);

            return;
        }

        if ($start instanceof Closure) {
            $start($domain);
        }

        $language = $domain->language;

        throw_unless($language instanceof Language, SitemapGeneratorException::class, 'Sitemap domain requires a language.');

        $this->forgetSitemapPageCaches($site->id, (int) $language->id);
        $items = $this->buildUrlItems($site, $domain);
        $total = count($items);

        if ($prepare instanceof Closure) {
            $prepare($total, $domain->getDomainKey());
        }

        if ($total > 0) {
            foreach ($items as $item) {
                if ($checkpoint instanceof Closure) {
                    $checkpoint($item->loc);
                }
            }

            $filePath = $this->writeItems($storage, $directory, $domain, $items);

            // Save state so the next incremental run has a baseline.
            $state = new SitemapStateStore($disk, $directory);
            $state->save($domain->getDomainKey(), $state->buildUrlMap($items));
        } else {
            $filePath = $directory . '/' . $domain->getDomainKey() . '.xml';
            $this->deleteDomainFiles($storage, $directory, $domain->getDomainKey());

            $state = new SitemapStateStore($disk, $directory);
            $state->save($domain->getDomainKey(), []);
        }

        if ($end instanceof Closure) {
            $end($total, $filePath);
        }
    }

    protected function generateForDomainIncremental(
        Site $site,
        SiteDomain $domain,
        ?Closure $start = null,
        ?Closure $prepare = null,
        ?Closure $checkpoint = null,
        ?Closure $end = null,
    ): void {
        $disk = config('capell.sitemap.disk', 'local');
        $directory = config('capell.sitemap.directory', 'sitemaps');
        $storage = Storage::disk($disk);
        $domainKey = $domain->getDomainKey();

        $this->ensureDirectoryExists($storage, $directory);

        if ($this->generatorFactory instanceof Closure) {
            ($this->generatorFactory)($site, $domain, $start, $prepare, $checkpoint, $end);

            return;
        }

        if ($start instanceof Closure) {
            $start($domain);
        }

        $language = $domain->language;

        throw_unless($language instanceof Language, SitemapGeneratorException::class, 'Sitemap domain requires a language.');

        $this->forgetSitemapPageCaches($site->id, (int) $language->id);
        $items = $this->buildUrlItems($site, $domain);
        $total = count($items);

        if ($prepare instanceof Closure) {
            $prepare($total, $domain->getDomainKey());
        }

        $state = new SitemapStateStore($disk, $directory);
        $currentMap = $state->buildUrlMap($items);
        $storedMap = $state->load($domainKey);

        if (! $state->hasChanged($currentMap, $storedMap)) {
            // Nothing changed — skip disk I/O entirely.
            if ($end instanceof Closure) {
                $end($total, $directory . '/' . $domainKey . '.xml', false);
            }

            return;
        }

        if ($total > 0) {
            foreach ($items as $item) {
                if ($checkpoint instanceof Closure) {
                    $checkpoint($item->loc);
                }
            }

            $filePath = $this->writeItems($storage, $directory, $domain, $items);
            $state->save($domainKey, $currentMap);
        } else {
            $filePath = $directory . '/' . $domainKey . '.xml';
            $this->deleteDomainFiles($storage, $directory, $domainKey);
            $state->save($domainKey, []);
        }

        if ($end instanceof Closure) {
            $end($total, $filePath, true);
        }
    }

    /**
     * Write items to disk, splitting into chunks + an index file when the
     * item count exceeds capell.sitemap.max_urls_per_file (default 50 000).
     *
     * Returns the path of the primary file that was written (main or index).
     *
     * @param  array<int, SitemapUrlItemData>  $items
     */
    protected function writeItems(
        Filesystem $storage,
        string $directory,
        SiteDomain $domain,
        array $items,
    ): string {
        $maxPerFile = max(1, config('capell.sitemap.max_urls_per_file', 50000));
        $domainKey = $domain->getDomainKey();
        $mainPath = $directory . '/' . $domainKey . '.xml';

        $this->deleteChunkFiles($storage, $directory, $domainKey);

        if (count($items) <= $maxPerFile) {
            $storage->put($mainPath, $this->toXml($items));

            return $mainPath;
        }

        $xmlPath = rtrim((string) config('capell.sitemap.xml_path', '/sitemap-xml'), '/');
        $baseUrl = rtrim($domain->full_url, '/') . $xmlPath;
        $now = now()->format(DATE_ATOM);
        $indexEntries = [];
        $chunk = [];
        $chunkNumber = 1;

        foreach ($items as $item) {
            $chunk[] = $item;

            if (count($chunk) < $maxPerFile) {
                continue;
            }

            $indexEntries[] = $this->writeChunk($storage, $directory, $domainKey, $baseUrl, $chunkNumber, $chunk, $now);
            $chunk = [];
            $chunkNumber++;
        }

        if ($chunk !== []) {
            $indexEntries[] = $this->writeChunk($storage, $directory, $domainKey, $baseUrl, $chunkNumber, $chunk, $now);
        }

        $storage->put($mainPath, $this->toIndexXml($indexEntries));

        return $mainPath;
    }

    protected function ensureDirectoryExists(Filesystem $storage, string $directory): void
    {
        if (! $storage->exists($directory)) {
            $storage->makeDirectory($directory);
        }
    }

    /**
     * @param  array<int, SitemapUrlItemData>  $chunk
     * @return array{loc: string, lastmod: string}
     */
    private function writeChunk(
        Filesystem $storage,
        string $directory,
        string $domainKey,
        string $baseUrl,
        int $chunkNumber,
        array $chunk,
        string $lastModified,
    ): array {
        $chunkFile = $directory . '/' . $domainKey . '-p' . $chunkNumber . '.xml';
        $storage->put($chunkFile, $this->toXml($chunk));

        return [
            'loc' => $baseUrl . '?p=' . $chunkNumber,
            'lastmod' => $lastModified,
        ];
    }

    /**
     * @param  array<int, SitemapPageData>  $sitemapPages
     * @return array<int, SitemapUrlItemData>
     */
    private function flattenPages(array $sitemapPages): array
    {
        $flat = [];

        foreach ($sitemapPages as $sitemapPage) {
            $this->appendPageAndChildren($flat, $sitemapPage);
        }

        return $flat;
    }

    /**
     * @param  array<int, SitemapUrlItemData>  $flat
     */
    private function appendPageAndChildren(array &$flat, SitemapPageData $sitemapPage): void
    {
        if ($sitemapPage->url === '') {
            return;
        }

        $flat[] = new SitemapUrlItemData(
            loc: $sitemapPage->url,
            lastmod: $sitemapPage->lastModified,
            changefreq: $sitemapPage->changeFrequency,
            priority: $sitemapPage->priority !== null ? number_format($sitemapPage->priority, 1, '.', '') : null,
        );

        foreach ($this->normalizeChildren($sitemapPage->children) as $child) {
            $this->appendPageAndChildren($flat, $child);
        }
    }

    /**
     * @param  Collection<int, SitemapPageData>|null  $children
     * @return array<int, SitemapPageData>
     */
    private function normalizeChildren(?Collection $children): array
    {
        if (! $children instanceof Collection) {
            return [];
        }

        return $children->values()->all();
    }

    /**
     * @return array<int, SitemapUrlItemData>
     */
    private function buildUrlItems(Site $site, SiteDomain $domain): array
    {
        $language = $domain->language;

        throw_unless($language instanceof Language, SitemapGeneratorException::class, 'Sitemap domain requires a language.');

        $builder = new SitemapBuilder($site, $domain, $language);
        $items = $this->flattenPages($builder->build()->all());

        $registryEntries = $this->registryEntriesForDomain($site, $language, $domain);
        $qualityReport = ValidateSitemapQualityAction::run($registryEntries);

        $registryItems = $registryEntries
            ->reject(fn (PublicUrlRegistryEntryData $entry): bool => $qualityReport->hasErrorsForUrl($entry->canonicalUrl))
            ->map(fn (PublicUrlRegistryEntryData $entry): SitemapUrlItemData => new SitemapUrlItemData(
                loc: $entry->canonicalUrl,
                lastmod: $entry->lastModified instanceof CarbonImmutable ? $entry->lastModified : null,
                changefreq: $entry->changeFrequency,
                priority: $entry->priority,
            ));

        $legacyContributedItems = DiscoverPublicUrlsAction::run($site, $language, includePages: false, domain: $domain)
            ->map(fn (DiscoverableUrlData $url): SitemapUrlItemData => new SitemapUrlItemData(
                loc: $url->loc,
                lastmod: $url->lastModified instanceof CarbonImmutable
                    ? $url->lastModified
                    : ($url->lastModified instanceof CarbonInterface ? CarbonImmutable::instance($url->lastModified) : null),
                changefreq: $url->changeFrequency,
                priority: $url->priority,
            ));

        return collect($items)
            ->merge($legacyContributedItems)
            ->merge($registryItems)
            ->reject(fn (SitemapUrlItemData $item): bool => str_contains($item->loc, '*'))
            ->unique(fn (SitemapUrlItemData $item): string => $item->loc)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, PublicUrlRegistryEntryData>
     */
    private function registryEntriesForDomain(Site $site, Language $language, SiteDomain $domain): Collection
    {
        return BuildPublicUrlRegistryAction::run()
            ->filter(fn (PublicUrlRegistryEntryData $entry): bool => $this->matchesSite($entry, $site))
            ->filter(fn (PublicUrlRegistryEntryData $entry): bool => $this->matchesLanguage($entry, $language))
            ->filter(fn (PublicUrlRegistryEntryData $entry): bool => $this->belongsToDomain($entry->canonicalUrl, $domain))
            ->values();
    }

    private function matchesSite(PublicUrlRegistryEntryData $entry, Site $site): bool
    {
        $siteKey = $site->getKey();

        if ($entry->siteId !== null && is_numeric($siteKey)) {
            return $entry->siteId === (int) $siteKey;
        }

        return (string) $entry->siteKey === (string) $siteKey;
    }

    private function matchesLanguage(PublicUrlRegistryEntryData $entry, Language $language): bool
    {
        $languageKey = $language->getKey();

        if ($entry->languageId !== null && is_numeric($languageKey)) {
            return $entry->languageId === (int) $languageKey;
        }

        return (string) $entry->languageKey === (string) $languageKey;
    }

    private function belongsToDomain(string $url, SiteDomain $domain): bool
    {
        $baseUrl = rtrim($domain->full_url, '/');

        return $url === $baseUrl
            || str_starts_with($url, $baseUrl . '/')
            || str_starts_with($url, $baseUrl . '?');
    }

    private function forgetSitemapPageCaches(int $siteId, int $languageId): void
    {
        $baseKey = CacheEnum::sitemapPages($siteId, $languageId);

        Cache::forget($baseKey);
        Cache::forget($baseKey . '.public');
        Cache::forget($baseKey . '.with-edit-urls');
    }

    private function deleteDomainFiles(Filesystem $storage, string $directory, string $domainKey): void
    {
        $storage->delete($directory . '/' . $domainKey . '.xml');
        $this->deleteChunkFiles($storage, $directory, $domainKey);
    }

    private function deleteChunkFiles(Filesystem $storage, string $directory, string $domainKey): void
    {
        foreach ($storage->files($directory) as $file) {
            $basename = basename($file);

            if (str_starts_with($basename, $domainKey . '-p') && str_ends_with($basename, '.xml')) {
                $storage->delete($file);
            }
        }
    }

    /**
     * Build a standard <urlset> XML document.
     *
     * @param  array<int, SitemapUrlItemData>  $items
     */
    private function toXml(array $items): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= $this->urlsetExtensionNamespaces($items);
        $xml .= '>';

        foreach ($items as $item) {
            $xml .= '<url>';
            $xml .= '<loc>' . htmlspecialchars($item->loc, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>';
            $lastModified = $item->lastmod;
            if ($lastModified !== null && $lastModified !== '') {
                if ($lastModified instanceof DateTimeInterface) {
                    $lastModified = $lastModified->format(DATE_ATOM);
                }

                $xml .= '<lastmod>' . htmlspecialchars($lastModified, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</lastmod>';
            }

            if ($item->changefreq !== null && $item->changefreq !== '') {
                $xml .= '<changefreq>' . htmlspecialchars((string) $item->changefreq, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</changefreq>';
            }

            if ($item->priority !== null && $item->priority !== '') {
                $xml .= '<priority>' . htmlspecialchars((string) $item->priority, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</priority>';
            }

            $xml .= $this->alternateXml($item->alternates);
            $xml .= $this->imageXml($item->images);
            $xml .= $this->videoXml($item->videos);
            $xml .= $this->newsXml($item->news);

            $xml .= '</url>';
        }

        return $xml . '</urlset>';
    }

    /**
     * @param  array<int, SitemapUrlItemData>  $items
     */
    private function urlsetExtensionNamespaces(array $items): string
    {
        $namespaces = '';

        if (collect($items)->contains(fn (SitemapUrlItemData $item): bool => $item->alternates !== [])) {
            $namespaces .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml"';
        }

        if (collect($items)->contains(fn (SitemapUrlItemData $item): bool => $item->images !== [])) {
            $namespaces .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        }

        if (collect($items)->contains(fn (SitemapUrlItemData $item): bool => $item->videos !== [])) {
            $namespaces .= ' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"';
        }

        if (collect($items)->contains(fn (SitemapUrlItemData $item): bool => $item->news instanceof SitemapNewsData)) {
            $namespaces .= ' xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"';
        }

        return $namespaces;
    }

    /**
     * @param  list<SitemapAlternateData>  $alternates
     */
    private function alternateXml(array $alternates): string
    {
        $xml = '';

        foreach ($alternates as $alternate) {
            if ($alternate->hreflang === '') {
                continue;
            }

            if ($alternate->href === '') {
                continue;
            }

            $xml .= sprintf(
                '<xhtml:link rel="alternate" hreflang="%s" href="%s" />',
                htmlspecialchars($alternate->hreflang, ENT_XML1 | ENT_COMPAT, 'UTF-8'),
                htmlspecialchars($alternate->href, ENT_XML1 | ENT_COMPAT, 'UTF-8'),
            );
        }

        return $xml;
    }

    /**
     * @param  list<SitemapImageData>  $images
     */
    private function imageXml(array $images): string
    {
        $xml = '';

        foreach ($images as $image) {
            $xml .= '<image:image>';
            $xml .= $this->xmlElement('image:loc', $image->loc);
            $xml .= $this->xmlElement('image:caption', $image->caption);
            $xml .= $this->xmlElement('image:title', $image->title);
            $xml .= $this->xmlElement('image:license', $image->license);
            $xml .= '</image:image>';
        }

        return $xml;
    }

    /**
     * @param  list<SitemapVideoData>  $videos
     */
    private function videoXml(array $videos): string
    {
        $xml = '';

        foreach ($videos as $video) {
            $xml .= '<video:video>';
            $xml .= $this->xmlElement('video:thumbnail_loc', $video->thumbnailLoc);
            $xml .= $this->xmlElement('video:title', $video->title);
            $xml .= $this->xmlElement('video:description', $video->description);
            $xml .= $this->xmlElement('video:content_loc', $video->contentLoc);
            $xml .= $this->xmlElement('video:player_loc', $video->playerLoc);
            $xml .= $this->xmlElement('video:duration', $video->duration);
            $xml .= $this->xmlElement('video:publication_date', $video->publicationDate?->format(DATE_ATOM));
            $xml .= '</video:video>';
        }

        return $xml;
    }

    private function newsXml(?SitemapNewsData $news): string
    {
        if (! $news instanceof SitemapNewsData) {
            return '';
        }

        $xml = '<news:news>';
        $xml .= '<news:publication>';
        $xml .= $this->xmlElement('news:name', $news->publicationName);
        $xml .= $this->xmlElement('news:language', $news->publicationLanguage);
        $xml .= '</news:publication>';
        $xml .= $this->xmlElement('news:publication_date', $news->publicationDate->format(DATE_ATOM));
        $xml .= $this->xmlElement('news:title', $news->title);

        return $xml . '</news:news>';
    }

    private function xmlElement(string $name, int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return sprintf(
            '<%s>%s</%s>',
            $name,
            htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8'),
            $name,
        );
    }

    /**
     * Build a <sitemapindex> XML document referencing chunk files.
     *
     * @param  array<int, array{loc: string, lastmod: string}>  $sitemaps
     */
    private function toIndexXml(array $sitemaps): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($sitemaps as $sitemap) {
            $xml .= '<sitemap>';
            $xml .= '<loc>' . htmlspecialchars($sitemap['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>';
            $xml .= '<lastmod>' . htmlspecialchars($sitemap['lastmod'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</lastmod>';
            $xml .= '</sitemap>';
        }

        return $xml . '</sitemapindex>';
    }
}
