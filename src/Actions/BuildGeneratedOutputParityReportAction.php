<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\SiteDiscovery\Contracts\GeneratedOutputCoverageSource;
use Capell\SiteDiscovery\Data\DiscoveryOutputData;
use Capell\SiteDiscovery\Data\GeneratedOutputParityReportData;
use Capell\SiteDiscovery\Data\GeneratedOutputParityRowData;
use Capell\SiteDiscovery\Data\PublicUrlRegistryEntryData;
use Capell\SiteDiscovery\Data\SitemapUrlItemData;
use Capell\SiteDiscovery\Enums\GeneratedOutputParityStatus;
use Capell\SiteDiscovery\Enums\PublicUrlContentType;
use Capell\SiteDiscovery\Enums\PublicUrlIndexability;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPublicationStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use SimpleXMLElement;

/**
 * @method static GeneratedOutputParityReportData run(?iterable<array-key, PublicUrlRegistryEntryData> $registryEntries = null, ?iterable<array-key, DiscoveryOutputData|SitemapUrlItemData|string> $sitemapUrls = null, ?iterable<array-key, DiscoveryOutputData|SitemapUrlItemData|string> $aiDiscoveryUrls = null, ?iterable<array-key, DiscoveryOutputData|SitemapUrlItemData|string> $searchUrls = null, ?iterable<array-key, DiscoveryOutputData|SitemapUrlItemData|string> $htmlCacheUrls = null, ?iterable<array-key, DiscoveryOutputData|SitemapUrlItemData|string> $agentDeliveryUrls = null)
 */
final class BuildGeneratedOutputParityReportAction
{
    use AsFake;
    use AsObject;

    private const string SITEMAP_NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    /**
     * @param  iterable<array-key, PublicUrlRegistryEntryData>|null  $registryEntries
     * @param  iterable<array-key, DiscoveryOutputData|SitemapUrlItemData|string>|null  $sitemapUrls
     * @param  iterable<array-key, DiscoveryOutputData|SitemapUrlItemData|string>|null  $aiDiscoveryUrls
     * @param  iterable<array-key, DiscoveryOutputData|SitemapUrlItemData|string>|null  $searchUrls
     * @param  iterable<array-key, DiscoveryOutputData|SitemapUrlItemData|string>|null  $htmlCacheUrls
     * @param  iterable<array-key, DiscoveryOutputData|SitemapUrlItemData|string>|null  $agentDeliveryUrls
     */
    public function handle(
        ?iterable $registryEntries = null,
        ?iterable $sitemapUrls = null,
        ?iterable $aiDiscoveryUrls = null,
        ?iterable $searchUrls = null,
        ?iterable $htmlCacheUrls = null,
        ?iterable $agentDeliveryUrls = null,
    ): GeneratedOutputParityReportData {
        $registry = $registryEntries === null
            ? BuildPublicUrlRegistryAction::run()
            : collect($registryEntries)->values();

        $sitemapIndex = $sitemapUrls === null
            ? $this->discoverSitemapUrlIndex()
            : $this->buildUrlIndex($sitemapUrls);

        $coverageIndexes = $this->coverageIndexes($registry);
        $aiDiscoveryIndex = $this->buildGeneratedOutputIndex(GeneratedOutputCoverageSource::AI_DISCOVERY, $aiDiscoveryUrls, $coverageIndexes);
        $searchIndex = $this->buildGeneratedOutputIndex(GeneratedOutputCoverageSource::SEARCH, $searchUrls, $coverageIndexes);
        $htmlCacheIndex = $this->buildGeneratedOutputIndex(GeneratedOutputCoverageSource::HTML_CACHE, $htmlCacheUrls, $coverageIndexes);
        $agentDeliveryIndex = $this->buildGeneratedOutputIndex(GeneratedOutputCoverageSource::AGENT_DELIVERY, $agentDeliveryUrls, $coverageIndexes);

        $rows = $registry
            ->map(fn (PublicUrlRegistryEntryData $entry): GeneratedOutputParityRowData => $this->row(
                $entry,
                $sitemapIndex,
                $aiDiscoveryIndex,
                $searchIndex,
                $htmlCacheIndex,
                $agentDeliveryIndex,
            ))
            ->values()
            ->all();
        $rows = array_values($rows);

        $missingOutputUrls = collect($rows)
            ->filter(fn (GeneratedOutputParityRowData $row): bool => $row->hasMissingOutput())
            ->count();

        return new GeneratedOutputParityReportData(
            rows: $rows,
            totalUrls: count($rows),
            missingOutputUrls: $missingOutputUrls,
        );
    }

