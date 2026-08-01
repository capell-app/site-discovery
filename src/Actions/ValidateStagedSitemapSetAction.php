<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\SiteDiscovery\Data\StagedSitemapDomainData;
use Capell\SiteDiscovery\Data\StagedSitemapSetData;
use Capell\SiteDiscovery\Exceptions\SitemapGeneratorException;
use DateTimeImmutable;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use SimpleXMLElement;

/**
 * @method static StagedSitemapSetData run(StagedSitemapSetData $sitemapSet)
 */
final class ValidateStagedSitemapSetAction
{
    use AsFake;
    use AsObject;

    private const string SITEMAP_NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    public function handle(StagedSitemapSetData $sitemapSet): StagedSitemapSetData
    {
        throw_if($sitemapSet->siteKey === '', SitemapGeneratorException::class, 'Staged sitemap set is missing its site key.');
        throw_if($sitemapSet->generationId === '', SitemapGeneratorException::class, 'Staged sitemap set is missing its generation ID.');
        throw_if($sitemapSet->domains === [], SitemapGeneratorException::class, 'Staged sitemap set contains no domains.');

        $storage = Storage::disk($this->disk());

        foreach ($sitemapSet->domains as $domain) {
            $this->validateDomain($storage, $sitemapSet, $domain);
        }

        return $sitemapSet;
    }

    private function validateDomain(
        Filesystem $storage,
        StagedSitemapSetData $sitemapSet,
        StagedSitemapDomainData $domain,
    ): void {
        throw_if($domain->domainKey === '', SitemapGeneratorException::class, 'Staged sitemap domain is missing its domain key.');
        throw_if($domain->languageId < 1, SitemapGeneratorException::class, 'Staged sitemap domain is missing its language ID.');
        throw_if($domain->mainFilename !== $domain->domainKey . '.xml', SitemapGeneratorException::class, 'Staged sitemap main filename does not match its domain.');
        throw_if($domain->filenames === [], SitemapGeneratorException::class, 'Staged sitemap domain contains no XML files.');
        throw_if($domain->publicChunkBaseUrl === '', SitemapGeneratorException::class, 'Staged sitemap domain is missing its public chunk base URL.');
        throw_unless(in_array($domain->mainFilename, $domain->filenames, true), SitemapGeneratorException::class, 'Staged sitemap set does not include its main XML file.');
        throw_unless(count($domain->filenames) === count(array_unique($domain->filenames)), SitemapGeneratorException::class, 'Staged sitemap set contains duplicate filenames.');
        throw_unless($domain->urlCount === count($domain->urlState), SitemapGeneratorException::class, 'Staged sitemap URL metadata is incomplete.');

        $discoveredUrls = [];
        $chunkFilenames = [];
        $mainDocument = null;

        foreach ($domain->filenames as $filename) {
            throw_unless($this->isSafeFilename($filename, $domain->domainKey), SitemapGeneratorException::class, 'Staged sitemap set contains an invalid filename.');

            $path = $this->path($sitemapSet->directory, $filename);
            throw_unless($storage->exists($path), SitemapGeneratorException::class, 'Staged sitemap XML file is missing: ' . $filename);

            $xml = $storage->get($path);
            throw_unless(is_string($xml) && $xml !== '', SitemapGeneratorException::class, 'Staged sitemap XML file is unreadable: ' . $filename);

            $qualityReport = ValidateSitemapQualityAction::run(xml: $xml);
            throw_unless($qualityReport->passed, SitemapGeneratorException::class, 'Staged sitemap XML is malformed: ' . $filename);

            $document = $this->parse($xml, $filename);

            if ($filename === $domain->mainFilename) {
                $mainDocument = $document;
                throw_unless(hash_equals($domain->etag, hash('sha256', $xml)), SitemapGeneratorException::class, 'Staged sitemap ETag metadata does not match its main XML file.');
            }

            if ($document->getName() === 'urlset') {
                array_push($discoveredUrls, ...$this->validateUrlset($document));
            }

            if ($filename !== $domain->mainFilename) {
                $chunkFilenames[] = $filename;
            }
        }

        throw_unless($mainDocument instanceof SimpleXMLElement, SitemapGeneratorException::class, 'Staged sitemap main XML file was not validated.');

        if ($mainDocument->getName() === 'sitemapindex') {
            $this->validateIndex(
                $mainDocument,
                $chunkFilenames,
                $domain->domainKey,
                $domain->publicChunkBaseUrl,
            );
        } else {
            throw_unless($chunkFilenames === [], SitemapGeneratorException::class, 'A single-file sitemap unexpectedly contains chunk files.');
        }

        sort($discoveredUrls);
        $expectedUrls = array_keys($domain->urlState);
        sort($expectedUrls);

        throw_unless(count($discoveredUrls) === $domain->urlCount, SitemapGeneratorException::class, 'Staged sitemap URL count does not match its metadata.');
        throw_unless($discoveredUrls === $expectedUrls, SitemapGeneratorException::class, 'Staged sitemap is missing one or more expected URLs.');
    }

