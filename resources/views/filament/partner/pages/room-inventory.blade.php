<x-filament-panels::page>
    @php $property = $this->getCurrentProperty(); @endphp

    {{-- No property selected --}}
    @if (! $property)
    <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
            <x-heroicon-o-building-office-2 class="h-8 w-8 text-gray-400" />
            <h4 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.select_property_first') }}</h4>
            <p class="text-sm text-gray-500">{{ __('admin.select_property_room_inventory_description') }}</p>
        </div>
    </div>

    @else

    {{-- Inline Add Rooms Form --}}
    @if ($this->showAddForm)
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="mb-4 flex items-center gap-3">
            <x-heroicon-o-building-office class="h-5 w-5 text-gray-400" />
            <div>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.add_room') }}</h3>
                <p class="text-xs text-gray-500">{{ __('admin.add_room_description') }}</p>
            </div>
        </div>

        <div class="flex items-start gap-4">
            <div class="flex-1">
                {{ $this->form }}
            </div>
            <div class="pt-[26px]">
                <x-filament::button wire:click="generateRooms" color="primary">
                    {{ __('admin.generate_rooms') }}
                </x-filament::button>
            </div>
        </div>
    </div>
    @endif

    {{-- Bulk Action Banner --}}
    @if (count($this->selectedRoomIds) > 0)
    @php $bulkStatus = $this->getSelectedRoomStatusSummary(); @endphp
    <div class="flex items-center justify-between rounded-xl px-5 py-3" style="background-color: #eff6ff;">
        <div class="flex items-center gap-3">
            <div class="flex h-6 w-6 items-center justify-center rounded" style="background-color: #2563eb;">
                <x-heroicon-s-check class="h-4 w-4 text-white" />
            </div>
            <span class="text-sm font-medium text-gray-700">
                {{ __('admin.selected_rooms_for_bulk', ['count' => count($this->selectedRoomIds)]) }}
            </span>
        </div>
        <div class="flex items-center gap-2">
            @if ($bulkStatus['has_inactive'])
            <x-filament::button wire:click="prepBulkActivate" color="primary" size="sm">
                {{ __('admin.active_all') }}
            </x-filament::button>
            @endif
            @if ($bulkStatus['has_active'])
            <x-filament::button wire:click="prepBulkInactivate" color="danger" size="sm">
                {{ __('admin.inactive_all') }}
            </x-filament::button>
            @endif
        </div>
    </div>
    @endif

    {{-- Empty State --}}
    @if (! $this->hasRooms())
    <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
            <x-heroicon-o-building-office-2 class="h-8 w-8 text-gray-400" />
            <h4 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.no_room_added') }}</h4>
            <p class="text-sm text-gray-500">{{ __('admin.no_room_added_description') }}</p>
        </div>
    </div>

    @else

    {{-- Floor Sections --}}
    <div class="space-y-4">
        @foreach ($this->getFloorsWithRooms() as $floor)
        @php
        $totalRooms = $floor->rooms->count();
        $activeRooms = $floor->rooms->where('status.value', 'active')->count();
        @endphp

        <div
            x-data="{ open: true }"
            class="overflow-hidden rounded-2xl bg-gray-50 ring-1 ring-gray-200">
            {{-- Floor Header --}}
            <div @click="open = !open" class="flex cursor-pointer items-center gap-4 border-b border-gray-200 bg-gray-100 p-4">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-md bg-gray-900">
                    <span class="text-base font-semibold text-white">{{ $loop->iteration }}</span>
                </div>

                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-semibold text-gray-900">{{ $floor->name }}</h3>
                    <div class="mt-0.5 flex items-center gap-3">
                        <span class="flex items-center gap-1 text-sm text-gray-700">
                            <x-heroicon-o-building-office class="h-4 w-4" />
                            {{ $totalRooms }} {{ __('admin.total_rooms_label') }}
                        </span>
                        <div class="h-1 w-1 flex-shrink-0 rounded-full bg-gray-400"></div>
                        <span class="flex items-center gap-1 text-sm text-gray-700">
                            <x-heroicon-o-check-circle class="h-4 w-4" />
                            {{ $activeRooms }} {{ __('admin.active_rooms_label') }}
                        </span>
                    </div>
                </div>

                <div
                    class="pointer-events-none flex flex-shrink-0 items-center justify-center rounded-lg p-1 transition-colors"
                    :class="open ? 'bg-gray-950' : 'bg-gray-200'">
                    <x-heroicon-o-chevron-up class="h-5 w-5 transition-transform" x-bind:class="open ? 'text-white rotate-0' : 'text-gray-700 rotate-180'" />
                </div>
            </div>

            {{-- Room Cards Grid --}}
            <div x-show="open" x-collapse class="border-b border-gray-200 bg-gray-50 p-4">
                @if ($floor->rooms->isEmpty())
                <p class="text-sm text-gray-400">{{ __('admin.no_rooms_on_floor') }}</p>
                @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($floor->rooms as $room)
                    @php $isActive = $room->status->value === 'active'; @endphp

                    <div
                        x-data="{ hovered: false }"
                        @mouseenter="hovered = true"
                        @mouseleave="hovered = false"
                        @if (empty($this->selectedRoomIds))
                        wire:click="mountAction('editRoom', { roomId: {{ $room->id }} })"
                        @endif
                        class="flex flex-col gap-3 rounded-lg bg-white p-3 ring-1 transition-shadow {{ empty($this->selectedRoomIds) ? 'cursor-pointer' : 'cursor-default' }}"
                        :class="hovered ? 'shadow-[0px_6px_12px_0px_rgba(0,0,0,0.07)] ring-gray-200' : 'ring-gray-200'"
                        style="{{ in_array($room->id, $this->selectedRoomIds) ? 'outline: 2px solid #2563eb;' : '' }}">
                        {{-- Top row: checkbox + room name + status badge --}}
                        <div class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                wire:model.live="selectedRoomIds"
                                value="{{ $room->id }}"
                                @click.stop
                                class="h-6 w-6 flex-shrink-0 rounded-sm border border-gray-950 text-primary-600 focus:ring-primary-500" />
                            <span class="flex-1 truncate text-base font-normal text-gray-950">
                                {{ __('admin.room_label') }} {{ $room->room_number }}
                            </span>
                            @if ($isActive)
                            <span class="flex-shrink-0 rounded-lg bg-green-50 px-2 py-1 text-sm font-medium leading-5 text-green-600">
                                {{ $room->status->label() }}
                            </span>
                            @else
                            <span class="flex-shrink-0 rounded-lg bg-red-50 px-2 py-1 text-sm font-medium leading-5 text-red-600">
                                {{ $room->status->label() }}
                            </span>
                            @endif
                        </div>

                        {{-- Divider --}}
                        <div class="border-t border-gray-200"></div>

                        {{-- Bottom row: room type + edit/delete (on hover) --}}
                        <div class="flex h-6 items-center gap-2">
                            <span class="flex-1 truncate text-sm text-gray-700">
                                {{ $room->propertyRoom?->roomType?->name ?? '—' }}
                            </span>
                            @if (empty($this->selectedRoomIds))
                            <div x-show="hovered" class="flex flex-shrink-0 items-center gap-1">
                                <button
                                    type="button"
                                    wire:click="mountAction('editRoom', { roomId: {{ $room->id }} })"
                                    @click.stop
                                    class="flex h-6 w-6 items-center justify-center text-gray-950 transition hover:text-gray-600"
                                    title="{{ __('admin.edit') }}">
                                    <x-phosphor-pencil-simple-line class="h-5 w-5" />
                                </button>
                                <button
                                    type="button"
                                    wire:click="mountAction('deleteRoom', { roomId: {{ $room->id }} })"
                                    @click.stop
                                    class="flex h-6 w-6 items-center justify-center text-red-600 transition hover:text-red-700"
                                    title="{{ __('admin.delete') }}">
                                    <x-phosphor-trash class="h-5 w-5" />
                                </button>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    @endif

    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
