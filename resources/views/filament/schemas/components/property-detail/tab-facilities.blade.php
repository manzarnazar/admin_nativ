{{--
    Shared "Facilities" tab body. Expects:
    - $groupedFacilities: Collection<int, array{category: ?FacilityCategory, facilities: Collection<int, Facility>}>

    Icons mirror the single-admin "Facilities & Amenities" management page
    exactly (app/Filament/Pages/FacilityManage.php) — an uploaded icon image
    is shown when present, nothing otherwise. No emoji/heroicon fallback.
--}}
<div class="rounded-2xl border border-[#EDEDED] bg-white dark:border-gray-700 dark:bg-gray-900">
    <div class="border-b border-[#EDEDED] p-6 dark:border-gray-700">
        <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.property_facilities') }}</h3>
    </div>

    <div class="p-6">
        @if ($groupedFacilities->isEmpty())
            <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('admin.no_facilities_selected') }}</p>
        @else
            <div class="flex flex-col gap-4">
                @foreach ($groupedFacilities as $group)
                    @php $category = $group['category']; @endphp
                    <div class="rounded-xl bg-[#FAFAFA] p-4 dark:bg-gray-800/50">
                        <div class="mb-3 flex items-center gap-2">
                            @if ($category?->getIconUrl())
                                <img src="{{ $category->getIconUrl() }}" alt="{{ $category->name }}" class="h-5 w-5 shrink-0 object-contain">
                            @endif
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $category->name ?? __('admin.uncategorized') }}</span>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($group['facilities'] as $facility)
                                <div class="flex items-center gap-2 rounded-lg border border-[#EDEDED] bg-white px-3 py-2.5 dark:border-gray-700 dark:bg-gray-900">
                                    @if ($facility->getIconUrl())
                                        <div class="shrink-0 rounded-md bg-primary-50 p-1 dark:bg-primary-950">
                                            <img src="{{ $facility->getIconUrl() }}" alt="{{ $facility->name }}" class="h-4 w-4 object-contain">
                                        </div>
                                    @endif
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $facility->name }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