    /**
     * @return list<string>
     */
    private function validateUrlset(SimpleXMLElement $document): array
    {
        $urls = [];

        foreach ($document->children(self::SITEMAP_NAMESPACE)->url as $urlNode) {
            $location = trim((string) $urlNode->loc);
            throw_if($location === '', SitemapGeneratorException::class, 'Staged sitemap URL is missing its required location.');

            $lastModified = trim((string) $urlNode->lastmod);
            if ($lastModified !== '') {
                throw_unless($this->isValidDate($lastModified), SitemapGeneratorException::class, 'Staged sitemap URL contains invalid last-modified metadata.');
            }

            $urls[] = $location;
        }

        return $urls;
    }

    /**
     * @param  list<string>  $chunkFilenames
     */
    private function validateIndex(
        SimpleXMLElement $document,
        array $chunkFilenames,
        string $domainKey,
        string $publicChunkBaseUrl,
    ): void {
        $expectedPageNumbers = $this->chunkPageNumbers($chunkFilenames, $domainKey);
        $expectedLocations = array_map(
            fn (int $pageNumber): string => $this->normalizeIndexLocation($publicChunkBaseUrl . '?p=' . $pageNumber),
            $expectedPageNumbers,
        );
        $entries = $document->children(self::SITEMAP_NAMESPACE)->sitemap;
        $actualLocations = [];

        throw_unless(count($entries) === count($chunkFilenames), SitemapGeneratorException::class, 'Staged sitemap index does not reference every generated chunk.');

        foreach ($entries as $entry) {
            $location = trim((string) $entry->loc);
            $lastModified = trim((string) $entry->lastmod);

            throw_if($location === '', SitemapGeneratorException::class, 'Staged sitemap index entry is missing its required location.');
            throw_unless($lastModified !== '' && $this->isValidDate($lastModified), SitemapGeneratorException::class, 'Staged sitemap index entry is missing valid last-modified metadata.');

            $actualLocations[] = $this->normalizeIndexLocation($location);
        }

        sort($actualLocations);
        sort($expectedLocations);

        throw_unless(
            $actualLocations === $expectedLocations,
            SitemapGeneratorException::class,
            'Staged sitemap index locations do not match the expected public chunk URLs.',
        );
    }

    /**
     * @param  list<string>  $chunkFilenames
     * @return list<int>
     */
    private function chunkPageNumbers(array $chunkFilenames, string $domainKey): array
    {
        $pageNumbers = [];
        $pattern = '/^' . preg_quote($domainKey, '/') . '-p([1-9][0-9]*)\.xml$/D';

        foreach ($chunkFilenames as $chunkFilename) {
            $matches = [];
            throw_unless(preg_match($pattern, $chunkFilename, $matches) === 1, SitemapGeneratorException::class, 'Staged sitemap chunk filename is invalid.');

            $pageNumber = $matches[1] ?? null;
            throw_unless(is_string($pageNumber), SitemapGeneratorException::class, 'Staged sitemap chunk filename is missing its page number.');

            $pageNumbers[] = (int) $pageNumber;
        }

        return $pageNumbers;
    }

    private function normalizeIndexLocation(string $location): string
    {
        $parts = parse_url($location);

        throw_unless(
            is_array($parts)
                && is_string($parts['scheme'] ?? null)
                && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
                && is_string($parts['host'] ?? null)
                && $parts['host'] !== ''
                && ! isset($parts['user'])
                && ! isset($parts['pass'])
                && ! isset($parts['fragment']),
            SitemapGeneratorException::class,
            'Staged sitemap index entry location must be an absolute public HTTP URL.',
        );

        $query = $parts['query'] ?? null;
        $matches = [];

        throw_unless(
            is_string($query) && preg_match('/^p=([1-9][0-9]*)$/D', $query, $matches) === 1,
            SitemapGeneratorException::class,
            'Staged sitemap index entry location must identify exactly one chunk page.',
        );

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;
        $portSuffix = is_int($port) && ! (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))
            ? ':' . $port
            : '';
        $path = $parts['path'] ?? '/';

        throw_unless(is_string($path), SitemapGeneratorException::class, 'Staged sitemap index entry location contains an invalid path.');

        $normalizedPath = '/' . ltrim((string) preg_replace('#/+#', '/', $path), '/');
        $normalizedPath = $normalizedPath !== '/' ? rtrim($normalizedPath, '/') : $normalizedPath;

        return $scheme . '://' . $host . $portSuffix . $normalizedPath . '?p=' . (int) $matches[1];
    }

    private function parse(string $xml, string $filename): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        throw_unless($document instanceof SimpleXMLElement, SitemapGeneratorException::class, 'Unable to parse staged sitemap XML: ' . $filename);

        return $document;
    }

    private function isSafeFilename(string $filename, string $domainKey): bool
    {
        return $filename === $domainKey . '.xml'
            || preg_match('/^' . preg_quote($domainKey, '/') . '-p[1-9][0-9]*\.xml$/', $filename) === 1;
    }

    private function isValidDate(string $value): bool
    {
        try {
            new DateTimeImmutable($value);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    private function path(string $directory, string $filename): string
    {
        return trim($directory, '/') . '/' . $filename;
    }

    private function disk(): string
    {
        $disk = config('capell.sitemap.disk', 'local');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }
}
