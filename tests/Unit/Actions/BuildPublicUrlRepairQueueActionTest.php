<?php

declare(strict_types=1);

use Capell\DiscoveryFoundation\Enums\PublicUrlContentType;
use Capell\DiscoveryFoundation\Enums\PublicUrlIndexability;
use Capell\SiteDiscovery\Actions\BuildPublicUrlRepairQueueAction;
use Capell\SiteDiscovery\Data\GeneratedOutputParityReportData;
use Capell\SiteDiscovery\Data\GeneratedOutputParityRowData;
use Capell\SiteDiscovery\Data\PublicUrlOutputIssueData;
use Capell\SiteDiscovery\Enums\GeneratedOutputParityStatus;
use Capell\SiteDiscovery\Enums\PublicUrlOutput;
use Capell\SiteDiscovery\Enums\PublicUrlRepairStatus;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;

uses(SiteDiscoveryTestCase::class);

function repairQueueRow(
    string $canonicalUrl = 'https://example.com/page',
    GeneratedOutputParityStatus $sitemapStatus = GeneratedOutputParityStatus::Present,
    GeneratedOutputParityStatus $aiDiscoveryStatus = GeneratedOutputParityStatus::Present,
    GeneratedOutputParityStatus $searchStatus = GeneratedOutputParityStatus::Present,
    GeneratedOutputParityStatus $htmlCacheStatus = GeneratedOutputParityStatus::Present,
    GeneratedOutputParityStatus $agentDeliveryStatus = GeneratedOutputParityStatus::Present,
    PublicUrlIndexability $indexability = PublicUrlIndexability::Indexable,
    bool $isSitemapEligible = true,
    bool $isAiDiscoveryEligible = true,
    string $sourcePackage = 'capell-app/test',
    int|string $siteKey = 1,
    int|string $languageKey = 1,
): GeneratedOutputParityRowData {
    return new GeneratedOutputParityRowData(
        canonicalUrl: $canonicalUrl,
        sourcePackage: $sourcePackage,
        siteKey: $siteKey,
        languageKey: $languageKey,
        siteId: is_int($siteKey) ? $siteKey : null,
        languageId: is_int($languageKey) ? $languageKey : null,
        languageCode: 'en',
        routeName: null,
        lastModified: null,
        indexability: $indexability,
        contentType: PublicUrlContentType::Page,
        isSitemapEligible: $isSitemapEligible,
        isAiDiscoveryEligible: $isAiDiscoveryEligible,
        sitemapStatus: $sitemapStatus,
        aiDiscoveryStatus: $aiDiscoveryStatus,
        searchStatus: $searchStatus,
        htmlCacheStatus: $htmlCacheStatus,
        agentDeliveryStatus: $agentDeliveryStatus,
    );
}

function repairQueueReport(GeneratedOutputParityRowData ...$rows): GeneratedOutputParityReportData
{
    $missing = 0;

    foreach ($rows as $row) {
        if ($row->hasMissingOutput()) {
            $missing++;
        }
    }

    return new GeneratedOutputParityReportData(
        rows: array_values($rows),
        totalUrls: count($rows),
        missingOutputUrls: $missing,
    );
}

it('marks a URL missing from an eligible output as needing attention', function (): void {
    $queue = BuildPublicUrlRepairQueueAction::run(repairQueueReport(
        repairQueueRow(sitemapStatus: GeneratedOutputParityStatus::Missing),
    ));

    $item = $queue->items[0];

    expect($queue->needsAttentionUrls)->toBe(1)
        ->and($queue->unavailableUrls)->toBe(0)
        ->and($queue->healthyUrls)->toBe(0)
        ->and($queue->hasWork())->toBeTrue()
        ->and($item->status)->toBe(PublicUrlRepairStatus::NeedsAttention)
        ->and($item->missingIssues())->toHaveCount(1)
        ->and($item->missingIssues()[0]->output)->toBe(PublicUrlOutput::Sitemap)
        ->and($item->missingIssues()[0]->reason)->toBe(PublicUrlOutput::Sitemap->missingReason())
        ->and($item->missingIssues()[0]->responsibleArea)->toBe(PublicUrlOutput::Sitemap->responsibleArea())
        ->and($item->missingIssues()[0]->nextStep)->toBe(PublicUrlOutput::Sitemap->missingNextStep());
});

it('reads unknown coverage as unavailable rather than missing', function (): void {
    $queue = BuildPublicUrlRepairQueueAction::run(repairQueueReport(
        repairQueueRow(htmlCacheStatus: GeneratedOutputParityStatus::Unknown),
    ));

    $item = $queue->items[0];

    expect($queue->needsAttentionUrls)->toBe(0)
        ->and($queue->unavailableUrls)->toBe(1)
        ->and($queue->hasWork())->toBeFalse()
        ->and($item->status)->toBe(PublicUrlRepairStatus::Unavailable)
        ->and($item->missingIssues())->toBe([])
        ->and($item->unavailableIssues())->toHaveCount(1)
        ->and($item->unavailableIssues()[0]->status)->toBe(GeneratedOutputParityStatus::Unknown)
        ->and($item->unavailableIssues()[0]->nextStep)->toBe(PublicUrlOutput::HtmlCache->unavailableNextStep());
});

