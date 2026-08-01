<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\Loader;

use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Data\SiteMapData;
use Capell\SiteDiscovery\Enums\SitemapCacheKey;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPublicationStore;
use Capell\SiteDiscovery\Support\Sitemap\SitemapStateStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SitemapLoader
{
    /**
     * @return array<int, SiteMapData>
     */
    public function all(): array
    {
        $directory = $this->stringValue(config('capell.sitemap.directory'));
        $disk = $this->stringValue(config('capell.sitemap.disk'), 'local');

        $storage = Storage::disk($disk);

        /** @var list<array{name: string, url: string, total: int|null}> $payload */
        $payload = Cache::remember(
            SitemapCacheKey::Sitemaps->value,
            30,
            function () use ($directory, $disk, $storage): array {
                $sitemaps = [];

                $sites = Site::excludingPreview()->with('siteDomains')->get();
                $publicationStore = resolve(SitemapPublicationStore::class);
                $state = new SitemapStateStore(
                    disk: $disk,
                    directory: $directory,
                );

                foreach ($sites as $site) {
                    $sitemapPage = $site->getFirstPageByType('sitemap');

                    if ($sitemapPage === null) {
                        continue;
                    }

                    $site->siteDomains->each(function (SiteDomain $domain) use (&$sitemaps, $storage, $publicationStore, $state): void {
                        $filename = $domain->getDomainKey() . '.xml';
                        $publishedPath = $publicationStore->resolveFilePath($domain->getDomainKey(), $filename);

                        if (! is_string($publishedPath) || ! $storage->exists($publishedPath)) {
                            return;
                        }

                        $sitemaps[] = [
                            'name' => $domain->name,
                            'url' => $domain->full_url . '/sitemap-xml',
                            'total' => $publicationStore->urlCount($domain->getDomainKey())
                                ?? $state->urlCount($domain->getDomainKey())
                                ?? 0,
                        ];
                    });
                }

                return $sitemaps;
            },
        );

        return collect($payload)
            ->filter(fn (mixed $sitemap): bool => is_array($sitemap))
            ->map(fn (array $sitemap): SiteMapData => SiteMapData::from($sitemap))
            ->values()
            ->all();
    }

    private function stringValue(mixed $value, string $fallback = ''): string
    {
        return is_scalar($value) ? (string) $value : $fallback;
    }
}
