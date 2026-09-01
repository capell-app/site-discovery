<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Capell\SiteDiscovery\Enums\GeneratedOutputParityStatus;
use Capell\SiteDiscovery\Enums\PublicUrlOutput;
use Spatie\LaravelData\Data;

final class PublicUrlOutputIssueData extends Data
{
    public function __construct(
        public readonly PublicUrlOutput $output,
        public readonly GeneratedOutputParityStatus $status,
        public readonly string $reason,
        public readonly string $responsibleArea,
        public readonly string $nextStep,
    ) {}

    public static function forMissing(PublicUrlOutput $output): self
    {
        return new self(
            output: $output,
            status: GeneratedOutputParityStatus::Missing,
            reason: $output->missingReason(),
            responsibleArea: $output->responsibleArea(),
            nextStep: $output->missingNextStep(),
        );
    }

    public static function forUnavailable(PublicUrlOutput $output): self
    {
        return new self(
            output: $output,
            status: GeneratedOutputParityStatus::Unknown,
            reason: $output->unavailableReason(),
            responsibleArea: $output->responsibleArea(),
            nextStep: $output->unavailableNextStep(),
        );
    }

    public function isMissing(): bool
    {
        return $this->status === GeneratedOutputParityStatus::Missing;
    }
}
