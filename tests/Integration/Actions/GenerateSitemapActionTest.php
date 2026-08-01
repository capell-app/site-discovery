<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\BuildSitemapXmlResponseAction;
use Capell\SiteDiscovery\Actions\GenerateSitemapAction;
use Capell\SiteDiscovery\Actions\PromoteStagedSitemapSetAction;
use Capell\SiteDiscovery\Data\StagedSitemapDomainData;
use Capell\SiteDiscovery\Enums\SitemapCacheKey;
use Capell\SiteDiscovery\Exceptions\SitemapGeneratorException;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPublicationStore;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Http\Request;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(SiteDiscoveryTestCase::class);

it('handles the sitemap generation', function (): void {
    $storage = Storage::fake(config('capell.sitemap.disk'));

    // Arrange
    $langauge = Language::factory()->create();
    $site = Site::factory()
        ->recycle($langauge)
        ->hasSiteDomain()
        ->create();

    Page::factory()
        ->count(5)
        ->site($site)
        ->withTranslations($site->languages)
        ->create();

    // Act
    $xml = GenerateSitemapAction::run($site);

    // Assert
    expect($site->siteDomains)->toHaveCount(1)
        ->and($xml)->toBeString()
        ->and($storage->exists(config('capell.sitemap.directory')))->toBeTrue();

    $dir = config('capell.sitemap.directory');

    $site->siteDomains->each(function (SiteDomain $domain) use ($dir, $storage): void {
        $filename = $domain->getDomainKey() . '.xml';
        $storage->assertExists($dir . ('/' . $filename));

        $publishedPath = resolve(SitemapPublicationStore::class)->resolveFilePath($domain->getDomainKey(), $filename);

        expect($publishedPath)->toBeString()
            ->and($publishedPath)->toContain('/.sets/');

        throw_unless(is_string($publishedPath), RuntimeException::class, 'Expected a published sitemap path.');

        $storage->assertExists($publishedPath);
    });

    $manifestDirectory = is_string($dir) && $dir !== '' ? $dir : 'sitemaps';
    $storage->assertExists($manifestDirectory . '/.current.json');
});

it('retains the currently served set when staged XML validation fails', function (): void {
    $storage = Storage::fake('local');
    $language = Language::factory()->create();
    $siteDomain = SiteDomain::factory()->state([
        'domain' => 'atomic.example.test',
        'language_id' => $language->getKey(),
        'scheme' => 'https',
        'path' => null,
    ])->create();
    $site = $siteDomain->site;

    Page::factory()->site($site)->withTranslations(collect([$language]))->create();
    GenerateSitemapAction::run($site);

    $publicationStore = resolve(SitemapPublicationStore::class);
    $filename = $siteDomain->getDomainKey() . '.xml';
    $oldPath = $publicationStore->resolveFilePath($siteDomain->getDomainKey(), $filename);

    throw_unless(is_string($oldPath), RuntimeException::class, 'Expected an existing published sitemap path.');

    $oldXml = $storage->get($oldPath);
    Page::factory()->site($site)->withTranslations(collect([$language]))->create();
    Cache::flush();

    $stagedSet = resolve(XmlSitemapGenerator::class)->stage($site);
    $stagedDomain = $stagedSet->domain($siteDomain->getDomainKey());

    throw_unless($stagedDomain instanceof StagedSitemapDomainData, RuntimeException::class, 'Expected staged domain metadata.');

    $storage->put($stagedSet->directory . '/' . $stagedDomain->mainFilename, '<urlset>');

    expect(fn () => PromoteStagedSitemapSetAction::run($stagedSet))
        ->toThrow(SitemapGeneratorException::class, 'Staged sitemap XML is malformed');

    $currentPath = $publicationStore->resolveFilePath($siteDomain->getDomainKey(), $filename);
    throw_unless(is_string($currentPath), RuntimeException::class, 'Expected the previous sitemap to remain published.');

    expect($currentPath)->toBe($oldPath)
        ->and($storage->get($currentPath))->toBe($oldXml)
        ->and($storage->exists($stagedSet->directory))->toBeFalse();
});

