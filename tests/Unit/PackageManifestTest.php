<?php

declare(strict_types=1);

use Capell\Core\Models\Page;
use Capell\Core\Models\Site;

it('keeps package composer requirements aligned with shipped code boundaries', function (): void {
    $packagePath = dirname(__DIR__, 2);

    $composer = capell_json_file_array($packagePath . '/composer.json');
    $manifest = capell_json_file_array($packagePath . '/capell.json');

    $composerRequirements = data_get($composer, 'require', []);

    throw_unless(is_array($composerRequirements), RuntimeException::class, 'Site Discovery composer requirements must be an array.');

    $runtimeRequirements = array_keys($composerRequirements);
    $packageRequirements = array_values(array_filter(
        $runtimeRequirements,
        fn (string $requirement): bool => str_starts_with($requirement, 'capell-app/'),
    ));

    sort($packageRequirements);

    expect($composerRequirements)->not->toHaveKey('icamys/php-sitemap-generator')
        ->and($packageRequirements)->toBe(data_get($manifest, 'dependencies.requires'))
        ->and(data_get($manifest, 'performance.cacheSafety.invalidationSources'))->toBe([
            [
                'model' => Page::class,
                'events' => ['saved', 'deleted'],
            ],
            [
                'model' => Site::class,
                'events' => ['created'],
            ],
        ]);
});

it('declares committed marketplace assets for buyer-facing screenshot capture targets', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = capell_json_file_array($packagePath . '/capell.json');
    $screenshotContract = capell_json_file_array($packagePath . '/docs/screenshots.json');

    $marketplaceScreenshots = data_get($manifest, 'marketplace.screenshots', []);
    $contractEntries = $screenshotContract['entries'] ?? [];

    throw_unless(is_array($marketplaceScreenshots), RuntimeException::class, 'Site Discovery marketplace screenshots must be an array.');
    throw_unless(is_array($contractEntries), RuntimeException::class, 'Site Discovery screenshot contract entries must be an array.');

    $marketplaceScreenshotPaths = [];

    foreach ($marketplaceScreenshots as $marketplaceScreenshot) {
        throw_unless(is_array($marketplaceScreenshot), RuntimeException::class, 'Site Discovery marketplace screenshot entries must be arrays.');

        $path = $marketplaceScreenshot['path'] ?? null;
        $alt = $marketplaceScreenshot['alt'] ?? null;
        $caption = $marketplaceScreenshot['caption'] ?? null;

        throw_unless(is_string($path), RuntimeException::class, 'Site Discovery marketplace screenshot paths must be strings.');
        throw_unless(is_string($alt), RuntimeException::class, 'Site Discovery marketplace screenshot alt text must be strings.');
        throw_unless(is_string($caption), RuntimeException::class, 'Site Discovery marketplace screenshot captions must be strings.');

        $marketplaceScreenshotPaths[] = $path;

        expect(file_exists($packagePath . '/' . $path))->toBeTrue()
            ->and(strlen(trim($alt)))->toBeGreaterThanOrEqual(12)
            ->and(strlen(trim($caption)))->toBeGreaterThanOrEqual(12);
    }

    $requiredMarketplaceAssetPaths = [];

    foreach ($contractEntries as $contractEntry) {
        throw_unless(is_array($contractEntry), RuntimeException::class, 'Site Discovery screenshot contract entries must be arrays.');

        if (($contractEntry['required'] ?? false) !== true) {
            continue;
        }

        $screenshotPath = $contractEntry['screenshotPath'] ?? null;

        throw_unless(is_string($screenshotPath), RuntimeException::class, 'Required Site Discovery screenshot contract entries must declare screenshot paths.');

        $packageRelativePath = str_replace('packages/site-discovery/', '', $screenshotPath);

        expect($screenshotPath)->toStartWith('packages/site-discovery/docs/screenshots/');
        expect(file_exists($packagePath . '/' . $packageRelativePath))->toBeTrue();

        if (($contractEntry['id'] ?? null) === 'xml-sitemap-output') {
            continue;
        }

        $requiredMarketplaceAssetPaths[] = $packageRelativePath;
    }

    expect($marketplaceScreenshotPaths)->toBe([
        'docs/assets/marketplace/extension-card.jpg',
        ...$requiredMarketplaceAssetPaths,
    ]);
});
