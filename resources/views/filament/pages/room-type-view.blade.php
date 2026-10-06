<x-filament-panels::page>
    {{-- Back Link --}}
    @if (\App\Support\SystemMode::isSingle())
        <a href="{{ \App\Filament\Pages\RoomTypeManage::getUrl() }}"
           wire:navigate
           class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            {{ __('admin.back_to_room_list') }}
        </a>
    @else
        <button type="button" onclick="window.history.back()"
           class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            {{ __('admin.back') }}
        </button>
    @endif

    {{-- Room Name & ID --}}
    <div>
        <h2 class="text-xl font-bold text-gray-950 dark:text-white">{{ $record->name }}</h2>
        <span class="mt-1 inline-flex rounded-md bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-600 dark:bg-primary-950 dark:text-primary-400">
            ID - {{ $record->id }}
        </span>
    </div>

    {{-- Room Description --}}
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.room_description') }}</h3>
        <div class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
            {!! $record->description !!}
        </div>
    </div>

    {{-- Room Photos --}}
    @if ($record->images->isNotEmpty())
        <div x-data="{ showAll: false }" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('admin.room_photos') }} ({{ $record->images->count() }})
                </h3>
                @if ($record->images->count() > 4)
                    <button @click="showAll = !showAll" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <x-heroicon-o-chevron-up x-show="showAll" class="h-5 w-5" />
                        <x-heroicon-o-chevron-down x-show="!showAll" class="h-5 w-5" />
                    </button>
                @endif
            </div>

            <div class="grid grid-cols-4 gap-4">
                @foreach ($record->images as $index => $image)
                    <div x-show="{{ $index < 4 ? 'true' : 'showAll' }}" x-cloak>
                        <img
                            src="{{ Storage::disk('public')->url($image->image_path) }}"
                            alt="{{ $record->name }}"
                            class="h-40 w-full rounded-lg object-cover"
                        />
                    </div>
                @endforeach
            </div>

            @if ($record->images->count() > 4)
                <div x-show="!showAll" class="mt-4 flex justify-center">
                    <button @click="showAll = true" class="inline-flex items-center gap-1.5 rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                        <x-heroicon-o-plus-circle class="h-4 w-4" />
                        {{ __('admin.show_more_photos') }}
                    </button>
                </div>
            @endif
        </div>
    @endif

    {{-- Room Specification --}}
    <div>
        <h3 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.room_specification') }}</h3>
        <div class="grid grid-cols-2 gap-4">
            <div class="rounded-xl border border-gray-200 bg-white px-6 py-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center">
                    <x-icon-bed class="h-6 w-6" />
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.bed_type') }}</p>
                <p class="mt-1 text-base font-bold text-gray-950 dark:text-white">{{ $record->bed_type }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white px-6 py-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center">
                    <x-icon-users class="h-6 w-6" />
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.max_guest_allowed') }}</p>
                <p class="mt-1 text-base font-bold text-gray-950 dark:text-white">{{ $record->max_guests }}</p>
            </div>
        </div>
    </div>

    {{-- Room Amenities --}}
    @if ($record->facilities->isNotEmpty())
        <div>
            <h3 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.room_amenities') }}</h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($record->facilities as $facility)
                    <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                        @if ($facility->getIconUrl())
                            <img
                                src="{{ $facility->getIconUrl() }}"
                                alt="{{ $facility->name }}"
                                class="h-5 w-5 shrink-0 object-contain"
                            />
                        @else
                            <x-heroicon-o-wifi class="h-5 w-5 shrink-0 text-gray-400" />
                        @endif
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $facility->name }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
