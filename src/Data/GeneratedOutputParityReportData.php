<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Spatie\LaravelData\Data;

final class GeneratedOutputParityReportData extends Data
{
    /**
     * @param  list<GeneratedOutputParityRowData>  $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly int $totalUrls,
        public readonly int $missingOutputUrls,
    ) {}

    public function hasMissingOutputs(): bool
    {
        return $this->missingOutputUrls > 0;
    }
}
