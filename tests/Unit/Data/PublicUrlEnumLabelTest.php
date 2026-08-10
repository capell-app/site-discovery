<?php

declare(strict_types=1);

use Capell\DiscoveryFoundation\Enums\PublicUrlContentType;
use Capell\DiscoveryFoundation\Enums\PublicUrlIndexability;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;

uses(SiteDiscoveryTestCase::class);

it('provides translated labels for public URL indexability options', function (): void {
    expect(PublicUrlIndexability::Indexable->getLabel())->toBe('Indexable')
        ->and(PublicUrlIndexability::NoIndex->getLabel())->toBe('Noindex');
});

it('provides translated labels for public URL content types', function (): void {
    expect(PublicUrlContentType::Page->getLabel())->toBe('Page')
        ->and(PublicUrlContentType::Article->getLabel())->toBe('Article')
        ->and(PublicUrlContentType::Taxonomy->getLabel())->toBe('Taxonomy')
        ->and(PublicUrlContentType::Media->getLabel())->toBe('Media')
        ->and(PublicUrlContentType::Feed->getLabel())->toBe('Feed')
        ->and(PublicUrlContentType::Other->getLabel())->toBe('Other');
});
