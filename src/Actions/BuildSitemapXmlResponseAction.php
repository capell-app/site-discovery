<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Support\PublicRead\PublicReadProjectionHeaders;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPublicationStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @method static Response|StreamedResponse run(Request $request, ?string $sitemapPrefix = null)
 */
final class BuildSitemapXmlResponseAction
{
    use AsFake;
    use AsObject;

    public function handle(Request $request, ?string $sitemapPrefix = null): Response|StreamedResponse
    {
        $disk = $this->configString('capell.sitemap.disk', 'local');
        $filename = $this->filename($request, $sitemapPrefix);
        $domainKey = $this->domainKey($request, $sitemapPrefix);
        $publicationStore = resolve(SitemapPublicationStore::class);
        $filePath = $publicationStore->resolveFilePath($domainKey, $filename);
        $storage = Storage::disk($disk);

        if (! is_string($filePath)) {
            $this->regenerateWithdrawnSitemap($publicationStore, $domainKey);
        }

        abort_unless(is_string($filePath) && $storage->exists($filePath), 404);

        $contents = $storage->get($filePath);

        abort_unless(is_string($contents), 404);

        $lastModifiedTimestamp = $storage->lastModified($filePath);
        $lastModified = gmdate(
            'D, d M Y H:i:s \G\M\T',
            is_numeric($lastModifiedTimestamp) ? $lastModifiedTimestamp : now()->getTimestamp(),
        );
        // The validator includes the public-read rules, so a copy cached under older rules is never reused.
        $etagDigest = trim(PublicReadProjectionHeaders::etag((string) $contents), '"');
        $weakEtag = 'W/"' . $etagDigest . '"';

        if ($this->requestMatchesEtag($request, $weakEtag, $etagDigest)) {
            return response('', 304, $this->cacheHeaders($weakEtag, $lastModified));
        }

        $headers = [
            ...$this->cacheHeaders($weakEtag, $lastModified),
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $this->downloadFilename($request, $sitemapPrefix) . '"',
        ];

        if (strlen((string) $contents) > 1024 * 1024) {
            return response()->stream(static function () use ($contents): void {
                echo $contents;
            }, 200, $headers);
        }

        return response($contents, 200, $headers);
    }

    /**
     * A sitemap generated under older public-read rules is withdrawn rather
     * than served. Regeneration is normally requested when the rules change;
     * asking again here (deduplicated) restores a sitemap whose change
     * notification was missed.
     */
    private function regenerateWithdrawnSitemap(SitemapPublicationStore $publicationStore, string $domainKey): void
    {
        $siteKey = $publicationStore->siteKeyForDomain($domainKey);

        if ($siteKey === null || $publicationStore->isPubliclyReadableGeneration($siteKey)) {
            return;
        }

        $site = Site::query()->find($siteKey);

        if ($site instanceof Site) {
            RequestSiteSitemapRegenerationAction::run($site);
        }
    }

    private function filename(Request $request, ?string $sitemapPrefix): string
    {
        $domainKey = $this->domainKey($request, $sitemapPrefix);
        $chunkPage = $request->query('p');

        if ($chunkPage !== null) {
            abort_unless(ctype_digit((string) $chunkPage) && (int) $chunkPage > 0, 404);

            return $domainKey . '-p' . (int) $chunkPage . '.xml';
        }

        return $domainKey . '.xml';
    }

    private function domainKey(Request $request, ?string $sitemapPrefix): string
    {
        return new SiteDomain([
            'scheme' => $request->getScheme(),
            'domain' => $request->getHost(),
            'port' => $request->getPort(),
            'path' => $sitemapPrefix,
        ])->getDomainKey();
    }

    private function requestMatchesEtag(Request $request, string $weakEtag, string $etagDigest): bool
    {
        $ifNoneMatch = $request->headers->get('If-None-Match');

        if ($ifNoneMatch === null) {
            return false;
        }

        $strongEtag = '"' . $etagDigest . '"';
        $clientEtags = array_values(array_filter(
            array_map(
                trim(...),
                explode(',', $ifNoneMatch),
            ),
            static fn (string $value): bool => $value !== '',
        ));

        return in_array($weakEtag, $clientEtags, true)
            || in_array($strongEtag, $clientEtags, true)
            || in_array($etagDigest, $clientEtags, true)
            || in_array('*', $clientEtags, true);
    }

    private function downloadFilename(Request $request, ?string $sitemapPrefix): string
    {
        return $this->filename($request, $sitemapPrefix);
    }

    /**
     * @return array<string, string>
     */
    private function cacheHeaders(string $etag, string $lastModified): array
    {
        return [
            'Cache-Control' => PublicReadProjectionHeaders::CACHE_CONTROL,
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
        ];
    }

    private function configString(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
