@php
    $rooms = $this->getPropertyRooms();
    $hasRooms = $this->hasPropertyRooms();
    $currencySymbol = $this->getCurrencySymbol();
    $roomFormMode = $this->roomFormMode;
@endphp

<div class="space-y-4">
    @if ($roomFormMode)
        {{-- Inline Room Form (Add / Edit) --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ $roomFormMode === 'edit' ? __('admin.edit_room') : __('admin.add_room') }}
                </h3>
            </div>

            {{ $this->roomForm }}

            <div class="flex justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                <x-filament::button color="gray" wire:click="cancelRoomForm">
                    {{ __('admin.cancel') }}
                </x-filament::button>

                <x-filament::button wire:click="saveRoom">
                    {{ __('admin.save_room') }}
                </x-filament::button>
            </div>
        </div>
    @else
        @if ($hasRooms)
            {{-- Room Cards --}}
            <div class="space-y-3">
                @foreach ($rooms as $room)
                    @php
                        $roomType = $room->roomType;
                        $amenitiesCount = $roomType->facilities->count();
                    @endphp

                    <div class="group relative rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600">
                        {{-- Actions --}}
                        <div class="absolute right-4 top-4 flex items-center gap-2">
                            <x-filament::icon-button
                                icon="heroicon-o-pencil"
                                color="gray"
                                wire:click="showEditRoomForm({{ $room->id }})"
                            />
                            {{ ($this->deleteRoomAction)(['room' => $room->id]) }}
                        </div>

                        <div class="flex items-start gap-4">
                            {{-- Room Type Image --}}
                            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                                @if ($roomType->images->isNotEmpty())
                                    <img src="{{ asset('storage/' . $roomType->images->first()->image_path) }}" alt="{{ $roomType->name }}" class="h-full w-full object-cover" />
                                @else
                                    <div class="flex h-full w-full items-center justify-center">
                                        <x-heroicon-o-building-office class="h-5 w-5 text-gray-400" />
                                    </div>
                                @endif
                            </div>

                            {{-- Room Details --}}
                            <div class="min-w-0 flex-1">
                                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $roomType->name }}
                                </h4>

                                <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="inline-flex items-center gap-1">
                                        <x-heroicon-o-users class="h-3.5 w-3.5" />
                                        {{ __('admin.up_to_x_guests', ['count' => $roomType->max_guests]) }}
                                    </span>

                                    <span class="inline-flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2 17.5V20h20v-2.5M2 17.5V15a2 2 0 012-2h4a2 2 0 012 2v2.5M2 17.5h8m12 0V10a2 2 0 00-2-2H10v9.5m12 0H10M4 13V8.5A1.5 1.5 0 015.5 7h3A1.5 1.5 0 0110 8.5V13" /></svg>
                                        {{ $roomType->bed_type }}
                                    </span>

                                    <span class="inline-flex items-center gap-1">
                                        <x-heroicon-o-home class="h-3.5 w-3.5" />
                                        {{ $room->total_rooms }} {{ str('Room')->plural($room->total_rooms) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Footer: Amenities + Price --}}
                        <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-800">
                            <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center gap-1">
                                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                                        <x-heroicon-s-check class="h-2.5 w-2.5 text-green-600 dark:text-green-400" />
                                    </span>
                                    {{ __('admin.amenities') }}
                                </span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">
                                    {{ $amenitiesCount }} {{ __('admin.selected') }}
                                </span>
                            </div>

                            <div class="text-right">
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.base_price') }}</p>
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $currencySymbol }}{{ number_format($room->base_price_per_night, 2) }} / {{ __('admin.night') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- Empty State --}}
            <div class="flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-gray-200 px-6 py-12 text-center dark:border-gray-700">
                <x-heroicon-o-building-office class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ __('admin.no_rooms_added') }}
                </p>
                <p class="max-w-sm text-xs text-gray-400 dark:text-gray-500">
                    {{ __('admin.no_rooms_description') }}
                </p>
            </div>
        @endif
    @endif
</div>

<x-filament-actions::modals />
