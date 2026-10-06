@php
    $bookedRooms = $booked_rooms ?? 1;
    $roomTypeName = $room_type_name ?? '';
    $checkIn = $check_in ?? '';
    $checkOut = $check_out ?? '';
@endphp

<div
    x-data="{
        maxRooms: {{ $bookedRooms }},
        get floors() { return $wire.selectRoomFloorData; },
        get selectedIds() { return $wire.pendingRoomIds; },
        get isMaxReached() { return this.selectedIds.length >= this.maxRooms; },
        isSelected(roomId) { return this.selectedIds.includes(roomId); },
        isBlocked(room) {
            if (room.is_occupied || room.status === 'inactive') return true;
            return this.isMaxReached && !this.isSelected(room.id);
        },
        toggle(room) {
            if (this.isBlocked(room)) return;
            $wire.togglePendingRoom(room.id);
        },
        availableCount(floor) {
            return floor.rooms.filter(r => !r.is_occupied && r.status !== 'inactive').length;
        },
        occupiedCount(floor) {
            return floor.rooms.filter(r => r.is_occupied).length;
        }
    }"
    class="space-y-4"
>
    {{-- Booking context bar --}}
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg px-4 py-3" style="background-color: var(--brand-primary-light, #eff6ff);">
        <div class="flex items-center gap-3 text-sm">
            <span class="font-medium text-primary-700">{{ $roomTypeName }}</span>
            <span class="text-primary-500">{{ $checkIn }} → {{ $checkOut }}</span>
        </div>
        <div class="text-sm">
            <span class="font-semibold text-primary-700" x-text="selectedIds.length"></span>
            <span class="text-primary-500">/ {{ $bookedRooms }} {{ str('room')->plural($bookedRooms) }} {{ __('admin.selected') }}</span>
        </div>
    </div>

    {{-- Empty state --}}
    <template x-if="floors.length === 0">
        <div class="flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-gray-200 py-12 text-center">
            <x-heroicon-o-building-office class="h-8 w-8 text-gray-400" />
            <p class="text-sm font-medium text-gray-500">{{ __('admin.no_rooms_in_inventory_for_type') }}</p>
            <p class="max-w-xs text-xs text-gray-400">{{ __('admin.no_rooms_in_inventory_description') }}</p>
        </div>
    </template>

    {{-- Floor sections --}}
    <template x-if="floors.length > 0">
        <div class="space-y-4">
            <template x-for="floor in floors" :key="floor.id">
                <div class="flex flex-col gap-6 rounded-2xl border border-gray-200 bg-white p-4">

                    {{-- Floor Header --}}
                    <div class="flex items-center gap-3 rounded-xl bg-gray-100 p-3">
                        <span class="flex-1 truncate text-lg font-semibold text-gray-950" x-text="floor.name"></span>
                        <div class="flex shrink-0 items-center gap-3 text-sm text-gray-600">
                            <span class="flex items-center gap-1.5">
                                <x-heroicon-o-home class="h-4 w-4" />
                                <span x-text="availableCount(floor) + ' {{ __('admin.rooms_available_stat') }}'"></span>
                            </span>
                            <span class="h-1 w-1 shrink-0 rounded-full bg-gray-400"></span>
                            <span class="flex items-center gap-1.5">
                                <x-heroicon-o-calendar-days class="h-4 w-4" />
                                <span x-text="occupiedCount(floor) + ' {{ __('admin.occupied_rooms_stat') }}'"></span>
                            </span>
                        </div>
                    </div>

                    {{-- Room Cards --}}
                    <div class="grid gap-3.5" style="grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));">
                        <template x-for="room in floor.rooms" :key="room.id">
                            <button
                                type="button"
                                @click="toggle(room)"
                                :disabled="isBlocked(room)"
                                :title="room.is_occupied ? '{{ __('admin.room_occupied') }}' : (room.status === 'inactive' ? '{{ __('admin.inactive') }}' : room.room_number)"
                                class="flex w-full flex-col items-center justify-between rounded-xl border p-3 transition-all shadow-xs"
                                style="min-height: 124px;"
                                :class="{
                                    'cursor-not-allowed opacity-60': isBlocked(room),
                                    'cursor-pointer hover:shadow-sm': !isBlocked(room)
                                }"
                                :style="
                                    room.status === 'inactive'
                                        ? 'background-color:#f9fafb;border-color:#e5e7eb;'
                                        : room.is_occupied
                                            ? 'background-color:#fff5f5;border-color:#fecaca;'
                                            : isSelected(room.id)
                                                ? 'background-color:var(--brand-primary-light, #eff6ff);border-color:var(--brand-primary, #2563eb);box-shadow:0 0 0 1px var(--brand-primary, #2563eb);'
                                                : isMaxReached
                                                    ? 'background-color:#f9fafb;border-color:#e5e7eb;opacity:0.5;'
                                                    : 'background-color:#ffffff;border-color:#e5e7eb;'
                                "
                                wire:loading.attr="disabled"
                            >
                                {{-- Room No section --}}
                                <div class="flex w-full flex-col items-center text-center">
                                    <span
                                        class="text-[11px] font-semibold uppercase tracking-wider"
                                        :style="
                                            room.status === 'inactive' ? 'color:#9ca3af;' : (room.is_occupied ? 'color:#f87171;' : (isSelected(room.id) ? 'color:var(--brand-primary, #2563eb);' : 'color:#9ca3af;'))
                                        "
                                    >{{ __('admin.room_no') }}</span>
                                    <span
                                        class="mt-0.5 text-2xl font-bold tracking-tight leading-tight"
                                        :style="
                                            room.status === 'inactive'
                                                ? 'color:#6b7280;'
                                                : (room.is_occupied
                                                    ? 'color:#7f1d1d;'
                                                    : (isSelected(room.id)
                                                        ? 'color:var(--brand-primary, #2563eb);'
                                                        : 'color:#111827;'))
                                        "
                                        x-text="room.room_number"
                                    ></span>
                                </div>

                                {{-- Room Type Pill --}}
                                <div
                                    class="w-full mt-2 rounded-lg px-2 py-1 text-center"
                                    :style="
                                        room.status === 'inactive'
                                            ? 'background-color:#f3f4f6;'
                                            : (room.is_occupied
                                                ? 'background-color:#fee2e2;'
                                                : (isSelected(room.id)
                                                    ? 'background-color:rgba(37,99,235,0.1);'
                                                    : 'background-color:#f9fafb;'))
                                    "
                                >
                                    <p
                                        class="text-xs font-medium line-clamp-2 leading-tight"
                                        :style="
                                            room.status === 'inactive'
                                                ? 'color:#6b7280;'
                                                : (room.is_occupied
                                                    ? 'color:#991b1b;'
                                                    : (isSelected(room.id)
                                                        ? 'color:var(--brand-primary, #2563eb);'
                                                        : 'color:#4b5563;'))
                                        "
                                        title="{{ $roomTypeName }}"
                                    >{{ $roomTypeName }}</p>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </template>

    {{-- Legend --}}
    <div class="sticky bottom-[-1.5rem] -mx-6 -mb-6 z-10 flex flex-wrap items-center justify-center gap-8 border-t border-gray-200 bg-gray-50/80 backdrop-blur-md px-6 py-4 shadow-[0_-4px_15px_rgba(0,0,0,0.02)]">
        <div class="flex items-center gap-2">
            <div class="h-3 w-3 shrink-0 rounded-full border border-gray-300 bg-white"></div>
            <span class="text-sm text-gray-950">{{ __('admin.available_rooms_legend') }}</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-3 w-3 shrink-0 rounded-full" style="background-color: var(--brand-primary, #2563eb);"></div>
            <span class="text-sm text-gray-950">{{ __('admin.selected') }}</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-3 w-3 shrink-0 rounded-full" style="background-color: #d63031;"></div>
            <span class="text-sm text-gray-950">{{ __('admin.booked_room_legend') }}</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-3 w-3 shrink-0 rounded-full" style="background-color: #9e9e9e;"></div>
            <span class="text-sm text-gray-950">{{ __('admin.inactive_room_legend') }}</span>
        </div>
    </div>
</div>
