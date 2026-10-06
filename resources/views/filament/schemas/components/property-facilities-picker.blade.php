@php
    $categories = $this->getFacilityCategories();
    $activeCategoryId = $this->activeCategoryId;
    $activeCategory = $categories->firstWhere('id', $activeCategoryId);
@endphp

@if ($categories->isEmpty())
    <div class="flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-gray-200 px-6 py-12 text-center dark:border-gray-700">
        <x-heroicon-o-squares-2x2 class="h-8 w-8 text-gray-400 dark:text-gray-500" />
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('admin.no_facilities_available') }}
        </p>
    </div>
@else
    <div class="property-facilities-picker flex gap-4 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        {{-- Left Panel: Categories --}}
        <div class="property-facilities-cats w-64 shrink-0 border-r border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
            <div class="property-facilities-cats-header p-3">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    {{ __('admin.categories') }}
                </p>
            </div>
            <nav class="property-facilities-cats-nav flex flex-col">
                @foreach ($categories as $category)
                    @php
                        $selectedCount = $this->getSelectedCountForCategory($category->id);
                        $totalCount = $category->facilities->count();
                        $isActive = $category->id === $activeCategoryId;
                    @endphp

                    <button
                        type="button"
                        wire:click="setActiveCategory({{ $category->id }})"
                        @class([
                            'property-facilities-cat-btn flex items-center gap-3 px-4 py-3 text-left text-sm transition border-l-2',
                            'border-primary-600 bg-white text-primary-700 dark:bg-gray-900 dark:text-primary-400' => $isActive,
                            'border-transparent hover:bg-white dark:hover:bg-gray-900 text-gray-700 dark:text-gray-300' => !$isActive,
                        ])
                    >
                        {{-- Category Icon --}}
                        <div @class([
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
                            'bg-primary-50 dark:bg-primary-950' => $isActive,
                            'bg-gray-100 dark:bg-gray-800' => !$isActive,
                        ])>
                            @if ($category->getIconUrl())
                                <img
                                    src="{{ $category->getIconUrl() }}"
                                    alt="{{ $category->name }}"
                                    class="h-4 w-4 object-contain"
                                />
                            @else
                                <x-heroicon-o-squares-2x2 class="h-4 w-4 text-gray-400" />
                            @endif
                        </div>

                        {{-- Category Name + Counter --}}
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ $category->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $selectedCount }}/{{ $totalCount }} {{ __('admin.selected') }}
                            </p>
                        </div>
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Right Panel: Facilities for Active Category --}}
        <div class="property-facilities-items min-w-0 flex-1 p-5">
            @if ($activeCategory)
                <div class="mb-4">
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $activeCategory->name }}
                    </h4>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('admin.select_facilities_for_category') }}
                    </p>
                </div>

                @if ($activeCategory->facilities->isEmpty())
                    <p class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                        {{ __('admin.no_facilities_in_category') }}
                    </p>
                @else
                    <div class="property-facilities-grid grid grid-cols-2 gap-3">
                        @foreach ($activeCategory->facilities as $facility)
                            @php
                                $isSelected = in_array($facility->id, $this->selectedFacilities);
                            @endphp

                            <label
                                wire:key="facility-{{ $facility->id }}"
                                @class([
                                    'flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 transition',
                                    'border-primary-300 bg-primary-50 dark:border-primary-700 dark:bg-primary-950/50' => $isSelected,
                                    'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600' => !$isSelected,
                                ])
                            >
                                <input
                                    type="checkbox"
                                    wire:click="toggleFacility({{ $facility->id }})"
                                    @checked($isSelected)
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                />

                                <div class="flex items-center gap-2">
                                    @if ($facility->getIconUrl())
                                        <img
                                            src="{{ $facility->getIconUrl() }}"
                                            alt="{{ $facility->name }}"
                                            class="h-5 w-5 object-contain"
                                        />
                                    @endif
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $facility->name }}
                                    </span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="flex h-full items-center justify-center py-12">
                    <p class="text-sm text-gray-400 dark:text-gray-500">
                        {{ __('admin.select_category_to_view_facilities') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
@endif
