<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\GenerateSitemapAction;
use Capell\SiteDiscovery\Enums\SitemapCacheKey;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
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
    });
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

    Log::spy();

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

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Site Discovery sitemap generation failed.', Mockery::on(
                static fn (array $context): bool => ($context['exception'] ?? null) === $previous
                    && ($context['site_id'] ?? null) === $site->getKey(),
            ));

        return;
    }

    throw new RuntimeException('Expected sitemap generation to fail.');
});
