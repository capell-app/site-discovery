<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\Sitemap;

use Capell\Core\Enums\CacheEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\DiscoveryFoundation\Actions\BuildPublicUrlRegistryAction;
use Capell\DiscoveryFoundation\Data\PublicUrlRegistryEntryData;
use Capell\SiteDiscovery\Actions\DiscoverPublicUrlsAction;
use Capell\SiteDiscovery\Actions\PromoteStagedSitemapSetAction;
use Capell\SiteDiscovery\Actions\ValidateSitemapQualityAction;
use Capell\SiteDiscovery\Data\DiscoverableUrlData;
use Capell\SiteDiscovery\Data\SitemapAlternateData;
use Capell\SiteDiscovery\Data\SitemapImageData;
use Capell\SiteDiscovery\Data\SitemapNewsData;
use Capell\SiteDiscovery\Data\SitemapPageData;
use Capell\SiteDiscovery\Data\SitemapUrlItemData;
use Capell\SiteDiscovery\Data\SitemapVideoData;
use Capell\SiteDiscovery\Data\StagedSitemapDomainData;
use Capell\SiteDiscovery\Data\StagedSitemapSetData;
use Capell\SiteDiscovery\Exceptions\SitemapGeneratorException;
use Capell\SiteDiscovery\Support\Sitemap\Pages\PagesSitemap;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
     * Delete the currently published sitemap set and compatibility files.
     */
    public function delete(Site $site): void
    {
        $site->load('siteDomains.language');

        $disk = $this->disk();
        $directory = $this->directory();
        $storage = Storage::disk($disk);
        $state = new SitemapStateStore($disk, $directory);
        $publicationStore = resolve(SitemapPublicationStore::class);

        $publicationStore->forgetSite($this->siteKey($site));

        $site->siteDomains->each(function (SiteDomain $domain) use ($directory, $storage, $state): void {
            $domainKey = $domain->getDomainKey();
            $this->deleteDomainFiles($storage, $directory, $domainKey);
            $state->delete($domainKey);
        });
    }

    /**
     * Backwards-compatible API to generate and atomically publish a complete sitemap set.
     */
    public function generate(Site $site): string
    {
        $this->process($site);
        $site->load('siteDomains.language');

        $domain = $site->siteDomains->first();
        throw_unless($domain instanceof SiteDomain, SitemapGeneratorException::class, 'No site domain found for site ID ' . $site->id);

        $filename = $domain->getDomainKey() . '.xml';
        $filePath = resolve(SitemapPublicationStore::class)->resolveFilePath($domain->getDomainKey(), $filename);

        throw_unless(is_string($filePath), SitemapGeneratorException::class, 'Published sitemap XML file not found: ' . $filename);

        $xml = Storage::disk($this->disk())->get($filePath);

        throw_unless(is_string($xml), SitemapGeneratorException::class, 'Failed to read published sitemap XML file: ' . $filePath);

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
        $stagedSet = $this->stage(
            site: $site,
            start: $start,
            prepare: $prepare,
            checkpoint: $checkpoint,
        );
        $publishedSet = PromoteStagedSitemapSetAction::run($stagedSet);

        $this->writeCompatibilityFiles($publishedSet);
        $this->signalCompletedDomains($publishedSet, $end);
    }

    /**
     * Incremental generation skips publication only when every domain is unchanged.
     * When one domain changes, a complete replacement set is staged and promoted.
     *
     * The $end closure receives: (int $total, string $filePath, bool $regenerated)
     */
    public function processIncremental(
        Site $site,
        ?Closure $start = null,
        ?Closure $prepare = null,
        ?Closure $checkpoint = null,
        ?Closure $end = null,
    ): void {
        $site->load('siteDomains.language');
        $contexts = $this->buildDomainContexts($site, $start, $prepare);
        $state = new SitemapStateStore($this->disk(), $this->directory());
        $publicationStore = resolve(SitemapPublicationStore::class);
        $hasChanges = false;

        foreach ($contexts as $context) {
            $domainKey = $context['domain']->getDomainKey();
            $storedMap = $publicationStore->hasDomain($domainKey)
                ? $publicationStore->urlState($domainKey)
                : $state->load($domainKey);

            if ($state->hasChanged($context['urlState'], $storedMap)) {
                $hasChanges = true;
            }
        }

        if (! $hasChanges) {
            foreach ($contexts as $context) {
                if ($end instanceof Closure) {
                    $end(
                        count($context['items']),
                        $this->directory() . '/' . $context['domain']->getDomainKey() . '.xml',
                        false,
                    );
                }
            }

            return;
        }

        $stagedSet = $this->stageContexts($site, $contexts, $checkpoint);
        $publishedSet = PromoteStagedSitemapSetAction::run($stagedSet);

        $this->writeCompatibilityFiles($publishedSet);
        $this->signalCompletedDomains($publishedSet, $end, true);
    }

    public function stage(
        Site $site,
        ?Closure $start = null,
        ?Closure $prepare = null,
        ?Closure $checkpoint = null,
    ): StagedSitemapSetData {
        $site->load('siteDomains.language');

        return $this->stageContexts(
            site: $site,
            contexts: $this->buildDomainContexts($site, $start, $prepare),
            checkpoint: $checkpoint,
        );
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
            throw_unless(
                $storage->put($mainPath, $this->toXml($items)),
                SitemapGeneratorException::class,
                'Unable to write staged sitemap XML: ' . $mainPath,
            );

            return $mainPath;
        }

        $baseUrl = $this->publicChunkBaseUrl($domain);
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

        throw_unless(
            $storage->put($mainPath, $this->toIndexXml($indexEntries)),
            SitemapGeneratorException::class,
            'Unable to write staged sitemap index XML: ' . $mainPath,
        );

        return $mainPath;
    }

    protected function ensureDirectoryExists(Filesystem $storage, string $directory): void
    {
        if (! $storage->exists($directory)) {
            $storage->makeDirectory($directory);
        }
    }

    /**
     * @return list<array{domain: SiteDomain, items: array<int, SitemapUrlItemData>, urlState: array<string, string>}>
     */
    private function buildDomainContexts(
        Site $site,
        ?Closure $start,
        ?Closure $prepare,
    ): array {
        throw_if($site->siteDomains->isEmpty(), SitemapGeneratorException::class, 'No site domain found for site ID ' . $site->id);

        $contexts = [];
        $state = new SitemapStateStore($this->disk(), $this->directory());

        foreach ($site->siteDomains as $domain) {
            if ($this->generatorFactory instanceof Closure) {
                throw new SitemapGeneratorException('Custom sitemap generator factories cannot bypass staged publication.');
            }

            if ($start instanceof Closure) {
                $start($domain);
            }

            $language = $domain->language;
            throw_unless($language instanceof Language, SitemapGeneratorException::class, 'Sitemap domain requires a language.');

            $this->forgetSitemapPageCaches(
                $this->integerModelKey($site, 'Site'),
                $this->integerModelKey($language, 'Language'),
            );
            $items = $this->buildUrlItems($site, $domain);

            if ($prepare instanceof Closure) {
                $prepare(count($items), $domain->getDomainKey());
            }

            $contexts[] = [
                'domain' => $domain,
                'items' => $items,
                'urlState' => $state->buildUrlMap($items),
            ];
        }

        return $contexts;
    }

    /**
     * @param  list<array{domain: SiteDomain, items: array<int, SitemapUrlItemData>, urlState: array<string, string>}>  $contexts
     */
    private function stageContexts(
        Site $site,
        array $contexts,
        ?Closure $checkpoint,
    ): StagedSitemapSetData {
        $storage = Storage::disk($this->disk());
        $siteKey = $this->siteKey($site);
        $generationId = $siteKey . '-' . bin2hex(random_bytes(16));
        $stagingDirectory = $this->directory() . '/.staging/' . $generationId;
        $domains = [];

        try {
            foreach ($contexts as $context) {
                foreach ($context['items'] as $item) {
                    if ($checkpoint instanceof Closure) {
                        $checkpoint($item->loc);
                    }
                }

                $domain = $context['domain'];
                $mainPath = $this->writeItems($storage, $stagingDirectory, $domain, $context['items']);
                $mainXml = $storage->get($mainPath);

                throw_unless(is_string($mainXml), SitemapGeneratorException::class, 'Unable to read staged sitemap main XML.');

                $domainKey = $domain->getDomainKey();
                $filenames = [];

                foreach ($storage->files($stagingDirectory) as $path) {
                    $filename = basename($path);

                    if ($filename === $domainKey . '.xml'
                        || preg_match('/^' . preg_quote($domainKey, '/') . '-p[1-9][0-9]*\.xml$/', $filename) === 1) {
                        $filenames[] = $filename;
                    }
                }

                sort($filenames);
                $language = $domain->language;

                throw_unless($language instanceof Language, SitemapGeneratorException::class, 'Sitemap domain requires a language.');

                $domains[] = new StagedSitemapDomainData(
                    domainKey: $domainKey,
                    languageId: $this->integerModelKey($language, 'Language'),
                    mainFilename: basename($mainPath),
                    filenames: $filenames,
                    urlCount: count($context['items']),
                    urlState: $context['urlState'],
                    etag: hash('sha256', $mainXml),
                    publicChunkBaseUrl: $this->publicChunkBaseUrl($domain),
                );
            }

            return new StagedSitemapSetData(
                siteKey: $siteKey,
                generationId: $generationId,
                directory: $stagingDirectory,
                domains: $domains,
            );
        } catch (Throwable $throwable) {
            $storage->deleteDirectory($stagingDirectory);

            throw $throwable;
        }
    }

    private function signalCompletedDomains(
        StagedSitemapSetData $sitemapSet,
        ?Closure $end,
        ?bool $regenerated = null,
    ): void {
        if (! $end instanceof Closure) {
            return;
        }

        foreach ($sitemapSet->domains as $domain) {
            $arguments = [
                $domain->urlCount,
                $this->directory() . '/' . $domain->mainFilename,
            ];

            if ($regenerated !== null) {
                $arguments[] = $regenerated;
            }

            $end(...$arguments);
        }
    }

    private function writeCompatibilityFiles(StagedSitemapSetData $sitemapSet): void
    {
        $storage = Storage::disk($this->disk());
        $state = new SitemapStateStore($this->disk(), $this->directory());

        foreach ($sitemapSet->domains as $domain) {
            try {
                if ($domain->urlCount > 0) {
                    foreach ($domain->filenames as $filename) {
                        $contents = $storage->get($sitemapSet->directory . '/' . $filename);

                        if (is_string($contents)) {
                            $storage->put($this->directory() . '/' . $filename, $contents);
                        }
                    }
                } else {
                    $storage->delete($this->directory() . '/' . $domain->mainFilename);
                }

                $publishedFilenames = $domain->urlCount > 0 ? $domain->filenames : [];
                foreach ($storage->files($this->directory()) as $path) {
                    $filename = basename($path);

                    if (str_starts_with($filename, $domain->domainKey . '-p')
                        && str_ends_with($filename, '.xml')
                        && ! in_array($filename, $publishedFilenames, true)) {
                        $storage->delete($path);
                    }
                }

                $state->save($domain->domainKey, $domain->urlState);
            } catch (Throwable $throwable) {
                Log::notice('Site Discovery could not refresh a legacy sitemap compatibility file.', [
                    'domain_key' => $domain->domainKey,
                    'exception' => $throwable,
                ]);
            }
        }
    }

    private function disk(): string
    {
        $disk = config('capell.sitemap.disk', 'local');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }

    private function directory(): string
    {
        $directory = config('capell.sitemap.directory', 'sitemaps');

        return is_string($directory) && trim($directory, '/') !== ''
            ? trim($directory, '/')
            : 'sitemaps';
    }

    private function siteKey(Site $site): string
    {
        $siteKey = $site->getKey();

        throw_unless(is_int($siteKey) || (is_string($siteKey) && $siteKey !== ''), SitemapGeneratorException::class, 'Site must have a scalar key.');

        return is_int($siteKey) ? (string) $siteKey : $siteKey;
    }

    private function integerModelKey(Site|Language $model, string $modelName): int
    {
        $modelKey = $model->getKey();

        if (is_int($modelKey)) {
            return $modelKey;
        }

        if (is_string($modelKey) && ctype_digit($modelKey)) {
            return (int) $modelKey;
        }

        throw new SitemapGeneratorException($modelName . ' must have an integer key.');
    }

    private function publicChunkBaseUrl(SiteDomain $domain): string
    {
        $configuredPath = config('capell.sitemap.xml_path', '/sitemap-xml');
        $xmlPath = is_string($configuredPath) && $configuredPath !== ''
            ? '/' . trim($configuredPath, '/')
            : '/sitemap-xml';

        return rtrim($domain->full_url, '/') . rtrim($xmlPath, '/');
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
        throw_unless(
            $storage->put($chunkFile, $this->toXml($chunk)),
            SitemapGeneratorException::class,
            'Unable to write staged sitemap chunk XML: ' . $chunkFile,
        );

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
            alternates: $sitemapPage->alternates,
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
        Cache::forget(CacheEnum::sitemapPages($siteId, $languageId));

        foreach (PagesSitemap::payloadCacheKeys($siteId, $languageId) as $key) {
            Cache::forget($key);
        }
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