it('atomically promotes a complete set and keeps the previous set for recovery', function (): void {
    $storage = Storage::fake('local');
    $language = Language::factory()->create();
    $siteDomain = SiteDomain::factory()->state([
        'domain' => 'promotion.example.test',
        'language_id' => $language->getKey(),
        'scheme' => 'https',
        'path' => null,
    ])->create();
    $site = $siteDomain->site;
    $filename = $siteDomain->getDomainKey() . '.xml';

    Page::factory()->site($site)->withTranslations(collect([$language]))->create();
    GenerateSitemapAction::run($site);

    $publicationStore = resolve(SitemapPublicationStore::class);
    $oldPath = $publicationStore->resolveFilePath($siteDomain->getDomainKey(), $filename);
    $oldGenerationId = $publicationStore->currentGenerationId((string) $site->getKey());

    Page::factory()->site($site)->withTranslations(collect([$language]))->create();
    Cache::flush();

    $stagedSet = resolve(XmlSitemapGenerator::class)->stage($site);
    $publishedSet = PromoteStagedSitemapSetAction::run($stagedSet);
    $newPath = $publicationStore->resolveFilePath($siteDomain->getDomainKey(), $filename);

    throw_unless(is_string($oldPath) && is_string($newPath), RuntimeException::class, 'Expected both sitemap generations to remain stored.');

    expect($newPath)->not->toBe($oldPath)
        ->and($storage->exists($oldPath))->toBeTrue()
        ->and($storage->exists($newPath))->toBeTrue()
        ->and($oldGenerationId)->not->toBe($publishedSet->generationId)
        ->and($publicationStore->currentGenerationId((string) $site->getKey()))->toBe($publishedSet->generationId)
        ->and($publicationStore->rollbackGenerationId((string) $site->getKey()))->toBe($oldGenerationId)
        ->and(fn () => PromoteStagedSitemapSetAction::run($stagedSet))->not->toThrow(Throwable::class);

    Page::factory()->site($site)->withTranslations(collect([$language]))->create();
    Cache::flush();

    $thirdStagedSet = resolve(XmlSitemapGenerator::class)->stage($site);
    $thirdPublishedSet = PromoteStagedSitemapSetAction::run($thirdStagedSet);
    $thirdPath = $publicationStore->resolveFilePath($siteDomain->getDomainKey(), $filename);

    throw_unless(is_string($thirdPath), RuntimeException::class, 'Expected the third sitemap generation to be published.');

    expect($publicationStore->currentGenerationId((string) $site->getKey()))->toBe($thirdPublishedSet->generationId)
        ->and($publicationStore->rollbackGenerationId((string) $site->getKey()))->toBe($publishedSet->generationId)
        ->and($storage->exists($thirdPath))->toBeTrue()
        ->and($storage->exists($newPath))->toBeTrue()
        ->and($storage->exists($oldPath))->toBeFalse();
});

it('merges interleaved site promotions without losing either current set', function (): void {
    $storage = Storage::fake('local');
    $firstLanguage = Language::factory()->create();
    $secondLanguage = Language::factory()->create();
    $firstDomain = SiteDomain::factory()->state([
        'domain' => 'first.example.test',
        'language_id' => $firstLanguage->getKey(),
        'scheme' => 'https',
        'path' => null,
    ])->create();
    $secondDomain = SiteDomain::factory()->state([
        'domain' => 'second.example.test',
        'language_id' => $secondLanguage->getKey(),
        'scheme' => 'https',
        'path' => null,
    ])->create();

    Page::factory()->site($firstDomain->site)->withTranslations(collect([$firstLanguage]))->create();
    Page::factory()->site($secondDomain->site)->withTranslations(collect([$secondLanguage]))->create();

    $generator = resolve(XmlSitemapGenerator::class);
    $firstStagedSet = $generator->stage($firstDomain->site);
    $secondStagedSet = $generator->stage($secondDomain->site);

    PromoteStagedSitemapSetAction::run($firstStagedSet);
    PromoteStagedSitemapSetAction::run($secondStagedSet);

    $publicationStore = resolve(SitemapPublicationStore::class);
    $secondPublishedPath = $publicationStore->resolveFilePath($secondDomain->getDomainKey(), $secondDomain->getDomainKey() . '.xml');

    throw_unless(is_string($secondPublishedPath), RuntimeException::class, 'Expected the second site sitemap to be published.');

    expect($publicationStore->resolveFilePath($firstDomain->getDomainKey(), $firstDomain->getDomainKey() . '.xml'))->toBeString()
        ->and($publicationStore->resolveFilePath($secondDomain->getDomainKey(), $secondDomain->getDomainKey() . '.xml'))->toBeString();

    for ($generationIndex = 0; $generationIndex < 2; $generationIndex++) {
        Page::factory()->site($firstDomain->site)->withTranslations(collect([$firstLanguage]))->create();
        Cache::flush();
        PromoteStagedSitemapSetAction::run($generator->stage($firstDomain->site));
    }

    expect($storage->exists($secondPublishedPath))->toBeTrue();
});

