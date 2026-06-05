<?php

declare(strict_types=1);

it('keeps package composer requirements aligned with shipped code boundaries', function (): void {
    $packagePath = dirname(__DIR__, 2);

    $composer = json_decode(
        (string) file_get_contents($packagePath . '/composer.json'),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );
    $manifest = json_decode(
        (string) file_get_contents($packagePath . '/capell.json'),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    $runtimeRequirements = array_keys($composer['require'] ?? []);
    $packageRequirements = array_values(array_filter(
        $runtimeRequirements,
        fn (string $requirement): bool => str_starts_with($requirement, 'capell-app/'),
    ));

    sort($packageRequirements);

    expect($composer['require'] ?? [])->not->toHaveKey('icamys/php-sitemap-generator')
        ->and($packageRequirements)->toBe($manifest['dependencies']['requires'])
        ->and($manifest['performance']['cacheSafety']['invalidationSources'] ?? null)->toBe([
            [
                'model' => 'Capell\\Core\\Models\\Page',
                'events' => ['saved', 'deleted'],
            ],
            [
                'model' => 'Capell\\Core\\Models\\Site',
                'events' => ['created'],
            ],
        ]);
});

it('declares committed marketplace assets for every required screenshot capture target', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = json_decode(
        (string) file_get_contents($packagePath . '/capell.json'),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );
    $screenshotContract = json_decode(
        (string) file_get_contents($packagePath . '/docs/screenshots.json'),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    throw_unless(is_array($manifest), RuntimeException::class, 'Expected Site Discovery manifest array.');
    throw_unless(is_array($screenshotContract), RuntimeException::class, 'Expected Site Discovery screenshot contract array.');

    $marketplaceScreenshots = $manifest['marketplace']['screenshots'] ?? [];
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

        $id = $contractEntry['id'] ?? null;
        $screenshotPath = $contractEntry['screenshotPath'] ?? null;

        throw_unless(is_string($id), RuntimeException::class, 'Required Site Discovery screenshot contract entries must have string ids.');
        throw_unless(is_string($screenshotPath), RuntimeException::class, 'Required Site Discovery screenshot contract entries must declare screenshot paths.');

        expect($screenshotPath)->toStartWith('packages/site-discovery/docs/screenshots/');

        $requiredMarketplaceAssetPaths[] = 'docs/assets/marketplace/' . $id . '.svg';
    }

    expect($marketplaceScreenshotPaths)->toBe([
        'docs/assets/marketplace/extension-card.jpg',
        ...$requiredMarketplaceAssetPaths,
    ]);
});