    /**
     * @param  array{available: bool, urls: array<string, true>}  $sitemapIndex
     * @param  array{available: bool, urls: array<string, true>}  $aiDiscoveryIndex
     * @param  array{available: bool, urls: array<string, true>}  $searchIndex
     * @param  array{available: bool, urls: array<string, true>}  $htmlCacheIndex
     * @param  array{available: bool, urls: array<string, true>}  $agentDeliveryIndex
     */
    private function row(
        PublicUrlRegistryEntryData $entry,
        array $sitemapIndex,
        array $aiDiscoveryIndex,
        array $searchIndex,
        array $htmlCacheIndex,
        array $agentDeliveryIndex,
    ): GeneratedOutputParityRowData {
        $isIndexable = $entry->indexability === PublicUrlIndexability::Indexable;
        $isSitemapEligible = $isIndexable && $entry->isSitemapEligible && $this->isSitemapContentType($entry->contentType);
        $isAiEligible = $isIndexable && $entry->isAiDiscoveryEligible;

        $sitemapStatus = $this->statusFor($entry->canonicalUrl, $isSitemapEligible, $sitemapIndex);
        $aiDiscoveryStatus = $this->statusFor($entry->canonicalUrl, $isAiEligible, $aiDiscoveryIndex);
        $searchStatus = $this->statusFor($entry->canonicalUrl, $isIndexable, $searchIndex);
        $htmlCacheStatus = $this->statusFor($entry->canonicalUrl, $isIndexable, $htmlCacheIndex);
        $agentDeliveryStatus = $this->statusFor($entry->canonicalUrl, $isAiEligible, $agentDeliveryIndex);

        return new GeneratedOutputParityRowData(
            canonicalUrl: $entry->canonicalUrl,
            sourcePackage: $entry->sourcePackage,
            siteKey: $entry->siteKey,
            languageKey: $entry->languageKey,
            siteId: $entry->siteId,
            languageId: $entry->languageId,
            languageCode: $entry->languageCode,
            routeName: $entry->routeName,
            lastModified: $entry->lastModified,
            indexability: $entry->indexability,
            contentType: $entry->contentType,
            isSitemapEligible: $entry->isSitemapEligible,
            isAiDiscoveryEligible: $entry->isAiDiscoveryEligible,
            sitemapStatus: $sitemapStatus,
            aiDiscoveryStatus: $aiDiscoveryStatus,
            searchStatus: $searchStatus,
            htmlCacheStatus: $htmlCacheStatus,
            agentDeliveryStatus: $agentDeliveryStatus,
            errors: $this->errorsFor($sitemapStatus, $aiDiscoveryStatus, $searchStatus, $htmlCacheStatus, $agentDeliveryStatus),
        );
    }

    private function isSitemapContentType(PublicUrlContentType $contentType): bool
    {
        return in_array($contentType, [
            PublicUrlContentType::Page,
            PublicUrlContentType::Article,
            PublicUrlContentType::Taxonomy,
            PublicUrlContentType::Other,
        ], true);
    }

    /**
     * @param  array{available: bool, urls: array<string, true>}  $index
     */
    private function statusFor(string $url, bool $eligible, array $index): GeneratedOutputParityStatus
    {
        if (! $eligible) {
            return GeneratedOutputParityStatus::NotEligible;
        }

        if (! $index['available']) {
            return GeneratedOutputParityStatus::Unknown;
        }

        $normalizedUrl = $this->normalizeUrl($url);

        return $normalizedUrl !== null && isset($index['urls'][$normalizedUrl])
            ? GeneratedOutputParityStatus::Present
            : GeneratedOutputParityStatus::Missing;
    }

    /**
     * @return list<string>
     */
    private function errorsFor(
        GeneratedOutputParityStatus $sitemapStatus,
        GeneratedOutputParityStatus $aiDiscoveryStatus,
        GeneratedOutputParityStatus $searchStatus,
        GeneratedOutputParityStatus $htmlCacheStatus,
        GeneratedOutputParityStatus $agentDeliveryStatus,
    ): array {
        $errors = collect([
            'missing_sitemap' => $sitemapStatus,
            'missing_ai_discovery' => $aiDiscoveryStatus,
            'missing_search' => $searchStatus,
            'missing_html_cache' => $htmlCacheStatus,
            'missing_agent_delivery' => $agentDeliveryStatus,
        ])
            ->filter(fn (GeneratedOutputParityStatus $status): bool => $status === GeneratedOutputParityStatus::Missing)
            ->keys()
            ->values()
            ->all();

        return array_values($errors);
    }

