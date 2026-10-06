@php
    $categories = $this->getFacilitiesByCategory();
@endphp

<div class="space-y-4">
    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.property_facilities') }}</h3>

    @if ($categories->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.no_facilities_selected') }}</p>
    @else
        @foreach ($categories as $category)
            <div class="rounded-lg bg-gray-50 p-5 dark:bg-gray-800">
                <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                    @if ($category->getIconUrl())
                        <img src="{{ $category->getIconUrl() }}" alt="{{ $category->name }}" class="h-5 w-5" />
                    @else
                        <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                    @endif
                    {{ $category->name }}
                </h4>

                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
                    @foreach ($category->facilities as $facility)
                        <div class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 dark:bg-gray-900">
                            <x-heroicon-s-check-circle class="h-4 w-4 shrink-0 text-green-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $facility->name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</div>
