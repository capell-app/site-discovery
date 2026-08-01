<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\Sitemap;

use Capell\SiteDiscovery\Data\StagedSitemapDomainData;
use Capell\SiteDiscovery\Data\StagedSitemapSetData;
use Capell\SiteDiscovery\Exceptions\SitemapGeneratorException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Throwable;

final class SitemapPublicationStore
{
    private const int MANIFEST_VERSION = 1;

    public function promote(StagedSitemapSetData $sitemapSet): void
    {
        Cache::lock($this->lockKey(), 60)->block(10, function () use ($sitemapSet): void {
            $manifest = $this->manifest();
            $sites = $this->sites($manifest);
            $currentSite = $sites[$sitemapSet->siteKey] ?? null;
            $currentGenerationId = is_array($currentSite)
                ? $this->generationId($currentSite['generation_id'] ?? null)
                : null;
            $existingRollbackGenerationId = is_array($currentSite)
                ? $this->generationId($currentSite['rollback_generation_id'] ?? null)
                : null;
            $rollbackGenerationId = $currentGenerationId !== null
                && $currentGenerationId !== $sitemapSet->generationId
                && $this->generationDirectoryExists($currentGenerationId)
                    ? $currentGenerationId
                    : $existingRollbackGenerationId;

            $sites[$sitemapSet->siteKey] = [
                'generation_id' => $sitemapSet->generationId,
                'rollback_generation_id' => $rollbackGenerationId,
                'promoted_at' => now()->toAtomString(),
                'domains' => collect($sitemapSet->domains)
                    ->mapWithKeys(fn (StagedSitemapDomainData $domain): array => [
                        $domain->domainKey => [
                            'directory' => trim($sitemapSet->directory, '/'),
                            'main_filename' => $domain->mainFilename,
                            'filenames' => $domain->filenames,
                            'language_id' => $domain->languageId,
                            'url_count' => $domain->urlCount,
                            'url_state' => $domain->urlState,
                            'etag' => $domain->etag,
                            'public_chunk_base_url' => $domain->publicChunkBaseUrl,
                        ],
                    ])
                    ->all(),
            ];

            $this->writeManifest([
                'version' => self::MANIFEST_VERSION,
                'sites' => $sites,
            ]);

            $this->pruneSiteGenerationDirectories(
                $sitemapSet->siteKey,
                array_values(array_filter([
                    $sitemapSet->generationId,
                    $rollbackGenerationId,
                ], is_string(...))),
            );
        });
    }

    public function isCurrentGeneration(string $siteKey, string $generationId): bool
    {
        $site = $this->site($siteKey);

        return ($site['generation_id'] ?? null) === $generationId;
    }

    public function currentGenerationId(string $siteKey): ?string
    {
        $generationId = $this->site($siteKey)['generation_id'] ?? null;

        return $this->generationId($generationId);
    }

    public function rollbackGenerationId(string $siteKey): ?string
    {
        $generationId = $this->site($siteKey)['rollback_generation_id'] ?? null;

        return $this->generationId($generationId);
    }

    public function resolveFilePath(string $domainKey, string $filename): ?string
    {
        $domain = $this->domain($domainKey);

        if ($domain !== null) {
            $filenames = $this->stringList($domain['filenames'] ?? null);
            $directory = $domain['directory'] ?? null;

            if (is_string($directory) && $directory !== '' && in_array($filename, $filenames, true)) {
                return trim($directory, '/') . '/' . $filename;
            }

            return null;
        }

        $legacyPath = $this->directory() . '/' . $filename;

        return $this->storage()->exists($legacyPath) ? $legacyPath : null;
    }

    public function hasDomain(string $domainKey): bool
    {
        return $this->domain($domainKey) !== null;
    }

