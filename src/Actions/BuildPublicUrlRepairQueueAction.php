<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\SiteDiscovery\Data\GeneratedOutputParityReportData;
use Capell\SiteDiscovery\Data\GeneratedOutputParityRowData;
use Capell\SiteDiscovery\Data\PublicUrlOutputIssueData;
use Capell\SiteDiscovery\Data\PublicUrlRepairItemData;
use Capell\SiteDiscovery\Data\PublicUrlRepairQueueData;
use Capell\SiteDiscovery\Enums\GeneratedOutputParityStatus;
use Capell\SiteDiscovery\Enums\PublicUrlOutput;
use Capell\SiteDiscovery\Enums\PublicUrlRepairStatus;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Projects the generated-output parity report into an operator repair queue.
 *
 * Missing coverage is repair work. Unknown coverage means no contributor can
 * report on that output, so it reads as unavailable rather than missing.
 * Ineligible and noindex URLs never become repair work.
 *
 * @method static PublicUrlRepairQueueData run(?GeneratedOutputParityReportData $report = null)
 */
final class BuildPublicUrlRepairQueueAction
{
    use AsFake;
    use AsObject;

    public function handle(?GeneratedOutputParityReportData $report = null): PublicUrlRepairQueueData
    {
        $report ??= BuildGeneratedOutputParityReportAction::run();

        $items = array_map(
            fn (GeneratedOutputParityRowData $row): PublicUrlRepairItemData => $this->item($row),
            array_values($report->rows),
        );

        $needsAttention = 0;
        $unavailable = 0;
        $healthy = 0;

        foreach ($items as $item) {
            match ($item->status) {
                PublicUrlRepairStatus::NeedsAttention => $needsAttention++,
                PublicUrlRepairStatus::Unavailable => $unavailable++,
                PublicUrlRepairStatus::Healthy => $healthy++,
            };
        }

        return new PublicUrlRepairQueueData(
            items: $items,
            totalUrls: count($items),
            needsAttentionUrls: $needsAttention,
            unavailableUrls: $unavailable,
            healthyUrls: $healthy,
        );
    }

    private function item(GeneratedOutputParityRowData $row): PublicUrlRepairItemData
    {
        $issues = [];

        foreach ($this->statuses($row) as [$output, $status]) {
            $issue = match ($status) {
                GeneratedOutputParityStatus::Missing => PublicUrlOutputIssueData::forMissing($output),
                GeneratedOutputParityStatus::Unknown => PublicUrlOutputIssueData::forUnavailable($output),
                default => null,
            };

            if ($issue instanceof PublicUrlOutputIssueData) {
                $issues[] = $issue;
            }
        }

        return new PublicUrlRepairItemData(
            row: $row,
            status: $this->status($issues),
            issues: $issues,
        );
    }

    /**
     * @return list<array{0: PublicUrlOutput, 1: GeneratedOutputParityStatus}>
     */
    private function statuses(GeneratedOutputParityRowData $row): array
    {
        return [
            [PublicUrlOutput::Sitemap, $row->sitemapStatus],
            [PublicUrlOutput::AiDiscovery, $row->aiDiscoveryStatus],
            [PublicUrlOutput::Search, $row->searchStatus],
            [PublicUrlOutput::HtmlCache, $row->htmlCacheStatus],
            [PublicUrlOutput::AgentDelivery, $row->agentDeliveryStatus],
        ];
    }

    /**
     * @param  list<PublicUrlOutputIssueData>  $issues
     */
    private function status(array $issues): PublicUrlRepairStatus
    {
        foreach ($issues as $issue) {
            if ($issue->isMissing()) {
                return PublicUrlRepairStatus::NeedsAttention;
            }
        }

        return $issues === [] ? PublicUrlRepairStatus::Healthy : PublicUrlRepairStatus::Unavailable;
    }
}
