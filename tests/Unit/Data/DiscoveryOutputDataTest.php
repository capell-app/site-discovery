<?php

declare(strict_types=1);

use Capell\SiteDiscovery\Data\DiscoveryOutputData;

it('stores public discovery output attributes', function (): void {
    $data = new DiscoveryOutputData(
        key: 'llms-txt',
        url: 'https://example.com/llms.txt',
        contentType: 'text/plain; charset=UTF-8',
        label: 'llms.txt',
        description: 'Optional public AI discovery output.',
    );

    expect($data->key)->toBe('llms-txt')
        ->and($data->url)->toBe('https://example.com/llms.txt')
        ->and($data->contentType)->toBe('text/plain; charset=UTF-8')
        ->and($data->label)->toBe('llms.txt')
        ->and($data->description)->toBe('Optional public AI discovery output.');
});