    /**
     * @param  iterable<array-key, DiscoveryOutputData|SitemapUrlItemData|string>|null  $urls
     * @return array{available: bool, urls: array<string, true>}
     */
    private function buildUrlIndex(?iterable $urls): array
    {
        if ($urls === null) {
            return ['available' => false, 'urls' => []];
        }

        $indexedUrls = [];

        foreach ($urls as $url) {
            $normalizedUrl = $this->urlFromOutput($url);

            if ($normalizedUrl !== null) {
                $indexedUrls[$normalizedUrl] = true;
            }
        }

        return [
            'available' => true,
            'urls' => $indexedUrls,
        ];
    }

    /**
     * @param  iterable<array-key, DiscoveryOutputData|SitemapUrlItemData|string>|null  $explicitUrls
     * @param  array<string, array{available: bool, urls: array<string, true>}>  $coverageIndexes
     * @return array{available: bool, urls: array<string, true>}
     */
    private function buildGeneratedOutputIndex(string $key, ?iterable $explicitUrls, array $coverageIndexes): array
    {
        if ($explicitUrls !== null) {
            return $this->buildUrlIndex($explicitUrls);
        }

        return $coverageIndexes[$key] ?? ['available' => false, 'urls' => []];
    }

    /**
     * @param  Collection<int, PublicUrlRegistryEntryData>  $registry
     * @return array<string, array{available: bool, urls: array<string, true>}>
     */
    private function coverageIndexes(Collection $registry): array
    {
        $indexes = [];

        collect(app()->tagged(GeneratedOutputCoverageSource::TAG))
            ->filter(fn (mixed $source): bool => $source instanceof GeneratedOutputCoverageSource)
            ->groupBy(fn (GeneratedOutputCoverageSource $source): string => $source->key())
            ->each(function (Collection $sources, string $key) use (&$indexes, $registry): void {
                $indexes[$key] = $this->buildUrlIndex(
                    $sources->flatMap(fn (GeneratedOutputCoverageSource $source): Collection => $source->coveredUrls($registry)),
                );
            });

        return $indexes;
    }

    private function urlFromOutput(DiscoveryOutputData|SitemapUrlItemData|string $output): ?string
    {
        $url = match (true) {
            $output instanceof DiscoveryOutputData => $output->url,
            $output instanceof SitemapUrlItemData => $output->loc,
            default => $output,
        };

        return $this->normalizeUrl($url);
    }

    /**
     * @return array{available: bool, urls: array<string, true>}
     */
    private function discoverSitemapUrlIndex(): array
    {
        $disk = config('capell.sitemap.disk', 'local');
        $directory = (string) config('capell.sitemap.directory', 'sitemaps');
        $storage = Storage::disk($disk);

        if (! $storage->exists($directory)) {
            return ['available' => true, 'urls' => []];
        }

        $publishedPaths = resolve(SitemapPublicationStore::class)->publishedXmlPaths();
        $xmlPaths = $publishedPaths !== []
            ? collect($publishedPaths)
            : collect($storage->files($directory))->filter(fn (string $file): bool => str_ends_with($file, '.xml'));

        $urls = $xmlPaths
            ->flatMap(function (string $file) use ($storage): Collection {
                $xml = $storage->get($file);

                return is_string($xml) ? $this->urlsFromSitemapXml($xml) : collect();
            });

        return $this->buildUrlIndex($urls);
    }

    /**
     * @return Collection<int, string>
     */
    private function urlsFromSitemapXml(string $xml): Collection
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $document instanceof SimpleXMLElement || $document->getName() !== 'urlset') {
            return collect();
        }

        $children = $document->children(self::SITEMAP_NAMESPACE);

        return collect($children->url)
            ->map(fn (SimpleXMLElement $url): ?string => $this->sitemapLocation($url))
            ->filter(fn (?string $url): bool => $url !== null)
            ->values();
    }

    private function sitemapLocation(SimpleXMLElement $url): ?string
    {
        $location = trim((string) $url->loc);

        return $location !== '' ? $location : null;
    }

    private function normalizeUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = isset($parts['path']) ? '/' . ltrim($parts['path'], '/') : '';
        $path = $path === '/' ? '' : rtrim($path, '/');

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $port . $path . $query;
    }
}