it('keeps the published ETag recoverable after a failed replacement and refreshes it after success', function (): void {
    $storage = Storage::fake('local');
    $language = Language::factory()->create();
    $siteDomain = SiteDomain::factory()->state([
        'domain' => 'etag.example.test',
        'language_id' => $language->getKey(),
        'scheme' => 'https',
        'path' => null,
    ])->create();
    $site = $siteDomain->site;

    Page::factory()->site($site)->withTranslations(collect([$language]))->create();
    GenerateSitemapAction::run($site);

    $request = Request::create('https://etag.example.test/sitemap-xml', 'GET');
    $oldResponse = BuildSitemapXmlResponseAction::run($request);
    $oldEtag = $oldResponse->headers->get('ETag');

    Page::factory()->site($site)->withTranslations(collect([$language]))->create();
    Cache::flush();

    $stagedSet = resolve(XmlSitemapGenerator::class)->stage($site);
    $stagedDomain = $stagedSet->domain($siteDomain->getDomainKey());

    throw_unless($stagedDomain instanceof StagedSitemapDomainData, RuntimeException::class, 'Expected staged domain metadata.');

    $storage->put($stagedSet->directory . '/' . $stagedDomain->mainFilename, '<broken>');

    expect(fn () => PromoteStagedSitemapSetAction::run($stagedSet))
        ->toThrow(SitemapGeneratorException::class);

    $recoveredResponse = BuildSitemapXmlResponseAction::run($request);
    expect($recoveredResponse->headers->get('ETag'))->toBe($oldEtag);

    GenerateSitemapAction::run($site);

    $newResponse = BuildSitemapXmlResponseAction::run($request);
    $newEtag = $newResponse->headers->get('ETag');

    throw_unless(is_string($newEtag), RuntimeException::class, 'Expected the rebuilt sitemap to provide an ETag.');

    expect($newEtag)->not->toBe($oldEtag);

    $conditionalRequest = Request::create('https://etag.example.test/sitemap-xml', 'GET');
    $conditionalRequest->headers->set('If-None-Match', $newEtag);

    expect(BuildSitemapXmlResponseAction::run($conditionalRequest)->getStatusCode())->toBe(304);
});

it('exposes per-site queue middleware to prevent overlapping sitemap jobs', function (): void {
    $site = Site::factory()->create();

    // @phpstan-ignore-next-line arguments.count
    $middleware = (new GenerateSitemapAction)->getJobMiddleware($site);

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);
});

it('cleans the generating counter when a site sitemap generation lock is already held', function (): void {
    config(['capell.sitemap.lock_wait_seconds' => 0]);

    $site = Site::factory()->create();
    $lock = Cache::lock('capell-site-discovery:sitemap:' . $site->getKey(), 900);
    $lock->get();

    Cache::put(SitemapCacheKey::Generating->value, 1);

    try {
        expect(fn (): string => GenerateSitemapAction::run($site))
            ->toThrow(Exception::class, 'Sitemap generation is already running for this site.');

        expect(Cache::has(SitemapCacheKey::Generating->value))->toBeFalse();
    } finally {
        $lock->release();
    }
});

it('preserves the previous exception when sitemap generation fails', function (): void {
    $site = Site::factory()->create();
    $previous = new RuntimeException('Storage write failed.');

    $log = Log::spy();

    app()->instance(XmlSitemapGenerator::class, new class($previous) extends XmlSitemapGenerator
    {
        public function __construct(private readonly RuntimeException $exception) {}

        public function generate(Site $site): string
        {
            throw $this->exception;
        }
    });

    try {
        GenerateSitemapAction::run($site);
    } catch (Exception $exception) {
        expect($exception->getMessage())->toBe('Failed to generate sitemap')
            ->and($exception->getPrevious())->toBe($previous);

        $log->shouldHaveReceived('warning')
            ->once()
            ->with('Site Discovery sitemap generation failed.', Mockery::on(
                static fn (array $context): bool => ($context['exception'] ?? null) === $previous
                    && ($context['site_id'] ?? null) === $site->getKey(),
            ));

        return;
    }

    throw new RuntimeException('Expected sitemap generation to fail.');
});

it('retains the currently served set when generation fails before staging completes', function (): void {
    $storage = Storage::fake('local');
    $language = Language::factory()->create();
    $siteDomain = SiteDomain::factory()->state([
        'domain' => 'generation-failure.example.test',
        'language_id' => $language->getKey(),
        'scheme' => 'https',
        'path' => null,
    ])->create();
    $site = $siteDomain->site;
    $filename = $siteDomain->getDomainKey() . '.xml';

    Page::factory()->site($site)->withTranslations(collect([$language]))->create();
    GenerateSitemapAction::run($site);

    $publicationStore = resolve(SitemapPublicationStore::class);
    $oldPath = $publicationStore->resolveFilePath($siteDomain->getDomainKey(), $filename);

    throw_unless(is_string($oldPath), RuntimeException::class, 'Expected an existing published sitemap path.');

    $oldXml = $storage->get($oldPath);
    $failure = new RuntimeException('Failed before the staged set was complete.');

    app()->instance(XmlSitemapGenerator::class, new class($failure) extends XmlSitemapGenerator
    {
        public function __construct(private readonly RuntimeException $exception) {}

        public function generate(Site $site): string
        {
            throw $this->exception;
        }
    });

    expect(fn (): string => GenerateSitemapAction::run($site))
        ->toThrow(Exception::class, 'Failed to generate sitemap');

    $currentPath = $publicationStore->resolveFilePath($siteDomain->getDomainKey(), $filename);

    expect($currentPath)->toBe($oldPath)
        ->and($storage->get($oldPath))->toBe($oldXml);
});
