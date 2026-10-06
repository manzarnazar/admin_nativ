<x-filament-panels::page>
    @php
    $property = $this->getCurrentProperty();
    @endphp

    @if (! $property)
    <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
            <x-heroicon-o-calendar-days class="h-8 w-8 text-gray-400" />
            <h4 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.select_property_first') }}</h4>
            <p class="text-sm text-gray-500">{{ __('admin.select_property_calendar_description') }}</p>
        </div>
    </div>
    @else

    {{-- Date Controls + Search (Calendar View only) --}}
    @if ($activeView === 'calendar')
    <div class="flex flex-wrap items-center justify-end gap-3">
        <div class="cal-date-controls flex items-center gap-2">
            <button
                wire:click="goToToday"
                x-on:click="$nextTick(() => { const el = document.querySelector('[style*=\'overflow-x: auto\']'); if (el) el.scrollLeft = 0; })"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                {{ __('admin.today') }}
            </button>
            <input
                type="date"
                wire:model.live="startDate"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300" />
            <span class="text-sm text-gray-400">→</span>
            <input
                type="date"
                wire:model.live="endDate"
                min="{{ $startDate }}"
                placeholder="{{ __('admin.end_date_optional') }}"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300" />
        </div>
    </div>
    <div class="cal-search-wrap relative w-72">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
        </div>
        <input
            type="text"
            wire:model.live.debounce.500ms="search"
            placeholder="{{ __('admin.search_by_guest_name') }}"
            class="block w-full rounded-lg border border-gray-300 bg-[#F7F7F7] py-2.5 pl-10 pr-4 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- CALENDAR VIEW                                                  --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    @if ($activeView === 'calendar')
    @php
    $dateColumns = $this->getDateColumns();
    $roomTypes = $this->getRoomTypes();
    $colCount = count($dateColumns);
    $tableWidth = max(1200, $colCount * 155 + 230);
    @endphp

    <style>
        .calendar-scroll::-webkit-scrollbar {
            height: 1px;
        }

        .calendar-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .calendar-scroll::-webkit-scrollbar-thumb {
            background-color: #d1d5db;
            border-radius: 9999px;
        }
    </style>
    <div class="relative">
        <div
            class="calendar-scroll rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            style="overflow-x: auto; cursor: grab;"
            x-data="{ dragging: false, startX: 0, sl: 0 }"
            @mousedown.prevent="dragging = true; startX = $event.pageX; sl = $el.scrollLeft; $el.style.cursor = 'grabbing'"
            @mouseup.window="dragging = false; $el.style.cursor = 'grab'"
            @mousemove.window="if (dragging) { $el.scrollLeft = sl - ($event.pageX - startX) }">
            <table style="width: {{ $tableWidth }}px; border-collapse: separate; border-spacing: 0;">
                {{-- Header --}}
                <thead>
                    <tr>
                        <th class="cal-sticky-col sticky left-0 z-30 border-b border-r border-gray-200 px-4 py-2 text-left dark:border-gray-700 dark:bg-gray-800" style="width: 230px; min-width: 230px; background-color: #F7F7F7; cursor: default;"
                            @mousedown.stop>
                            <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400">{{ __('admin.todays_rooms_details') }}</span>
                        </th>
                        @foreach ($dateColumns as $col)
                        <th class="border-b border-r border-gray-200 px-1 py-2 text-center dark:border-gray-700"
                            style="min-width: 90px; background-color: {{ $col['isToday'] ? 'var(--brand-primary-light)' : '#F7F7F7' }}; {{ $col['isToday'] ? 'border-top: 3px solid var(--brand-primary);' : '' }}">
                            <span class="block text-[10px] font-medium text-gray-400">{{ $col['dayName'] }}</span>
                            <span @class([ 'block text-sm font-bold' , 'text-primary-600'=> $col['isToday'],
                                'text-gray-950 dark:text-white' => ! $col['isToday'],
                                ])>{{ $col['dayNum'] }} {{ $col['monthName'] }}</span>
                        </th>
                        @endforeach
                    </tr>
                </thead>

                {{-- Room Rows --}}
                <tbody>
                    @foreach ($roomTypes as $roomData)
                    @php
                    $room = $roomData['room'];
                    $bookings = $this->getBookingsForRoom($room->id);
                    $positions = $bookings->map(fn ($b) => $this->getBookingPosition($b, $dateColumns))->filter()->values();
                    $maxVisible = 4;
                    $hasOverflow = $positions->count() > $maxVisible;
                    $visibleSlots = $hasOverflow ? $maxVisible + 1 : max(1, $positions->count());
                    $rowHeight = $visibleSlots * 32 + 16;
                    @endphp

                    <tr x-data="{ expanded: false }">
                        {{-- Room Info --}}
                        <td class="cal-sticky-col sticky left-0 z-30 border-b border-r border-gray-200 px-4 py-4 align-top dark:border-gray-700 dark:bg-gray-800" style="width: 230px; min-width: 230px; background-color: #F7F7F7; cursor: default;"
                            @mousedown.stop>
                            <p style="font-size: 14px; font-weight: 700; color: #111827; margin: 0;">{{ $room->roomType->name }}</p>
                            <p style="font-size: 11px; color: #9ca3af; margin-top: 6px; white-space: nowrap; display: flex; gap: 12px;">
                                <span style="display: flex; align-items: center; gap: 4px;">
                                    <x-heroicon-o-home style="width: 12px; height: 12px;" />
                                    {{ $roomData['totalRooms'] }} {{ __('admin.rooms') }}
                                </span>
                                <span style="display: flex; align-items: center; gap: 4px;">
                                    <x-heroicon-o-calendar style="width: 12px; height: 12px;" />
                                    {{ $roomData['occupiedToday'] }} {{ __('admin.occupied') }}
                                </span>
                            </p>
                        </td>

                        {{-- Date Cells with Booking Blocks --}}
                        <td colspan="{{ $colCount }}" class="relative border-b border-gray-200 p-0 dark:border-gray-700"
                            style="height: {{ $rowHeight }}px;"
                            x-bind:style="expanded ? 'height: {{ $positions->count() * 32 + 16 }}px' : 'height: {{ $rowHeight }}px'">

                            {{-- Column backgrounds --}}
                            <div style="display: flex; position: absolute; inset: 0;">
                                @foreach ($dateColumns as $col)
                                <div class="flex-1 border-r border-gray-100 dark:border-gray-800" style="{{ $col['isToday'] ? 'background-color: var(--brand-primary-light);' : '' }}"></div>
                                @endforeach
                            </div>

                            {{-- Booking Blocks --}}
                            @foreach ($positions as $idx => $pos)
                            <div
                                wire:click="openBookingModal({{ $pos['bookingId'] }})"
                                x-show="{{ $hasOverflow ? 'expanded || ' . $idx . ' < ' . $maxVisible : 'true' }}"
                                class="absolute cursor-pointer truncate text-xs font-medium transition hover:opacity-75"
                                style="
                                    top: {{ $idx * 32 + 6 }}px;
                                    left: calc({{ ($pos['start'] - 1) / $colCount * 100 }}% + 2px);
                                    width: calc({{ ($pos['end'] - $pos['start']) / $colCount * 100 }}% - 4px);
                                    height: 26px;
                                    line-height: 26px;
                                    padding: 0 8px;
                                    border-radius: 0;
                                    {{ match($pos['status']) {
                                        'confirmed'  => 'background-color: #E8F1FD; color: #000000; border-left: 3px solid #1A73E8;',
                                        'checked_in' => 'background-color: #E5FAEF; color: #000000; border-left: 3px solid #20B364;',
                                        'completed'  => 'background-color: #EDEDED; color: #000000; border-left: 3px solid #555555;',
                                        default      => 'background-color: #EDEDED; color: #000000; border-left: 3px solid #555555;',
                                    } }}
                                ">
                                {{ $pos['guestName'] }}
                            </div>
                            @endforeach

                            {{-- +X more --}}
                            @if ($hasOverflow)
                            <div
                                x-show="!expanded"
                                x-on:click="expanded = true"
                                class="absolute cursor-pointer text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                                style="top: {{ $maxVisible * 32 + 6 }}px; left: 10px;">
                                +{{ $positions->count() - $maxVisible }} {{ __('admin.more') }}
                            </div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{-- Hides scrollbar under the fixed first column --}}
        <div class="pointer-events-none absolute bottom-0 left-0 z-20 bg-[#F7F7F7] dark:bg-gray-800" style="width: 230px; height: 16px;"></div>
    </div>

    {{-- Legend (fixed above footer, starts after sidebar) --}}
    <div
        x-data="{
            left: 0,
            bottom: 0,
            footerHeight: 0,
            sidebarObserver: null,
            footerObserver: null,
            resizeHandler: null,
            update() {
                const sidebar = document.querySelector('.fi-sidebar');
                this.left = sidebar ? sidebar.offsetWidth : 0;
                const footer = document.getElementById('panel-footer');
                if (footer) {
                    footer.style.position = 'fixed';
                    footer.style.width = 'auto';
                    footer.style.left = this.left + 'px';
                    footer.style.right = '0';
                    footer.style.bottom = '0';
                    footer.style.zIndex = '39';
                    this.footerHeight = footer.offsetHeight;
                }
                this.bottom = this.footerHeight;
                document.documentElement.style.setProperty('--cal-bottom-space', (52 + this.footerHeight) + 'px');
            },
            destroy() {
                if (this.sidebarObserver) { this.sidebarObserver.disconnect(); this.sidebarObserver = null; }
                if (this.footerObserver) { this.footerObserver.disconnect(); this.footerObserver = null; }
                if (this.resizeHandler) { window.removeEventListener('resize', this.resizeHandler); this.resizeHandler = null; }
                const footer = document.getElementById('panel-footer');
                if (footer) {
                    footer.style.position = '';
                    footer.style.width = '';
                    footer.style.left = '';
                    footer.style.right = '';
                    footer.style.bottom = '';
                    footer.style.zIndex = '';
                }
                document.documentElement.style.removeProperty('--cal-bottom-space');
            }
        }"
        x-init="
            update();
            const sidebar = document.querySelector('.fi-sidebar');
            if (sidebar) { sidebarObserver = new ResizeObserver(() => update()); sidebarObserver.observe(sidebar); }
            const footer = document.getElementById('panel-footer');
            if (footer) { footerObserver = new ResizeObserver(() => update()); footerObserver.observe(footer); }
            resizeHandler = () => update();
            window.addEventListener('resize', resizeHandler);
        "
        x-on:livewire:navigating.window="destroy()"
        :style="`left: ${left}px; bottom: ${bottom}px`"
        class="cal-legend-bar fixed right-0 z-40 flex items-center gap-7 border-l border-t-2 border-gray-200 bg-white px-6 py-2 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
            <span class="h-3 w-3 rounded-full" style="background-color: #1A73E8;"></span>
            {{ __('admin.confirmed_bookings') }}
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
            <span class="h-3 w-3 rounded-full" style="background-color: #20B364;"></span>
            {{ __('admin.guest_checked_in') }}
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
            <span class="h-3 w-3 rounded-full" style="background-color: #555555;"></span>
            {{ __('admin.completed_bookings') }}
        </div>
    </div>
    {{-- Spacer to prevent content hiding behind the fixed legend + footer --}}
    <div class="cal-legend-spacer" style="height: var(--cal-bottom-space, 52px);"></div>
    @endif {{-- end calendar view --}}

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- ROOM GRID VIEW                                                 --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    @if ($activeView === 'grid')
    @php
    $roomGridFloors = $this->getRoomGridFloors();
    $roomTypeOptions = $this->getPropertyRoomOptions();
    @endphp

    {{-- Controls Container --}}
    <div class="flex flex-wrap items-center gap-4 rounded-2xl border border-gray-200 bg-white p-6">
        {{-- Left: Search --}}
        <div class="relative" style="width: 450px; max-width: 100%;">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
            </div>
            <input
                type="text"
                wire:model.live="search"
                placeholder="{{ __('admin.search_by_guest_name') }}"
                class="block w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-4 text-sm text-gray-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" />
        </div>
        {{-- Right: Room Type + Today + Date --}}
        <div class="ml-auto flex flex-wrap items-center gap-3">
            <select
                wire:model.live="gridRoomTypeFilter"
                class="rounded-lg border border-gray-700 bg-white px-3 py-2 text-sm text-gray-700"
                style="width: 200px;">
                <option value="">{{ __('admin.all_room_types') }}</option>
                @foreach ($roomTypeOptions as $roomTypeId => $roomTypeName)
                <option value="{{ $roomTypeId }}">{{ $roomTypeName }}</option>
                @endforeach
            </select>
            <button
                wire:click="goToToday"
                class="rounded-lg border border-gray-700 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                {{ __('admin.today') }}
            </button>
            <input
                type="date"
                wire:model.live="startDate"
                class="rounded-lg border border-gray-700 bg-white px-3 py-2 text-sm text-gray-700"
                style="width: 200px;" />
            <span class="text-sm text-gray-400">→</span>
            <input
                type="date"
                wire:model.live="endDate"
                min="{{ $startDate }}"
                placeholder="{{ __('admin.end_date_optional') }}"
                class="rounded-lg border border-gray-700 bg-white px-3 py-2 text-sm text-gray-700"
                style="width: 200px;" />
        </div>
    </div>

    {{-- Reservation Notes: active bookings summary per room type for the selected date range --}}
    @php $reservationNotes = $this->getRoomTypeReservationNotes(); @endphp
    @if ($reservationNotes->isNotEmpty())
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
        <div class="flex items-center gap-2 shrink-0 self-center">
            <x-heroicon-o-information-circle class="h-4 w-4 text-amber-600 shrink-0" />
            <span class="text-sm font-medium text-amber-800 leading-none">{{ __('admin.reserved_rooms_note') }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($reservationNotes as $note)
            <div class="flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-3 py-1.5 text-sm">
                <span class="font-medium text-gray-900">{{ $note['name'] }}</span>
                @if ($note['confirmed'] > 0)
                <span class="text-gray-300">·</span>
                <span class="text-amber-700">{{ $note['confirmed'] }} {{ __('admin.confirmed') }}</span>
                @endif
                @if ($note['checked_in'] > 0)
                <span class="text-gray-300">·</span>
                <span class="text-blue-700">{{ $note['checked_in'] }} {{ __('admin.checked_in_label') }}</span>
                @endif
                <span class="text-gray-300">·</span>
                <span class="text-green-700">{{ $note['available'] }} {{ __('admin.available') }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- No floors configured --}}
    @if ($roomGridFloors->isEmpty())
    <div class="flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-gray-200 bg-white py-16 text-center">
        <x-heroicon-o-building-office-2 class="h-8 w-8 text-gray-400" />
        <p class="text-sm font-medium text-gray-500">{{ __('admin.no_floors_configured') }}</p>
        <a
            href="{{ \App\Filament\Partner\Pages\PartnerRoomManage::getUrl() }}"
            wire:navigate
            class="text-xs font-medium text-primary-600 hover:underline">
            {{ __('admin.go_to_room_inventory') }} →
        </a>
    </div>
    @else

    {{-- Floor Sections --}}
    <div class="space-y-4">
        @foreach ($roomGridFloors as $floorData)
        @php
        $floor = $floorData['floor'];
        $rooms = $floorData['rooms'];
        $availableCount = $rooms->filter(fn ($r) => ! $r['isBooked'] && ! $r['isInactive'])->count();
        $occupiedCount = $rooms->filter(fn ($r) => $r['isBooked'])->count();
        @endphp
        <div class="flex flex-col gap-6 rounded-2xl border border-gray-200 bg-white p-4">

            {{-- Floor Header --}}
            <div class="flex items-center gap-3 rounded-xl bg-gray-100 p-3">
                <span class="flex-1 truncate text-lg font-semibold text-gray-950">{{ $floor->name }}</span>
                <div class="flex shrink-0 items-center gap-3 text-sm text-gray-600">
                    <span class="flex items-center gap-1.5">
                        <x-heroicon-o-home class="h-4 w-4" />
                        {{ $availableCount }} {{ __('admin.rooms_available_stat') }}
                    </span>
                    <span class="h-1 w-1 shrink-0 rounded-full bg-gray-400"></span>
                    <span class="flex items-center gap-1.5">
                        <x-heroicon-o-calendar-days class="h-4 w-4" />
                        {{ $occupiedCount }} {{ __('admin.occupied_rooms_stat') }}
                    </span>
                </div>
            </div>

            {{-- Room Cards --}}
            <div class="grid gap-3.5" style="grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));">
                @foreach ($rooms as $roomData)
                @php
                $room = $roomData['room'];
                $roomTypeName = $room->propertyRoom?->roomType?->name ?? '—';
                @endphp

                @if ($roomData['isInactive'])
                <div
                    class="flex flex-col items-center justify-between rounded-xl border border-gray-200 bg-gray-50/80 p-3 opacity-60 transition"
                    style="min-height: 124px; cursor: not-allowed;"
                    title="{{ __('admin.inactive') }}">
                    <div class="flex w-full flex-col items-center text-center">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ __('admin.room_no') }}</span>
                        <span class="mt-0.5 text-2xl font-bold tracking-tight text-gray-500 leading-tight">{{ $room->room_number }}</span>
                    </div>
                    <div class="w-full mt-2 rounded-lg bg-gray-200/60 px-2 py-1 text-center">
                        <p class="text-xs font-medium text-gray-500 line-clamp-2 leading-tight" title="{{ $roomTypeName }}">{{ $roomTypeName }}</p>
                    </div>
                </div>
                @elseif ($roomData['isBooked'])
                <button
                    type="button"
                    wire:click="openBookingModalFromRoom({{ $room->id }})"
                    class="group flex flex-col items-center justify-between rounded-xl border border-red-200 bg-[#FFF5F5] p-3 shadow-xs transition hover:border-red-300 hover:shadow-sm"
                    style="min-height: 124px;"
                    title="{{ __('admin.room_occupied') }}">
                    <div class="flex w-full flex-col items-center text-center">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-red-400">{{ __('admin.room_no') }}</span>
                        <span class="mt-0.5 text-2xl font-bold tracking-tight text-red-950 leading-tight">{{ $room->room_number }}</span>
                    </div>
                    <div class="w-full mt-2 rounded-lg bg-red-100/70 px-2 py-1 text-center">
                        <p class="text-xs font-medium text-red-800 line-clamp-2 leading-tight" title="{{ $roomTypeName }}">{{ $roomTypeName }}</p>
                    </div>
                </button>
                @else
                <div
                    class="group flex flex-col items-center justify-between rounded-xl border border-gray-200 bg-white p-3 shadow-xs transition hover:border-gray-300 hover:shadow-sm"
                    style="min-height: 124px;"
                    title="{{ __('admin.available') }}">
                    <div class="flex w-full flex-col items-center text-center">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ __('admin.room_no') }}</span>
                        <span class="mt-0.5 text-2xl font-bold tracking-tight text-gray-900 leading-tight">{{ $room->room_number }}</span>
                    </div>
                    <div class="w-full mt-2 rounded-lg bg-gray-50 px-2 py-1 text-center">
                        <p class="text-xs font-medium text-gray-600 line-clamp-2 leading-tight" title="{{ $roomTypeName }}">{{ $roomTypeName }}</p>
                    </div>
                </div>
                @endif
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    @endif {{-- end roomGridFloors check --}}

    {{-- Legend wrapper: x-data drives both the white backdrop and the floating card --}}
    <div
        x-data="{
            sidebarLeft: 0,
            left: 24,
            bottom: 8,
            footerHeight: 0,
            sidebarObserver: null,
            footerObserver: null,
            resizeHandler: null,
            update() {
                const sidebar = document.querySelector('.fi-sidebar');
                const sw = sidebar ? sidebar.offsetWidth : 0;
                this.sidebarLeft = sw;
                this.left = sw + 24;
                const footer = document.getElementById('panel-footer');
                if (footer) {
                    footer.style.position = 'fixed';
                    footer.style.width = 'auto';
                    footer.style.left = sw + 'px';
                    footer.style.right = '0';
                    footer.style.bottom = '0';
                    footer.style.zIndex = '39';
                    this.footerHeight = footer.offsetHeight;
                }
                this.bottom = this.footerHeight + 8;
                document.documentElement.style.setProperty('--cal-bottom-space', (68 + this.footerHeight) + 'px');
            },
            destroy() {
                if (this.sidebarObserver) { this.sidebarObserver.disconnect(); this.sidebarObserver = null; }
                if (this.footerObserver) { this.footerObserver.disconnect(); this.footerObserver = null; }
                if (this.resizeHandler) { window.removeEventListener('resize', this.resizeHandler); this.resizeHandler = null; }
                const footer = document.getElementById('panel-footer');
                if (footer) {
                    footer.style.position = '';
                    footer.style.width = '';
                    footer.style.left = '';
                    footer.style.right = '';
                    footer.style.bottom = '';
                    footer.style.zIndex = '';
                }
                document.documentElement.style.removeProperty('--cal-bottom-space');
            }
        }"
        x-init="
            update();
            const sidebar = document.querySelector('.fi-sidebar');
            if (sidebar) { sidebarObserver = new ResizeObserver(() => update()); sidebarObserver.observe(sidebar); }
            const footer = document.getElementById('panel-footer');
            if (footer) { footerObserver = new ResizeObserver(() => update()); footerObserver.observe(footer); }
            resizeHandler = () => update();
            window.addEventListener('resize', resizeHandler);
        "
        x-on:livewire:navigating.window="destroy()">
        {{-- White backdrop: covers the full bottom strip (behind legend card + footer) so content cannot show through --}}
        <div class="grid-legend-backdrop fixed z-[38] bg-white" :style="`left: ${sidebarLeft}px; right: 0; bottom: 0; height: var(--cal-bottom-space, 68px);`"></div>
        {{-- Legend card --}}
        <div
            :style="`left: ${left}px; right: 24px; bottom: ${bottom}px`"
            class="grid-legend-bar fixed z-40 flex flex-wrap items-center gap-8 rounded-2xl border border-gray-200 bg-white px-6 py-4 shadow-[0_-4px_15px_rgba(0,0,0,0.05)]">
            <div class="flex items-center gap-2">
                <div class="h-3 w-3 shrink-0 rounded-full border border-gray-300 bg-white"></div>
                <span class="text-sm text-gray-950">{{ __('admin.available_rooms_legend') }}</span>
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
    {{-- Spacer to prevent content hiding behind the fixed bottom area --}}
    <div class="grid-legend-spacer" style="height: var(--cal-bottom-space, 68px);"></div>

    @endif {{-- end grid view --}}

    @endif {{-- end property check --}}

    <x-filament-actions::modals />
</x-filament-panels::page>
