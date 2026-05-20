<?php

declare(strict_types=1);

use Capell\SiteDiscovery\Data\DiscoverableUrlData;

it('stores discoverable URL attributes for sitemap and discovery consumers', function (): void {
    $data = new DiscoverableUrlData(
        loc: 'https://example.com/about',
        changeFrequency: 'weekly',
        priority: '0.7',
    );

    expect($data->loc)->toBe('https://example.com/about')
        ->and($data->changeFrequency)->toBe('weekly')
        ->and($data->priority)->toBe('0.7');
});
