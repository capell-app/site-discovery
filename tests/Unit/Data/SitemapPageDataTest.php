<?php

declare(strict_types=1);

use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Models\Translation;
use Capell\SiteDiscovery\Data\SitemapPageData;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

uses(SiteDiscoveryTestCase::class);

it('serializes last modified dates with atom formatting', function (): void {
    $lastModified = CarbonImmutable::parse('2026-03-15 09:30:00', 'UTC');

    $data = new SitemapPageData(
        label: 'Example page',
        url: 'https://example.test/example-page',
        lastModified: $lastModified,
    );

    expect($data->lastModified)
        ->toBeInstanceOf(CarbonImmutable::class)
        ->and($data->toArray()['lastModified'])
        ->toBe($lastModified->toAtomString());
});

it('builds sitemap DTOs from prehydrated relations without queries', function (): void {
    $siteDomain = new SiteDomain([
        'scheme' => 'https',
        'domain' => 'example.test',
    ]);
    $pageUrl = new PageUrl([
        'url' => '/example-page',
        'site_id' => 1,
        'language_id' => 1,
    ]);
    $pageUrl->exists = true;
    $pageUrl->setRelation('siteDomain', $siteDomain);

    $page = new Page;
    $page->forceFill([
        'id' => 1,
        'name' => 'Example page',
    ]);
    $page->exists = true;
    $page->setRelation('translation', new Translation(['title' => 'Example page']));
    $page->setRelation('blueprint', new Blueprint);
    $page->setRelation('pageUrl', $pageUrl);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $data = SitemapPageData::fromPage($page);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($data->label)->toBe('Example page')
        ->and($data->url)->toBe('https://example.test/example-page')
        ->and($queryCount)->toBe(0);
});
