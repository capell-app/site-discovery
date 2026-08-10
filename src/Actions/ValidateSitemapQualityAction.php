<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\DiscoveryFoundation\Data\PublicUrlRegistryEntryData;
use Capell\DiscoveryFoundation\Enums\PublicUrlContentType;
use Capell\DiscoveryFoundation\Enums\PublicUrlIndexability;
use Capell\SiteDiscovery\Data\SitemapQualityErrorData;
use Capell\SiteDiscovery\Data\SitemapQualityReportData;
use Capell\SiteDiscovery\Data\SitemapUrlItemData;
use Capell\SiteDiscovery\Enums\SitemapQualityError;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use SimpleXMLElement;

/**
 * @method static SitemapQualityReportData run(iterable<array-key, PublicUrlRegistryEntryData|SitemapUrlItemData|string> $entries = [], ?string $xml = null, bool $requireLastModified = false, ?CarbonInterface $staleBefore = null, ?callable $statusResolver = null)
 */
final class ValidateSitemapQualityAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  iterable<array-key, PublicUrlRegistryEntryData|SitemapUrlItemData|string>  $entries
     */
    public function handle(
        iterable $entries = [],
        ?string $xml = null,
        bool $requireLastModified = false,
        ?CarbonInterface $staleBefore = null,
        ?callable $statusResolver = null,
    ): SitemapQualityReportData {
        $errors = [];
        $seenUrls = [];
        $validUrls = [];

        foreach ($entries as $entry) {
            $url = $this->entryUrl($entry);

            if ($url === null) {
                continue;
            }

            $urlErrors = $this->validateEntry($entry, $url, $requireLastModified, $staleBefore, $statusResolver);
            $duplicateKey = $this->duplicateKey($url);

            if ($duplicateKey !== null && isset($seenUrls[$duplicateKey])) {
                $urlErrors[] = new SitemapQualityErrorData(
                    code: SitemapQualityError::DuplicateUrl,
                    url: $url,
                    message: 'Sitemap contains a duplicate URL.',
                    context: ['firstUrl' => $seenUrls[$duplicateKey]],
                );
            }

            if ($duplicateKey !== null) {
                $seenUrls[$duplicateKey] = $url;
            }

            if ($urlErrors === []) {
                $validUrls[] = $url;
            }

            array_push($errors, ...$urlErrors);
        }

        if ($xml !== null) {
            array_push($errors, ...$this->validateXml($xml));
        }

        return new SitemapQualityReportData(
            passed: $errors === [],
            errors: $errors,
            validUrls: array_values(array_unique($validUrls)),
        );
    }

    /**
     * @return list<SitemapQualityErrorData>
     */
    private function validateEntry(
        PublicUrlRegistryEntryData|SitemapUrlItemData|string $entry,
        string $url,
        bool $requireLastModified,
        ?CarbonInterface $staleBefore,
        ?callable $statusResolver,
    ): array {
        $errors = [];

        if ($this->isPrivateUrl($url)) {
            $errors[] = new SitemapQualityErrorData(
                code: SitemapQualityError::PrivateUrl,
                url: $url,
                message: 'Sitemap URL exposes a private, signed, draft, admin, or editor URL.',
            );
        }

        if ($entry instanceof PublicUrlRegistryEntryData) {
            if ($entry->indexability !== PublicUrlIndexability::Indexable || ! $entry->isSitemapEligible) {
                $errors[] = new SitemapQualityErrorData(
                    code: SitemapQualityError::InvalidStatus,
                    url: $url,
                    message: 'Registry URL is not indexable and sitemap eligible.',
                    context: [
                        'indexability' => $entry->indexability->value,
                        'isSitemapEligible' => $entry->isSitemapEligible,
                    ],
                );
            }

            if (! $this->isAllowedContentType($entry->contentType)) {
                $errors[] = new SitemapQualityErrorData(
                    code: SitemapQualityError::InvalidContentType,
                    url: $url,
                    message: 'Registry URL content type is not sitemap eligible.',
                    context: ['contentType' => $entry->contentType->value],
                );
            }
        }

        $lastModified = $this->entryLastModified($entry);

        if ($requireLastModified && ! $lastModified instanceof CarbonImmutable) {
            $errors[] = new SitemapQualityErrorData(
                code: SitemapQualityError::MissingLastModified,
                url: $url,
                message: 'Sitemap URL is missing a required last modified value.',
            );
        }

        if ($lastModified instanceof CarbonImmutable && $staleBefore instanceof CarbonInterface && $lastModified->lessThan($staleBefore)) {
            $errors[] = new SitemapQualityErrorData(
                code: SitemapQualityError::StaleLastModified,
                url: $url,
                message: 'Sitemap URL has a stale last modified value.',
                context: [
                    'lastModified' => $lastModified->toAtomString(),
                    'staleBefore' => CarbonImmutable::instance($staleBefore)->toAtomString(),
                ],
            );
        }

        if ($statusResolver !== null) {
            $statusCode = $statusResolver($url, $entry);

            if (is_int($statusCode)) {
                if ($statusCode >= 300 && $statusCode < 400) {
                    $errors[] = new SitemapQualityErrorData(
                        code: SitemapQualityError::RedirectStatus,
                        url: $url,
                        message: 'Sitemap URL resolves to a redirect instead of the canonical public URL.',
                        context: ['status' => $statusCode],
                    );
                } elseif ($statusCode !== 200) {
                    $errors[] = new SitemapQualityErrorData(
                        code: SitemapQualityError::UnexpectedStatus,
                        url: $url,
                        message: 'Sitemap URL does not resolve with an HTTP 200 status.',
                        context: ['status' => $statusCode],
                    );
                }
            }
        }

        return $errors;
    }

    private function entryUrl(PublicUrlRegistryEntryData|SitemapUrlItemData|string $entry): ?string
    {
        if ($entry instanceof PublicUrlRegistryEntryData) {
            return $entry->canonicalUrl;
        }

        if ($entry instanceof SitemapUrlItemData) {
            return $entry->loc;
        }

        $url = trim($entry);

        return $url !== '' ? $url : null;
    }

    private function entryLastModified(PublicUrlRegistryEntryData|SitemapUrlItemData|string $entry): ?CarbonImmutable
    {
        return match (true) {
            $entry instanceof PublicUrlRegistryEntryData => $entry->lastModified,
            $entry instanceof SitemapUrlItemData => $entry->lastmod,
            default => null,
        };
    }

    private function isAllowedContentType(PublicUrlContentType $contentType): bool
    {
        return in_array($contentType, [
            PublicUrlContentType::Page,
            PublicUrlContentType::Article,
            PublicUrlContentType::Taxonomy,
            PublicUrlContentType::Other,
        ], true);
    }

    private function isPrivateUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return true;
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return true;
        }

        $path = strtolower((string) ($parts['path'] ?? ''));
        $segments = collect(explode('/', trim($path, '/')))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->values();

        if ($segments->contains(fn (string $segment): bool => in_array($segment, ['admin', 'draft', 'editor', 'filament'], true))) {
            return true;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $privateQueryKeys = collect(array_keys($query))
            ->map(fn (int|string $key): string => strtolower((string) $key));

        return $privateQueryKeys->contains(
            fn (string $key): bool => in_array($key, ['signature', 'expires', 'signed', 'preview', 'draft', 'editor'], true),
        );
    }

    private function duplicateKey(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = isset($parts['path']) ? '/' . ltrim($parts['path'], '/') : '';
        $path = $path === '/' ? '' : rtrim($path, '/');

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $port . $path . $query;
    }

    /**
     * @return list<SitemapQualityErrorData>
     */
    private function validateXml(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $document instanceof SimpleXMLElement || $errors !== []) {
            return [
                new SitemapQualityErrorData(
                    code: SitemapQualityError::MalformedXml,
                    url: null,
                    message: 'Sitemap XML is malformed.',
                ),
            ];
        }

        if (collect(['urlset', 'sitemapindex'])->doesntContain($document->getName())) {
            return [
                new SitemapQualityErrorData(
                    code: SitemapQualityError::MalformedXml,
                    url: null,
                    message: 'Sitemap XML must use a urlset or sitemapindex root element.',
                    context: ['root' => $document->getName()],
                ),
            ];
        }

        return [];
    }
}
