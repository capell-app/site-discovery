<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Filament\Pages;

use BackedEnum;
use Capell\SiteDiscovery\Actions\BuildGeneratedOutputParityReportAction;
use Capell\SiteDiscovery\Data\GeneratedOutputParityReportData;
use Capell\SiteDiscovery\Data\GeneratedOutputParityRowData;
use Capell\SiteDiscovery\Enums\GeneratedOutputParityStatus;
use Capell\SiteDiscovery\Enums\PublicUrlIndexability;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Override;

final class PublicUrlRegistryPage extends Page
{
    public string $sourcePackageFilter = '';

    public string $siteFilter = '';

    public string $languageFilter = '';

    public string $indexabilityFilter = '';

    public string $sitemapEligibleFilter = '';

    public string $aiDiscoveryEligibleFilter = '';

    public string $missingOutputFilter = '';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Link;

    protected static ?string $slug = 'site-discovery/public-url-registry';

    protected static ?int $navigationSort = 16;

    protected string $view = 'capell-site-discovery::filament.pages.public-url-registry';

    private ?GeneratedOutputParityReportData $cachedReport = null;

    #[Override]
    public static function getNavigationLabel(): string
    {
        return (string) __('capell-site-discovery::generic.public_url_registry');
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return (string) __('capell-admin::navigation.group_monitoring');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('capell-site-discovery::generic.public_url_registry');
    }

    #[Override]
    public function getSubheading(): string
    {
        return __('capell-site-discovery::generic.public_url_registry_info');
    }

    public function report(): GeneratedOutputParityReportData
    {
        return $this->cachedReport ??= BuildGeneratedOutputParityReportAction::run();
    }

    /**
     * @return list<GeneratedOutputParityRowData>
     */
    public function rows(): array
    {
        return array_values($this->filteredRows()->values()->all());
    }

    /**
     * @return array<string, string>
     */
    public function sourcePackageOptions(): array
    {
        return $this->allRows()
            ->pluck('sourcePackage', 'sourcePackage')
            ->sortKeys()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function siteOptions(): array
    {
        return $this->allRows()
            ->mapWithKeys(fn (GeneratedOutputParityRowData $row): array => [
                (string) $row->siteKey => (string) $row->siteKey,
            ])
            ->sortKeys()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function languageOptions(): array
    {
        return $this->allRows()
            ->mapWithKeys(fn (GeneratedOutputParityRowData $row): array => [
                (string) $row->languageKey => $row->languageCode ?? (string) $row->languageKey,
            ])
            ->sortKeys()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function indexabilityOptions(): array
    {
        return collect(PublicUrlIndexability::cases())
            ->mapWithKeys(fn (PublicUrlIndexability $indexability): array => [
                $indexability->value => $indexability->getLabel(),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function binaryFilterOptions(): array
    {
        return [
            'yes' => (string) __('capell-site-discovery::generic.filter_yes'),
            'no' => (string) __('capell-site-discovery::generic.filter_no'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function missingOutputOptions(): array
    {
        return [
            'missing' => (string) __('capell-site-discovery::generic.missing_output_only'),
            'clean' => (string) __('capell-site-discovery::generic.without_missing_output'),
        ];
    }

    public function statusClass(GeneratedOutputParityStatus $status): string
    {
        return match ($status) {
            GeneratedOutputParityStatus::Present => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30',
            GeneratedOutputParityStatus::Missing => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30',
            GeneratedOutputParityStatus::NotEligible => 'bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10',
            GeneratedOutputParityStatus::Unknown => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30',
        };
    }

    public function errorLabel(string $error): string
    {
        return (string) __('capell-site-discovery::generic.generated_output_parity_error.' . $error);
    }

    /**
     * @return Collection<int, GeneratedOutputParityRowData>
     */
    private function filteredRows(): Collection
    {
        return $this->allRows()
            ->filter(fn (GeneratedOutputParityRowData $row): bool => $this->matchesSourcePackage($row))
            ->filter(fn (GeneratedOutputParityRowData $row): bool => $this->matchesSite($row))
            ->filter(fn (GeneratedOutputParityRowData $row): bool => $this->matchesLanguage($row))
            ->filter(fn (GeneratedOutputParityRowData $row): bool => $this->matchesIndexability($row))
            ->filter(fn (GeneratedOutputParityRowData $row): bool => $this->matchesBooleanFilter($this->sitemapEligibleFilter, $row->isSitemapEligible))
            ->filter(fn (GeneratedOutputParityRowData $row): bool => $this->matchesBooleanFilter($this->aiDiscoveryEligibleFilter, $row->isAiDiscoveryEligible))
            ->filter(fn (GeneratedOutputParityRowData $row): bool => $this->matchesMissingOutput($row));
    }

    /**
     * @return Collection<int, GeneratedOutputParityRowData>
     */
    private function allRows(): Collection
    {
        return collect($this->report()->rows);
    }

    private function matchesSourcePackage(GeneratedOutputParityRowData $row): bool
    {
        return $this->sourcePackageFilter === '' || $row->sourcePackage === $this->sourcePackageFilter;
    }

    private function matchesSite(GeneratedOutputParityRowData $row): bool
    {
        return $this->siteFilter === '' || (string) $row->siteKey === $this->siteFilter;
    }

    private function matchesLanguage(GeneratedOutputParityRowData $row): bool
    {
        return $this->languageFilter === '' || (string) $row->languageKey === $this->languageFilter;
    }

    private function matchesIndexability(GeneratedOutputParityRowData $row): bool
    {
        return $this->indexabilityFilter === '' || $row->indexability->value === $this->indexabilityFilter;
    }

    private function matchesBooleanFilter(string $filter, bool $value): bool
    {
        return match ($filter) {
            'yes' => $value,
            'no' => ! $value,
            default => true,
        };
    }

    private function matchesMissingOutput(GeneratedOutputParityRowData $row): bool
    {
        return match ($this->missingOutputFilter) {
            'missing' => $row->hasMissingOutput(),
            'clean' => ! $row->hasMissingOutput(),
            default => true,
        };
    }
}