it('never turns ineligible or noindex URLs into repair work', function (): void {
    $queue = BuildPublicUrlRepairQueueAction::run(repairQueueReport(
        repairQueueRow(
            canonicalUrl: 'https://example.com/noindex',
            sitemapStatus: GeneratedOutputParityStatus::NotEligible,
            aiDiscoveryStatus: GeneratedOutputParityStatus::NotEligible,
            searchStatus: GeneratedOutputParityStatus::NotEligible,
            htmlCacheStatus: GeneratedOutputParityStatus::NotEligible,
            agentDeliveryStatus: GeneratedOutputParityStatus::NotEligible,
            indexability: PublicUrlIndexability::NoIndex,
            isSitemapEligible: false,
            isAiDiscoveryEligible: false,
        ),
    ));

    expect($queue->needsAttentionUrls)->toBe(0)
        ->and($queue->unavailableUrls)->toBe(0)
        ->and($queue->healthyUrls)->toBe(1)
        ->and($queue->items[0]->status)->toBe(PublicUrlRepairStatus::Healthy)
        ->and($queue->items[0]->issues)->toBe([]);
});

it('reports an issue for each of the five generated outputs', function (): void {
    $queue = BuildPublicUrlRepairQueueAction::run(repairQueueReport(
        repairQueueRow(
            sitemapStatus: GeneratedOutputParityStatus::Missing,
            aiDiscoveryStatus: GeneratedOutputParityStatus::Missing,
            searchStatus: GeneratedOutputParityStatus::Missing,
            htmlCacheStatus: GeneratedOutputParityStatus::Missing,
            agentDeliveryStatus: GeneratedOutputParityStatus::Missing,
        ),
    ));

    $outputs = array_map(
        fn (PublicUrlOutputIssueData $issue): PublicUrlOutput => $issue->output,
        $queue->items[0]->issues,
    );

    expect($outputs)->toBe([
        PublicUrlOutput::Sitemap,
        PublicUrlOutput::AiDiscovery,
        PublicUrlOutput::Search,
        PublicUrlOutput::HtmlCache,
        PublicUrlOutput::AgentDelivery,
    ])
        ->and($queue->items[0]->matrix())->toBe([
            'sitemap' => GeneratedOutputParityStatus::Missing,
            'ai_discovery' => GeneratedOutputParityStatus::Missing,
            'search' => GeneratedOutputParityStatus::Missing,
            'html_cache' => GeneratedOutputParityStatus::Missing,
            'agent_delivery' => GeneratedOutputParityStatus::Missing,
        ]);
});

it('prefers needs attention over unavailable when a URL has both', function (): void {
    $queue = BuildPublicUrlRepairQueueAction::run(repairQueueReport(
        repairQueueRow(
            sitemapStatus: GeneratedOutputParityStatus::Missing,
            agentDeliveryStatus: GeneratedOutputParityStatus::Unknown,
        ),
    ));

    $item = $queue->items[0];

    expect($item->status)->toBe(PublicUrlRepairStatus::NeedsAttention)
        ->and($item->issues)->toHaveCount(2)
        ->and($item->missingIssues())->toHaveCount(1)
        ->and($item->unavailableIssues())->toHaveCount(1)
        ->and($item->affects(PublicUrlOutput::Sitemap))->toBeTrue()
        ->and($item->affects(PublicUrlOutput::AgentDelivery))->toBeTrue()
        ->and($item->affects(PublicUrlOutput::Search))->toBeFalse();
});

it('reconciles queue counts with the parity report across sites and languages', function (): void {
    $queue = BuildPublicUrlRepairQueueAction::run(repairQueueReport(
        repairQueueRow(canonicalUrl: 'https://example.com/a', sitemapStatus: GeneratedOutputParityStatus::Missing, siteKey: 1, languageKey: 1),
        repairQueueRow(canonicalUrl: 'https://example.fr/a', searchStatus: GeneratedOutputParityStatus::Unknown, siteKey: 2, languageKey: 2),
        repairQueueRow(canonicalUrl: 'https://example.com/b', siteKey: 1, languageKey: 1),
    ));

    expect($queue->totalUrls)->toBe(3)
        ->and($queue->needsAttentionUrls + $queue->unavailableUrls + $queue->healthyUrls)->toBe($queue->totalUrls)
        ->and($queue->needsAttentionUrls)->toBe(1)
        ->and($queue->unavailableUrls)->toBe(1)
        ->and($queue->healthyUrls)->toBe(1);
});

it('gives every output a distinct responsible area and next step', function (): void {
    $areas = array_map(
        fn (PublicUrlOutput $output): string => $output->responsibleArea(),
        PublicUrlOutput::cases(),
    );
    $steps = array_map(
        fn (PublicUrlOutput $output): string => $output->missingNextStep(),
        PublicUrlOutput::cases(),
    );

    expect(array_unique($areas))->toHaveCount(count($areas))
        ->and(array_unique($steps))->toHaveCount(count($steps))
        ->and($steps)->not->toContain('');
});
