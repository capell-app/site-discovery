<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Filament\Pages;

use BackedEnum;
use Capell\DiscoveryFoundation\Enums\PublicUrlIndexability;
use Capell\SiteDiscovery\Actions\BuildPublicUrlRepairQueueAction;
use Capell\SiteDiscovery\Data\GeneratedOutputParityRowData;
use Capell\SiteDiscovery\Data\PublicUrlRepairItemData;
use Capell\SiteDiscovery\Data\PublicUrlRepairQueueData;
use Capell\SiteDiscovery\Enums\GeneratedOutputParityStatus;
use Capell\SiteDiscovery\Enums\PublicUrlOutput;
use Capell\SiteDiscovery\Enums\PublicUrlRepairStatus;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Override;

final class PublicUrlRegistryPage extends Page
{
    public const string VIEW_NEEDS_ATTENTION = 'needs_attention';

    public const string VIEW_UNAVAILABLE = 'unavailable';

    public const string VIEW_ALL = 'all';

    /**
     * Default to the repair queue rather than the full parity matrix.
     */
    public string $queueView = self::VIEW_NEEDS_ATTENTION;

    public string $outputFilter = '';

    public string $siteFilter = '';

    public bool $showAdvancedFilters = false;

    public string $sourcePackageFilter = '';

    public string $languageFilter = '';

    public string $indexabilityFilter = '';

    public string $sitemapEligibleFilter = '';

    public string $aiDiscoveryEligibleFilter = '';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Link;

    protected static ?string $slug = 'site-discovery/public-url-registry';

    protected static ?int $navigationSort = 16;

    protected string $view = 'capell-site-discovery::filament.pages.public-url-registry';

    private ?PublicUrlRepairQueueData $cachedQueue = null;

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

    public function queue(): PublicUrlRepairQueueData
    {
        return $this->cachedQueue ??= BuildPublicUrlRepairQueueAction::run();
    }

    /**
     * @return list<PublicUrlRepairItemData>
     */
    public function items(): array
    {
        return array_values($this->filteredItems()->values()->all());
    }

    public function toggleAdvancedFilters(): void
    {
        $this->showAdvancedFilters = ! $this->showAdvancedFilters;
    }

    public function clearFilters(): void
    {
        $this->outputFilter = '';
        $this->siteFilter = '';
        $this->sourcePackageFilter = '';
        $this->languageFilter = '';
        $this->indexabilityFilter = '';
        $this->sitemapEligibleFilter = '';
        $this->aiDiscoveryEligibleFilter = '';
    }

    public function hasActiveFilters(): bool
    {
        return $this->outputFilter !== ''
            || $this->siteFilter !== ''
            || $this->sourcePackageFilter !== ''
            || $this->languageFilter !== ''
            || $this->indexabilityFilter !== ''
            || $this->sitemapEligibleFilter !== ''
            || $this->aiDiscoveryEligibleFilter !== '';
    }

    /**
     * @return array<string, string>
     */
    public function viewOptions(): array
    {
        return [
            self::VIEW_NEEDS_ATTENTION => (string) __('capell-site-discovery::generic.view_needs_attention'),
            self::VIEW_UNAVAILABLE => (string) __('capell-site-discovery::generic.view_unavailable'),
            self::VIEW_ALL => (string) __('capell-site-discovery::generic.view_all_urls'),
        ];
    }

    public function viewCount(string $view): int
    {
        $queue = $this->queue();

        return match ($view) {
            self::VIEW_NEEDS_ATTENTION => $queue->needsAttentionUrls,
            self::VIEW_UNAVAILABLE => $queue->unavailableUrls,
            default => $queue->totalUrls,
        };
    }

    /**
     * @return array<string, string>
     */
    public function outputOptions(): array
    {
        return collect(PublicUrlOutput::cases())
            ->mapWithKeys(fn (PublicUrlOutput $output): array => [
                $output->value => $output->getLabel(),
            ])
            ->all();
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

    public function repairStatusClass(PublicUrlRepairStatus $status): string
    {
        return match ($status) {
            PublicUrlRepairStatus::NeedsAttention => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30',
            PublicUrlRepairStatus::Unavailable => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30',
            PublicUrlRepairStatus::Healthy => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30',
        };
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

    public function outputLabel(string $output): string
    {
        return PublicUrlOutput::from($output)->getLabel();
    }

    public function emptyStateMessage(): string
    {
        if ($this->hasActiveFilters()) {
            return (string) __('capell-site-discovery::generic.no_urls_for_filters');
        }

        return match ($this->queueView) {
            self::VIEW_NEEDS_ATTENTION => (string) __('capell-site-discovery::generic.no_urls_need_attention'),
            self::VIEW_UNAVAILABLE => (string) __('capell-site-discovery::generic.no_unavailable_outputs'),
            default => (string) __('capell-site-discovery::generic.no_registry_urls'),
        };
    }

    /**
     * @return Collection<int, PublicUrlRepairItemData>
     */
    private function filteredItems(): Collection
    {
        return collect($this->queue()->items)
            ->filter(fn (PublicUrlRepairItemData $item): bool => $this->matchesView($item))
            ->filter(fn (PublicUrlRepairItemData $item): bool => $this->matchesOutput($item))
            ->filter(fn (PublicUrlRepairItemData $item): bool => $this->matchesSite($item->row))
            ->filter(fn (PublicUrlRepairItemData $item): bool => $this->matchesSourcePackage($item->row))
            ->filter(fn (PublicUrlRepairItemData $item): bool => $this->matchesLanguage($item->row))
            ->filter(fn (PublicUrlRepairItemData $item): bool => $this->matchesIndexability($item->row))
            ->filter(fn (PublicUrlRepairItemData $item): bool => $this->matchesBooleanFilter($this->sitemapEligibleFilter, $item->row->isSitemapEligible))
            ->filter(fn (PublicUrlRepairItemData $item): bool => $this->matchesBooleanFilter($this->aiDiscoveryEligibleFilter, $item->row->isAiDiscoveryEligible));
    }

    /**
     * @return Collection<int, GeneratedOutputParityRowData>
     */
    private function allRows(): Collection
    {
        return collect($this->queue()->items)
            ->map(fn (PublicUrlRepairItemData $item): GeneratedOutputParityRowData => $item->row);
    }

    private function matchesView(PublicUrlRepairItemData $item): bool
    {
        return match ($this->queueView) {
            self::VIEW_NEEDS_ATTENTION => $item->status === PublicUrlRepairStatus::NeedsAttention,
            self::VIEW_UNAVAILABLE => $item->status === PublicUrlRepairStatus::Unavailable,
            default => true,
        };
    }

    private function matchesOutput(PublicUrlRepairItemData $item): bool
    {
        if ($this->outputFilter === '') {
            return true;
        }

        $output = PublicUrlOutput::tryFrom($this->outputFilter);

        return $output instanceof PublicUrlOutput && $item->affects($output);
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
}
