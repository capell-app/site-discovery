<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\SiteDiscovery\Data\StagedSitemapDomainData;
use Capell\SiteDiscovery\Data\StagedSitemapSetData;
use Capell\SiteDiscovery\Exceptions\SitemapGeneratorException;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPublicationStore;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static StagedSitemapSetData run(StagedSitemapSetData $sitemapSet)
 */
final class PromoteStagedSitemapSetAction
{
    use AsFake;
    use AsObject;

    public function __construct(
        private readonly SitemapPublicationStore $publicationStore,
    ) {}

    public function handle(StagedSitemapSetData $sitemapSet): StagedSitemapSetData
    {
        if ($this->publicationStore->isCurrentGeneration($sitemapSet->siteKey, $sitemapSet->generationId)) {
            return $sitemapSet;
        }

        $storage = Storage::disk($this->disk());
        $publishedDirectory = $this->baseDirectory() . '/.sets/' . $sitemapSet->generationId;
        $publishedSet = new StagedSitemapSetData(
            siteKey: $sitemapSet->siteKey,
            generationId: $sitemapSet->generationId,
            directory: $publishedDirectory,
            domains: $sitemapSet->domains,
        );
        $promoted = false;

        try {
            ValidateStagedSitemapSetAction::run($sitemapSet);
            $this->copyCompleteSet($storage, $sitemapSet, $publishedSet);
            ValidateStagedSitemapSetAction::run($publishedSet);
            $this->publicationStore->promote($publishedSet);
            $promoted = true;

            return $publishedSet;
        } finally {
            $storage->deleteDirectory($sitemapSet->directory);

            if (! $promoted) {
                $storage->deleteDirectory($publishedDirectory);
            }
        }
    }

    private function copyCompleteSet(
        Filesystem $storage,
        StagedSitemapSetData $stagedSet,
        StagedSitemapSetData $publishedSet,
    ): void {
        foreach ($stagedSet->domains as $domain) {
            $this->copyDomainFiles($storage, $stagedSet, $publishedSet, $domain);
        }
    }

    private function copyDomainFiles(
        Filesystem $storage,
        StagedSitemapSetData $stagedSet,
        StagedSitemapSetData $publishedSet,
        StagedSitemapDomainData $domain,
    ): void {
        foreach ($domain->filenames as $filename) {
            $source = trim($stagedSet->directory, '/') . '/' . $filename;
            $destination = trim($publishedSet->directory, '/') . '/' . $filename;
            $contents = $storage->get($source);

            throw_unless(is_string($contents), SitemapGeneratorException::class, 'Unable to read staged sitemap XML during promotion.');
            throw_unless($storage->put($destination, $contents), SitemapGeneratorException::class, 'Unable to copy staged sitemap XML during promotion.');
        }
    }

    private function disk(): string
    {
        $disk = config('capell.sitemap.disk', 'local');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }

    private function baseDirectory(): string
    {
        $directory = config('capell.sitemap.directory', 'sitemaps');

        return is_string($directory) && trim($directory, '/') !== ''
            ? trim($directory, '/')
            : 'sitemaps';
    }
}