    /**
     * @return list<string>
     */
    public function publishedXmlPaths(): array
    {
        $paths = [];

        foreach ($this->sites($this->manifest()) as $site) {
            if (! is_array($site)) {
                continue;
            }

            $domains = $site['domains'] ?? null;
            if (! is_array($domains)) {
                continue;
            }

            foreach ($domains as $domain) {
                if (! is_array($domain) || ! is_string($domain['directory'] ?? null)) {
                    continue;
                }

                foreach ($this->stringList($domain['filenames'] ?? null) as $filename) {
                    $paths[] = trim($domain['directory'], '/') . '/' . $filename;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return array<string, string>
     */
    public function urlState(string $domainKey): array
    {
        $domain = $this->domain($domainKey);
        $state = $domain['url_state'] ?? null;

        if (! is_array($state)) {
            return [];
        }

        $normalized = [];

        foreach ($state as $url => $lastModified) {
            if (is_string($url) && is_string($lastModified)) {
                $normalized[$url] = $lastModified;
            }
        }

        return $normalized;
    }

    public function urlCount(string $domainKey): ?int
    {
        $domain = $this->domain($domainKey);
        $count = $domain['url_count'] ?? null;

        return is_numeric($count) ? max(0, (int) $count) : null;
    }

    /**
     * @return list<string>
     */
    public function filenames(string $domainKey): array
    {
        $domain = $this->domain($domainKey);

        return $this->stringList($domain['filenames'] ?? null);
    }

    public function forgetSite(string $siteKey): void
    {
        Cache::lock($this->lockKey(), 60)->block(10, function () use ($siteKey): void {
            $manifest = $this->manifest();
            $sites = $this->sites($manifest);
            unset($sites[$siteKey]);

            $this->writeManifest([
                'version' => self::MANIFEST_VERSION,
                'sites' => $sites,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $storage = $this->storage();

        if (! $storage->exists($this->manifestPath())) {
            return [];
        }

        $contents = $storage->get($this->manifestPath());

        if (! is_string($contents) || $contents === '') {
            return [];
        }

        try {
            $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($manifest) && ($manifest['version'] ?? null) === self::MANIFEST_VERSION
            ? $manifest
            : [];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeManifest(array $manifest): void
    {
        $storage = $this->storage();
        $contents = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $temporaryPath = $this->manifestPath() . '.' . bin2hex(random_bytes(8)) . '.tmp';

        try {
            throw_unless($storage->put($temporaryPath, $contents), SitemapGeneratorException::class, 'Unable to write the staged sitemap publication manifest.');

            $written = $storage->get($temporaryPath);
            throw_unless(is_string($written) && hash_equals(hash('sha256', $contents), hash('sha256', $written)), SitemapGeneratorException::class, 'Staged sitemap publication manifest could not be verified.');

            if ($storage->getAdapter() instanceof LocalFilesystemAdapter) {
                $temporaryAbsolutePath = $storage->path($temporaryPath);
                $manifestAbsolutePath = $storage->path($this->manifestPath());

                throw_unless(rename($temporaryAbsolutePath, $manifestAbsolutePath), SitemapGeneratorException::class, 'Unable to atomically promote the sitemap publication manifest.');
            } else {
                throw_unless($storage->put($this->manifestPath(), $contents), SitemapGeneratorException::class, 'Unable to promote the sitemap publication manifest.');
            }
        } finally {
            try {
                $storage->delete($temporaryPath);
            } catch (Throwable) {
                // A stale temporary manifest is never served.
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function site(string $siteKey): ?array
    {
        $site = $this->sites($this->manifest())[$siteKey] ?? null;

        return is_array($site) ? $this->stringKeyedArray($site) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function domain(string $domainKey): ?array
    {
        foreach ($this->sites($this->manifest()) as $site) {
            if (! is_array($site) || ! is_array($site['domains'] ?? null)) {
                continue;
            }

            $domain = $site['domains'][$domainKey] ?? null;

            if (is_array($domain)) {
                return $this->stringKeyedArray($domain);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function sites(array $manifest): array
    {
        $sites = $manifest['sites'] ?? null;

        return is_array($sites) ? $this->stringKeyedArray($sites) : [];
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $value): array
    {
        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[is_int($key) ? (string) $key : $key] = $item;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    private function generationId(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function generationDirectoryExists(string $generationId): bool
    {
        return $this->storage()->exists($this->setsDirectory() . '/' . $generationId);
    }

    /**
     * @param  list<string>  $protectedGenerationIds
     */
    private function pruneSiteGenerationDirectories(string $siteKey, array $protectedGenerationIds): void
    {
        $storage = $this->storage();

        foreach ($storage->directories($this->setsDirectory()) as $generationDirectory) {
            $generationId = basename($generationDirectory);

            if (! $this->belongsToSite($generationId, $siteKey)
                || in_array($generationId, $protectedGenerationIds, true)) {
                continue;
            }

            try {
                if (! $storage->deleteDirectory($generationDirectory)) {
                    Log::notice('Site Discovery could not prune an obsolete sitemap generation.', [
                        'generation_id' => $generationId,
                        'site_key' => $siteKey,
                    ]);
                }
            } catch (Throwable $throwable) {
                Log::notice('Site Discovery could not prune an obsolete sitemap generation.', [
                    'exception' => $throwable,
                    'generation_id' => $generationId,
                    'site_key' => $siteKey,
                ]);
            }
        }
    }

    private function belongsToSite(string $generationId, string $siteKey): bool
    {
        return preg_match(
            '/^' . preg_quote($siteKey, '/') . '-[a-f0-9]{32}$/D',
            $generationId,
        ) === 1;
    }

    private function storage(): Filesystem
    {
        return Storage::disk($this->disk());
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

    private function manifestPath(): string
    {
        return $this->directory() . '/.current.json';
    }

    private function setsDirectory(): string
    {
        return $this->directory() . '/.sets';
    }

    private function lockKey(): string
    {
        return 'capell-site-discovery:sitemap-publication:' . hash('sha256', $this->disk() . '|' . $this->directory());
    }
}
