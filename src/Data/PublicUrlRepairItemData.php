<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Capell\SiteDiscovery\Enums\GeneratedOutputParityStatus;
use Capell\SiteDiscovery\Enums\PublicUrlOutput;
use Capell\SiteDiscovery\Enums\PublicUrlRepairStatus;
use Spatie\LaravelData\Data;

final class PublicUrlRepairItemData extends Data
{
    /**
     * @param  list<PublicUrlOutputIssueData>  $issues
     */
    public function __construct(
        public readonly GeneratedOutputParityRowData $row,
        public readonly PublicUrlRepairStatus $status,
        public readonly array $issues = [],
    ) {}

    public function canonicalUrl(): string
    {
        return $this->row->canonicalUrl;
    }

    public function needsAttention(): bool
    {
        return $this->status === PublicUrlRepairStatus::NeedsAttention;
    }

    /**
     * @return list<PublicUrlOutputIssueData>
     */
    public function missingIssues(): array
    {
        return array_values(array_filter(
            $this->issues,
            fn (PublicUrlOutputIssueData $issue): bool => $issue->isMissing(),
        ));
    }

    /**
     * @return list<PublicUrlOutputIssueData>
     */
    public function unavailableIssues(): array
    {
        return array_values(array_filter(
            $this->issues,
            fn (PublicUrlOutputIssueData $issue): bool => ! $issue->isMissing(),
        ));
    }

    public function affects(PublicUrlOutput $output): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->output === $output) {
                return true;
            }
        }

        return false;
    }

    /**
     * The complete eligibility and five-output matrix, retained for row details.
     *
     * @return array<string, GeneratedOutputParityStatus>
     */
    public function matrix(): array
    {
        return [
            PublicUrlOutput::Sitemap->value => $this->row->sitemapStatus,
            PublicUrlOutput::AiDiscovery->value => $this->row->aiDiscoveryStatus,
            PublicUrlOutput::Search->value => $this->row->searchStatus,
            PublicUrlOutput::HtmlCache->value => $this->row->htmlCacheStatus,
            PublicUrlOutput::AgentDelivery->value => $this->row->agentDeliveryStatus,
        ];
    }
}
