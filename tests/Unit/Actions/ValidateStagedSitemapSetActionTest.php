<?php

declare(strict_types=1);

use Capell\SiteDiscovery\Actions\ValidateStagedSitemapSetAction;
use Capell\SiteDiscovery\Data\StagedSitemapDomainData;
use Capell\SiteDiscovery\Data\StagedSitemapSetData;
use Capell\SiteDiscovery\Exceptions\SitemapGeneratorException;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Support\Facades\Storage;

uses(SiteDiscoveryTestCase::class);

beforeEach(function (): void {
    config([
        'capell.sitemap.disk' => 'array',
        'capell.sitemap.directory' => 'sitemaps_validation_action',
    ]);

    Storage::fake('array');
});

it('directly validates complete XML and required publication metadata', function (): void {
    $directory = 'sitemaps_validation_action/.staging/generation-1';
    $filename = 'https-example-test.xml';
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        . '<url><loc>https://example.test/about</loc><lastmod>2026-07-30T10:00:00+00:00</lastmod></url>'
        . '</urlset>';

    Storage::disk('array')->put($directory . '/' . $filename, $xml);

    $stagedSet = new StagedSitemapSetData(
        siteKey: '42',
        generationId: 'generation-1',
        directory: $directory,
        domains: [
            new StagedSitemapDomainData(
                domainKey: 'https-example-test',
                languageId: 1,
                mainFilename: $filename,
                filenames: [$filename],
                urlCount: 1,
                urlState: ['https://example.test/about' => '2026-07-30T10:00:00+00:00'],
                etag: hash('sha256', $xml),
                publicChunkBaseUrl: 'https://example.test/sitemap-xml',
            ),
        ],
    );

    expect(ValidateStagedSitemapSetAction::run($stagedSet))->toBe($stagedSet);
});

it('rejects a sitemap index without required last-modified metadata', function (): void {
    $directory = 'sitemaps_validation_action/.staging/generation-2';
    $domainKey = 'https-example-test';
    $mainFilename = $domainKey . '.xml';
    $chunkFilename = $domainKey . '-p1.xml';
    $indexXml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        . '<sitemap><loc>https://example.test/sitemap-xml?p=1</loc></sitemap>'
        . '</sitemapindex>';
    $chunkXml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        . '<url><loc>https://example.test/about</loc></url>'
        . '</urlset>';

    Storage::disk('array')->put($directory . '/' . $mainFilename, $indexXml);
    Storage::disk('array')->put($directory . '/' . $chunkFilename, $chunkXml);

    $stagedSet = new StagedSitemapSetData(
        siteKey: '42',
        generationId: 'generation-2',
        directory: $directory,
        domains: [
            new StagedSitemapDomainData(
                domainKey: $domainKey,
                languageId: 1,
                mainFilename: $mainFilename,
                filenames: [$mainFilename, $chunkFilename],
                urlCount: 1,
                urlState: ['https://example.test/about' => ''],
                etag: hash('sha256', $indexXml),
                publicChunkBaseUrl: 'https://example.test/sitemap-xml',
            ),
        ],
    );

    expect(fn () => ValidateStagedSitemapSetAction::run($stagedSet))
        ->toThrow(SitemapGeneratorException::class, 'missing valid last-modified metadata');
});

it('rejects a matching chunk page on the wrong public host and path', function (): void {
    $directory = 'sitemaps_validation_action/.staging/generation-3';
    $domainKey = 'https-example-test';
    $mainFilename = $domainKey . '.xml';
    $firstChunkFilename = $domainKey . '-p1.xml';
    $secondChunkFilename = $domainKey . '-p2.xml';
    $lastModified = '2026-07-30T10:00:00+00:00';
    $indexXml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        . '<sitemap><loc>https://wrong.example/unrelated?p=1</loc><lastmod>' . $lastModified . '</lastmod></sitemap>'
        . '<sitemap><loc>https://example.test/sitemap-xml?p=2</loc><lastmod>' . $lastModified . '</lastmod></sitemap>'
        . '</sitemapindex>';
    $firstChunkXml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        . '<url><loc>https://example.test/about</loc></url>'
        . '</urlset>';
    $secondChunkXml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        . '<url><loc>https://example.test/contact</loc></url>'
        . '</urlset>';

    Storage::disk('array')->put($directory . '/' . $mainFilename, $indexXml);
    Storage::disk('array')->put($directory . '/' . $firstChunkFilename, $firstChunkXml);
    Storage::disk('array')->put($directory . '/' . $secondChunkFilename, $secondChunkXml);

    $stagedSet = new StagedSitemapSetData(
        siteKey: '42',
        generationId: 'generation-3',
        directory: $directory,
        domains: [
            new StagedSitemapDomainData(
                domainKey: $domainKey,
                languageId: 1,
                mainFilename: $mainFilename,
                filenames: [$mainFilename, $firstChunkFilename, $secondChunkFilename],
                urlCount: 2,
                urlState: [
                    'https://example.test/about' => '',
                    'https://example.test/contact' => '',
                ],
                etag: hash('sha256', $indexXml),
                publicChunkBaseUrl: 'https://example.test/sitemap-xml',
            ),
        ],
    );

    expect(fn () => ValidateStagedSitemapSetAction::run($stagedSet))
        ->toThrow(SitemapGeneratorException::class, 'do not match the expected public chunk URLs');
});
