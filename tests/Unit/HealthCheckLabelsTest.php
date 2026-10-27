<?php

declare(strict_types=1);

use Capell\SiteDiscovery\Health\SiteDiscoveryHealthCheck;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;

uses(SiteDiscoveryTestCase::class);

it('keeps diagnostic identities while naming checks by package and purpose', function (): void {
    $results = SiteDiscoveryHealthCheck::runDiagnostics();
    $existingIds = [
        'legacy.site.discovery.public.url.contributors',
        'legacy.site.discovery.xml.sitemap.output',
        'legacy.site.discovery.incremental.sitemap.regeneration',
        'legacy.site.discovery.html.sitemap.type',
    ];

    expect($results)->not->toBeEmpty();

    foreach ($results as $result) {
        expect($result->label)->toStartWith('Site Discovery: ')
            ->not->toMatch('/morph|contributor|registry|binding|runtime surfaces/i');
        expect($existingIds)->toContain($result->id);
    }
});
