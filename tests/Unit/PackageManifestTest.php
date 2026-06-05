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
        ->and($packageRequirements)->toBe($manifest['dependencies']['requires']);
});
