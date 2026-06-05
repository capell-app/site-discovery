<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @method static Response|StreamedResponse run(Request $request, ?string $sitemapPrefix = null)
 */
final class BuildSitemapXmlResponseAction
{
    use AsAction;

    public function handle(Request $request, ?string $sitemapPrefix = null): Response|StreamedResponse
    {
        $disk = $this->configString('capell.sitemap.disk', 'local');
        $directory = trim($this->configString('capell.sitemap.directory', 'sitemaps'), '/');
        $filePath = $directory . '/' . $this->filename($request, $sitemapPrefix);
        $storage = Storage::disk($disk);

        abort_unless($storage->exists($filePath), 404);

        $contents = $storage->get($filePath);

        abort_unless(is_string($contents), 404);

        $lastModifiedTimestamp = $storage->lastModified($filePath);
        $lastModified = gmdate(
            'D, d M Y H:i:s \G\M\T',
            is_numeric($lastModifiedTimestamp) ? $lastModifiedTimestamp : now()->getTimestamp(),
        );
        $etagDigest = hash('sha256', (string) $contents);
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
        $segments = [
            $request->getScheme(),
            str_replace('.', '-', $request->getHost()),
        ];

        $prefix = trim((string) $sitemapPrefix, '/');

        if ($prefix !== '') {
            $segments[] = str_replace('/', '.', $prefix);
        }

        return implode('-', $segments);
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
            'Cache-Control' => 'public, max-age=86400',
            'Expires' => now()->addDay()->toRfc7231String(),
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
