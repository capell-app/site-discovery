<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Capell\SiteDiscovery\Enums\SitemapQualityError;
use Spatie\LaravelData\Data;

final class SitemapQualityReportData extends Data
{
    /**
     * @param  list<SitemapQualityErrorData>  $errors
     * @param  list<string>  $validUrls
     */
    public function __construct(
        public readonly bool $passed,
        public readonly array $errors = [],
        public readonly array $validUrls = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function hasError(SitemapQualityError $error): bool
    {
        return collect($this->errors)
            ->contains(fn (SitemapQualityErrorData $qualityError): bool => $qualityError->code === $error);
    }

    public function hasErrorsForUrl(string $url): bool
    {
        return collect($this->errors)
            ->contains(fn (SitemapQualityErrorData $qualityError): bool => $qualityError->url === $url);
    }
}
