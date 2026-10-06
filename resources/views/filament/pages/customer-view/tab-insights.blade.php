@php
    $bookingData = $this->getBookingsByTime();
    $labels = $bookingData['labels'];
    $dataCounts = $bookingData['data'];
    $statusBreakdown = $this->getBookingStatusBreakdown();
    $mostBookedProperties = $this->getMostBookedProperties();
    $maxBookings = max(1, empty($dataCounts) ? 0 : max($dataCounts));
    $totalStatusBookings = max(1, array_sum($statusBreakdown));
@endphp

<div class="space-y-6">
    {{-- Filter and Exports Bar --}}
    <div class="insights-filter-bar" style="background: white; padding: 12px 16px; border-radius: 12px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); border: 1px solid #f3f4f6;">
        <div class="insights-filter-controls">
            <span style="font-size: 14px; font-weight: 600; color: #374151;">Filter :</span>
            <select wire:model.live="filterPeriod" style="border: 1px solid #d1d5db; border-radius: 8px; padding: 6px 12px; font-size: 13px; color: #4b5563; background: white; outline: none; min-width: 120px; cursor: pointer;">
                <option value="this_week">This Week</option>
                <option value="this_month">This Month</option>
                <option value="this_year">This Year</option>
                <option value="custom">Custom Date</option>
            </select>
            
            @if ($filterPeriod === 'custom')
                <div style="display: flex; gap: 8px;">
                    <input type="date" wire:model.live="customStartDate" style="border: 1px solid #d1d5db; border-radius: 8px; padding: 6px 12px; font-size: 13px; color: #4b5563; width: 130px; outline: none;" />
                    <input type="date" wire:model.live="customEndDate" style="border: 1px solid #d1d5db; border-radius: 8px; padding: 6px 12px; font-size: 13px; color: #4b5563; width: 130px; outline: none;" />
                </div>
            @else
                <div style="position: relative; opacity: 0.5; pointer-events: none;">
                    <input type="text" placeholder="Custom Date" disabled style="border: 1px solid #d1d5db; border-radius: 8px; padding: 6px 12px; font-size: 13px; color: #4b5563; width: 140px; outline: none; background: #f9fafb;" />
                    <x-heroicon-o-calendar style="width: 14px; height: 14px; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #9ca3af;" />
                </div>
            @endif
        </div>
        
        <button wire:click="exportInsights" style="display: flex; align-items: center; gap: 8px; background: #111827; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer;">
            <x-heroicon-o-arrow-down-tray style="width: 14px; height: 14px;" />
            Exports
            <x-heroicon-m-chevron-down style="width: 14px; height: 14px;" />
        </button>
    </div>

    {{-- Charts Grid --}}
    <div class="insights-charts-grid">
        {{-- Booking Over Time (Bar Chart) --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h4 style="font-size: 14px; font-weight: 700; color: #111827; margin: 0 0 24px 0; display: flex; align-items: center; gap: 8px;">
                <x-heroicon-o-chart-bar style="width: 18px; height: 18px;" />
                {{ __('admin.booking_over_time') }}
            </h4>

            <div style="display: flex; align-items: flex-end; gap: 8px; height: 200px; padding-bottom: 30px; position: relative;">
                {{-- Y-axis line --}}
                <div style="position: absolute; left: 0; bottom: 30px; top: 0; width: 1px; background: #e5e7eb;"></div>

                @foreach ($dataCounts as $idx => $count)
                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; position: relative;">
                        @if ($count > 0)
                            <div
                                style="
                                    width: 60%;
                                    height: {{ ($count / $maxBookings) * 100 }}%;
                                    min-height: 8px;
                                    background-color: #3b82f6;
                                    border-radius: 4px 4px 0 0;
                                    position: relative;
                                "
                                title="{{ $labels[$idx] }}: {{ $count }} bookings"
                            ></div>
                        @endif
                        @if (count($labels) > 15)
                            @if ($idx % 5 === 0 || $idx === count($labels) - 1)
                                <span style="font-size: 10px; color: #6b7280; margin-top: 8px; position: absolute; bottom: -24px;">{{ $labels[$idx] }}</span>
                            @endif
                        @else
                            <span style="font-size: 10px; color: #6b7280; margin-top: 8px; position: absolute; bottom: -24px;">{{ $labels[$idx] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Booking Status (Donut Chart) --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h4 style="font-size: 14px; font-weight: 700; color: #111827; margin: 0 0 24px 0; display: flex; align-items: center; gap: 8px;">
                <x-heroicon-o-chart-pie style="width: 18px; height: 18px;" />
                {{ __('admin.booking_status') }}
            </h4>

            {{-- Simple visual breakdown --}}
            <div style="display: flex; flex-direction: column; align-items: center; gap: 16px;">
                {{-- Circle --}}
                <div style="width: 160px; height: 160px; border-radius: 50%; position: relative; background: conic-gradient(
                    #22c55e 0% {{ round($statusBreakdown['completed'] / $totalStatusBookings * 100) }}%,
                    #ef4444 {{ round($statusBreakdown['completed'] / $totalStatusBookings * 100) }}% {{ round(($statusBreakdown['completed'] + $statusBreakdown['cancelled']) / $totalStatusBookings * 100) }}%,
                    #3b82f6 {{ round(($statusBreakdown['completed'] + $statusBreakdown['cancelled']) / $totalStatusBookings * 100) }}% 100%
                );">
                    <div style="position: absolute; inset: 30px; border-radius: 50%; background: white; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <span style="font-size: 11px; color: #6b7280;">{{ __('admin.total_booking') }}</span>
                        <span style="font-size: 22px; font-weight: 700; color: #111827;">{{ $totalStatusBookings }}</span>
                    </div>
                </div>

                {{-- Legend --}}
                <div style="display: flex; gap: 16px; font-size: 12px;">
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #22c55e;"></span>
                        {{ __('admin.completed_label') }} ({{ $statusBreakdown['completed'] }})
                    </div>
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #ef4444;"></span>
                        {{ __('admin.cancelled') }} ({{ $statusBreakdown['cancelled'] }})
                    </div>
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #3b82f6;"></span>
                        {{ __('admin.confirmed') }} ({{ $statusBreakdown['confirmed'] }})
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Most Booked Properties --}}
    @if (count($mostBookedProperties) > 0)
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h4 style="font-size: 14px; font-weight: 700; color: #111827; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
                <x-heroicon-o-building-office style="width: 18px; height: 18px;" />
                {{ __('admin.most_booked_properties') }}
            </h4>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                @foreach ($mostBookedProperties as $prop)
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; border-radius: 8px; border: 1px solid #f3f4f6;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 40px; height: 40px; border-radius: 8px; overflow: hidden; background: #e5e7eb; flex-shrink: 0;">
                                @if ($prop['image'])
                                    <img src="{{ asset('storage/' . $prop['image']) }}" style="width: 100%; height: 100%; object-fit: cover;" />
                                @else
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                        <x-heroicon-o-building-office style="width: 20px; height: 20px; color: #9ca3af;" />
                                    </div>
                                @endif
                            </div>
                            <div>
                                <p style="font-size: 13px; font-weight: 600; color: #111827; margin: 0;">{{ $prop['property_name'] }}</p>
                                <p style="font-size: 11px; color: #2563eb; font-weight: 500; margin: 2px 0 0 0;">ID - {{ str_pad((string) $prop['property_id'], 4, '0', STR_PAD_LEFT) }}</p>
                            </div>
                        </div>
                        <span style="font-size: 12px; font-weight: 600; color: #2563eb; background: #eff6ff; padding: 4px 10px; border-radius: 6px;">
                            {{ $prop['count'] }} {{ __('admin.times') }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
