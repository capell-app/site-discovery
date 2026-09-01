<x-filament-panels::page>
    @php
        $queue = $this->queue();
        $items = $this->items();
        $outputCases = \Capell\SiteDiscovery\Enums\PublicUrlOutput::cases();
    @endphp

    <div class="grid gap-4 md:grid-cols-3">
        <div
            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900"
        >
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('capell-site-discovery::generic.urls_needing_attention') }}
            </div>
            <div
                class="text-danger-600 dark:text-danger-400 mt-1 text-2xl font-semibold"
            >
                {{ number_format($queue->needsAttentionUrls) }}
            </div>
        </div>
        <div
            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900"
        >
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('capell-site-discovery::generic.urls_with_unavailable_outputs') }}
            </div>
            <div
                class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white"
            >
                {{ number_format($queue->unavailableUrls) }}
            </div>
        </div>
        <div
            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900"
        >
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('capell-site-discovery::generic.registry_urls') }}
            </div>
            <div
                class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white"
            >
                {{ number_format($queue->totalUrls) }}
            </div>
        </div>
    </div>

    <div
        class="flex flex-wrap gap-2"
        role="group"
        aria-label="{{ __('capell-site-discovery::generic.queue_view') }}"
    >
        @foreach ($this->viewOptions() as $value => $label)
            <button
                type="button"
                wire:click="$set('queueView', '{{ $value }}')"
                aria-pressed="{{ $this->queueView === $value ? 'true' : 'false' }}"
                @class([
                    'focus-visible:ring-primary-600 dark:focus-visible:ring-primary-400 inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium ring-1 ring-inset focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900',
                    'bg-primary-600 text-white ring-primary-600' => $this->queueView === $value,
                    'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/5' => $this->queueView !== $value,
                ])
            >
                {{ $label }}
                <span
                    @class([
                        'rounded-md px-1.5 py-0.5 text-xs font-semibold',
                        'bg-white/20 text-white' => $this->queueView === $value,
                        'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => $this->queueView !== $value,
                    ])
                >
                    {{ number_format($this->viewCount($value)) }}
                </span>
            </button>
        @endforeach
    </div>

    <div
        class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900"
    >
        <div class="grid gap-3 md:grid-cols-3">
            <label class="block">
                <span
                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    {{ __('capell-site-discovery::generic.output') }}
                </span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="outputFilter">
                        <option value="">
                            {{ __('capell-site-discovery::generic.all_outputs') }}
                        </option>
                        @foreach ($this->outputOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>

            <label class="block">
                <span
                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    {{ __('capell-site-discovery::generic.site') }}
                </span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="siteFilter">
                        <option value="">
                            {{ __('capell-site-discovery::generic.all_sites') }}
                        </option>
                        @foreach ($this->siteOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>

            <div class="flex items-end gap-2">
                <x-filament::button
                    color="gray"
                    icon="heroicon-m-adjustments-horizontal"
                    type="button"
                    wire:click="toggleAdvancedFilters"
                    aria-expanded="{{ $this->showAdvancedFilters ? 'true' : 'false' }}"
                    aria-controls="public-url-registry-advanced-filters"
                >
                    {{ __('capell-site-discovery::generic.advanced_filters') }}
                </x-filament::button>

                @if ($this->hasActiveFilters())
                    <x-filament::button
                        color="gray"
                        type="button"
                        wire:click="clearFilters"
                    >
                        {{ __('capell-site-discovery::generic.clear_filters') }}
                    </x-filament::button>
                @endif
            </div>
        </div>

        @if ($this->showAdvancedFilters)
            <div
                id="public-url-registry-advanced-filters"
                class="grid gap-3 border-t border-gray-200 pt-3 md:grid-cols-3 dark:border-white/10"
            >
                <label class="block">
                    <span
                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        {{ __('capell-site-discovery::generic.source_package') }}
                    </span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            wire:model.live="sourcePackageFilter"
                        >
                            <option value="">
                                {{ __('capell-site-discovery::generic.all_source_packages') }}
                            </option>
                            @foreach ($this->sourcePackageOptions() as $value => $label)
                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="block">
                    <span
                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        {{ __('capell-site-discovery::generic.language') }}
                    </span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            wire:model.live="languageFilter"
                        >
                            <option value="">
                                {{ __('capell-site-discovery::generic.all_languages') }}
                            </option>
                            @foreach ($this->languageOptions() as $value => $label)
                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="block">
                    <span
                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        {{ __('capell-site-discovery::generic.indexability') }}
                    </span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            wire:model.live="indexabilityFilter"
                        >
                            <option value="">
                                {{ __('capell-site-discovery::generic.all_indexability') }}
                            </option>
                            @foreach ($this->indexabilityOptions() as $value => $label)
                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="block">
                    <span
                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        {{ __('capell-site-discovery::generic.sitemap_eligible') }}
                    </span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            wire:model.live="sitemapEligibleFilter"
                        >
                            <option value="">
                                {{ __('capell-site-discovery::generic.all_sitemap_states') }}
                            </option>
                            @foreach ($this->binaryFilterOptions() as $value => $label)
                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="block">
                    <span
                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        {{ __('capell-site-discovery::generic.ai_discovery_eligible') }}
                    </span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            wire:model.live="aiDiscoveryEligibleFilter"
                        >
                            <option value="">
                                {{ __('capell-site-discovery::generic.all_ai_discovery_states') }}
                            </option>
                            @foreach ($this->binaryFilterOptions() as $value => $label)
                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>
        @endif
    </div>

    <div
        class="space-y-3"
        data-public-url-repair-queue
    >
        @forelse ($items as $item)
            <div
                wire:key="public-url-repair-item-{{ hash('xxh128', $item->canonicalUrl()) }}"
                class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a
                            class="text-primary-600 hover:text-primary-500 dark:text-primary-400 focus-visible:ring-primary-600 dark:focus-visible:ring-primary-400 block break-all text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
                            href="{{ $item->canonicalUrl() }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ $item->canonicalUrl() }}
                        </a>
                        <div
                            class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            <span>{{ $item->row->sourcePackage }}</span>
                            <span>
                                {{ __('capell-site-discovery::generic.site') }}: {{ $item->row->siteKey }}
                            </span>
                            <span>
                                {{ __('capell-site-discovery::generic.language') }}: {{ $item->row->languageCode ?? $item->row->languageKey }}
                            </span>
                            @if ($item->row->routeName)
                                <span>{{ $item->row->routeName }}</span>
                            @endif
                        </div>
                    </div>

                    <span
                        class="{{ $this->repairStatusClass($item->status) }} inline-flex shrink-0 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                    >
                        {{ $item->status->getLabel() }}
                    </span>
                </div>

                @if ($item->issues !== [])
                    <ul class="mt-3 space-y-2">
                        @foreach ($item->issues as $issue)
                            <li
                                class="rounded-md bg-gray-50 p-3 text-sm dark:bg-white/5"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="{{ $this->statusClass($issue->status) }} inline-flex rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                    >
                                        {{ $issue->status->getLabel() }}
                                    </span>
                                    <span
                                        class="font-medium text-gray-950 dark:text-white"
                                    >
                                        {{ $issue->output->getLabel() }}
                                    </span>
                                    <span
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __('capell-site-discovery::generic.handled_by') }} {{ $issue->responsibleArea }}
                                    </span>
                                </div>
                                <p
                                    class="mt-1 text-gray-700 dark:text-gray-300"
                                >
                                    {{ $issue->reason }}
                                </p>
                                <p
                                    class="mt-1 text-gray-600 dark:text-gray-400"
                                >
                                    <span class="font-medium">
                                        {{ __('capell-site-discovery::generic.next_step') }}:
                                    </span>
                                    {{ $issue->nextStep }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('capell-site-discovery::generic.all_eligible_outputs_present') }}
                    </p>
                @endif

                <details class="group mt-3">
                    <summary
                        class="focus-visible:ring-primary-600 dark:focus-visible:ring-primary-400 cursor-pointer text-sm font-medium text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:text-gray-300 dark:focus-visible:ring-offset-gray-900"
                    >
                        {{ __('capell-site-discovery::generic.full_output_matrix') }}
                    </summary>

                    <div class="mt-2 overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-gray-200 text-xs dark:divide-white/10"
                        >
                            <thead>
                                <tr>
                                    @foreach ($outputCases as $output)
                                        <th
                                            scope="col"
                                            class="px-3 py-2 text-left font-semibold text-gray-950 dark:text-white"
                                        >
                                            {{ $output->getLabel() }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    @foreach ($item->matrix() as $status)
                                        <td class="px-3 py-2">
                                            <span
                                                class="{{ $this->statusClass($status) }} inline-flex rounded-md px-2 py-0.5 font-medium ring-1 ring-inset"
                                            >
                                                {{ $status->getLabel() }}
                                            </span>
                                        </td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <dl
                        class="mt-2 grid gap-x-4 gap-y-1 text-xs text-gray-600 sm:grid-cols-2 dark:text-gray-400"
                    >
                        <div class="flex gap-1">
                            <dt class="font-medium">
                                {{ __('capell-site-discovery::generic.indexability') }}:
                            </dt>
                            <dd>{{ $item->row->indexability->getLabel() }}</dd>
                        </div>
                        <div class="flex gap-1">
                            <dt class="font-medium">
                                {{ __('capell-site-discovery::generic.content_type') }}:
                            </dt>
                            <dd>{{ $item->row->contentType->getLabel() }}</dd>
                        </div>
                        <div class="flex gap-1">
                            <dt class="font-medium">
                                {{ __('capell-site-discovery::generic.sitemap_eligible') }}:
                            </dt>
                            <dd>
                                {{ $item->row->isSitemapEligible ? __('capell-site-discovery::generic.filter_yes') : __('capell-site-discovery::generic.filter_no') }}
                            </dd>
                        </div>
                        <div class="flex gap-1">
                            <dt class="font-medium">
                                {{ __('capell-site-discovery::generic.ai_discovery_eligible') }}:
                            </dt>
                            <dd>
                                {{ $item->row->isAiDiscoveryEligible ? __('capell-site-discovery::generic.filter_yes') : __('capell-site-discovery::generic.filter_no') }}
                            </dd>
                        </div>
                    </dl>
                </details>
            </div>
        @empty
            <div
                class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-400"
            >
                {{ $this->emptyStateMessage() }}
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
