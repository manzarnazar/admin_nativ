<x-filament-panels::page>
    <div class="flex flex-col gap-6">

        <!-- Header Text -->
        <p class="text-gray-500 dark:text-gray-400 text-sm -mt-4">
            Generate and export structured data across all platform operations
        </p>

        <!-- Top Bar: Search and Tabs in one row -->
        <div class="bg-[#F7F7F7] dark:bg-gray-900/50 rounded-xl p-2 flex items-center gap-3 overflow-x-auto w-full">
            <!-- Search -->
            <div class="w-64 shrink-0 bg-white dark:bg-gray-800 rounded-lg">
                <x-filament::input.wrapper icon="heroicon-m-magnifying-glass" class="rounded-lg border-gray-200 dark:border-gray-700 shadow-sm">
                    <x-filament::input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by reports..."
                        class="text-sm border-0 focus:ring-0" />
                </x-filament::input.wrapper>
            </div>

            <!-- Tabs -->
            <div class="flex items-center gap-2 shrink-0">
                @foreach ($this->tabs as $tab)
                <button
                    wire:click="$set('activeTab', '{{ $tab }}')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors border shadow-sm {{ $this->activeTab === $tab ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700 dark:hover:bg-gray-700' }}">
                    {{ $tab }}
                </button>
                @endforeach
            </div>
        </div>

        <!-- Sections & Cards -->
        <div class="flex flex-col gap-8 mt-4">
            @forelse ($this->filteredSections as $sectionTitle => $reports)
            <div class="flex flex-col gap-4">
                <!-- Section Header with Primary vertical line -->
                <div class="flex items-center gap-2">
                    <div class="w-1 h-5 bg-primary-600 rounded-sm"></div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white tracking-wide">
                        {{ $sectionTitle }} Reports
                    </h2>
                </div>

                <!-- 3 Column Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach ($reports as $report)
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 flex flex-col hover:shadow-sm transition-shadow min-h-[160px]">
                        <!-- Icon with #F7F7F7 background -->
                        <div class="w-10 h-10 rounded-lg bg-[#F7F7F7] dark:bg-gray-800 flex items-center justify-center mb-4">
                            <x-filament::icon
                                icon="{{ $report['icon'] }}"
                                class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                        </div>

                        <h3 class="font-bold text-gray-900 dark:text-white text-sm mb-1.5">
                            {{ $report['title'] }}
                        </h3>

                        <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed mb-4">
                            {{ $report['description'] }}
                        </p>

                        <div class="mt-auto">
                            <a href="{{ $report['url'] }}" class="inline-flex items-center text-xs font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 transition-colors">
                                View Report
                                <x-filament::icon
                                    icon="heroicon-m-arrow-right"
                                    class="w-3.5 h-3.5 ml-1" />
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @empty
            <div class="flex items-center justify-center p-8 text-sm text-gray-500 dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-700 rounded-xl">
                No reports found matching your criteria.
            </div>
            @endforelse
        </div>

    </div>
</x-filament-panels::page>