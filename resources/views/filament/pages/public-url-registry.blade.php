<x-filament-panels::page>
    @php
        $report = $this->report();
        $rows = $this->rows();
    @endphp

    <div class="grid gap-4 md:grid-cols-3">
        <div
            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900"
        >
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('capell-site-discovery::generic.registry_urls') }}
            </div>
            <div
                class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white"
            >
                {{ number_format($report->totalUrls) }}
            </div>
        </div>
        <div
            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900"
        >
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('capell-site-discovery::generic.missing_output_urls') }}
            </div>
            <div
                class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white"
            >
                {{ number_format($report->missingOutputUrls) }}
            </div>
        </div>
        <div
            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900"
        >
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('capell-site-discovery::generic.visible_rows') }}
            </div>
            <div
                class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white"
            >
                {{ number_format(count($rows)) }}
            </div>
        </div>
    </div>

    <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-7">
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="sourcePackageFilter">
                <option value="">
                    {{ __('capell-site-discovery::generic.all_source_packages') }}
                </option>
                @foreach ($this->sourcePackageOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

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

        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="languageFilter">
                <option value="">
                    {{ __('capell-site-discovery::generic.all_languages') }}
                </option>
                @foreach ($this->languageOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="indexabilityFilter">
                <option value="">
                    {{ __('capell-site-discovery::generic.all_indexability') }}
                </option>
                @foreach ($this->indexabilityOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="sitemapEligibleFilter">
                <option value="">
                    {{ __('capell-site-discovery::generic.all_sitemap_states') }}
                </option>
                @foreach ($this->binaryFilterOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::input.wrapper>
            <x-filament::input.select
                wire:model.live="aiDiscoveryEligibleFilter"
            >
                <option value="">
                    {{ __('capell-site-discovery::generic.all_ai_discovery_states') }}
                </option>
                @foreach ($this->binaryFilterOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="missingOutputFilter">
                <option value="">
                    {{ __('capell-site-discovery::generic.all_output_states') }}
                </option>
                @foreach ($this->missingOutputOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    <div
        class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
    >
        <div class="overflow-x-auto">
            <table
                class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10"
            >
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th
                            class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white"
                        >
                            {{ __('capell-site-discovery::generic.canonical_url') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white"
                        >
                            {{ __('capell-site-discovery::generic.source_package') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white"
                        >
                            {{ __('capell-site-discovery::generic.indexability') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white"
                        >
                            {{ __('capell-site-discovery::generic.sitemap') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white"
                        >
                            {{ __('capell-site-discovery::generic.ai_discovery') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white"
                        >
                            {{ __('capell-site-discovery::generic.search') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white"
                        >
                            {{ __('capell-site-discovery::generic.html_cache') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white"
                        >
                            {{ __('capell-site-discovery::generic.agent_delivery') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white"
                        >
                            {{ __('capell-site-discovery::generic.errors') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($rows as $row)
                        <tr
                            wire:key="public-url-registry-row-{{ hash('xxh128', $row->canonicalUrl) }}"
                        >
                            <td
                                class="max-w-md px-4 py-3 text-gray-950 dark:text-white"
                            >
                                <a
                                    class="text-primary-600 hover:text-primary-500 dark:text-primary-400 font-medium"
                                    href="{{ $row->canonicalUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {{ $row->canonicalUrl }}
                                </a>
                                @if ($row->routeName)
                                    <div
                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ $row->routeName }}
                                    </div>
                                @endif
                            </td>
                            <td
                                class="px-4 py-3 text-gray-700 dark:text-gray-300"
                            >
                                {{ $row->sourcePackage }}
                            </td>
                            <td
                                class="px-4 py-3 text-gray-700 dark:text-gray-300"
                            >
                                {{ $row->indexability->getLabel() }}
                            </td>
                            @foreach ([
                                $row->sitemapStatus,
                                $row->aiDiscoveryStatus,
                                $row->searchStatus,
                                $row->htmlCacheStatus,
                                $row->agentDeliveryStatus,
                            ] as $status)
                                <td class="px-4 py-3">
                                    <span
                                        class="{{ $this->statusClass($status) }} inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                                    >
                                        {{ $status->getLabel() }}
                                    </span>
                                </td>
                            @endforeach

                            <td
                                class="px-4 py-3 text-gray-700 dark:text-gray-300"
                            >
                                @forelse ($row->errors as $error)
                                    <div>{{ $this->errorLabel($error) }}</div>
                                @empty
                                    <span
                                        class="text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __('capell-site-discovery::generic.none') }}
                                    </span>
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="9"
                                class="px-4 py-8 text-center text-gray-500 dark:text-gray-400"
                            >
                                {{ __('capell-site-discovery::generic.no_registry_urls') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
