<x-filament-panels::page>
    <style>
        @media (max-width: 1023px) {
            .charts-row {
                grid-template-columns: 1fr !important;
            }

            .rooms-booked-header {
                flex-wrap: wrap;
                gap: 10px;
            }

            .rooms-booked-header-controls {
                width: 100%;
                flex-wrap: wrap;
            }

            .section-header-mobile {
                flex-wrap: wrap;
                gap: 10px;
            }

            .section-header-mobile-select {
                width: 100%;
            }
        }
    </style>
    <div class="space-y-6">
        {{-- Welcome Header --}}
        <div>
            <h2 style="font-size: 20px; font-weight: 700; color: #0D0E0D; margin: 0; line-height: 28px;">
                {{ __('admin.welcome') }}, {{ $userName }}
            </h2>
            <p style="font-size: 14px; color: #555555; margin: 4px 0 0 0;">
                {{ $greeting }}
            </p>
        </div>

        {{-- Property Overview Bar --}}
        <div style="background-color: #F0F6FF; border-radius: 12px; padding: 16px;">
            <p style="font-size: 14px; font-weight: 500; color: #64748B; margin: 0 0 16px 4px;">
                {{ __('admin.property_overview_for') }} {{ $todayDate }}
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Today Check-In --}}
                <div style="background-color: white; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 0;">{{ __('admin.today_check_in') }}</p>
                        <p style="font-size: 24px; font-weight: 700; color: #0F172A; margin: 0;">{{ $todayCheckIn }}</p>
                    </div>
                    <div style="background-color: #3B82F6; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <x-phosphor-calendar-check-fill style="width: 24px; height: 24px; color: white;" />
                    </div>
                </div>

                {{-- Today Check-Out --}}
                <div style="background-color: white; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 0;">{{ __('admin.today_check_out') }}</p>
                        <p style="font-size: 24px; font-weight: 700; color: #0F172A; margin: 0;">{{ $todayCheckOut }}</p>
                    </div>
                    <div style="background-color: #3B82F6; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <x-phosphor-calendar-x-fill style="width: 24px; height: 24px; color: white;" />
                    </div>
                </div>

                {{-- Total Rooms --}}
                <div style="background-color: white; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 0;">{{ __('admin.total_rooms') }}</p>
                        <p style="font-size: 24px; font-weight: 700; color: #0F172A; margin: 0;">{{ $totalRooms }}</p>
                    </div>
                    <div style="background-color: #3B82F6; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <x-phosphor-bed-fill style="width: 24px; height: 24px; color: white;" />
                    </div>
                </div>

                {{-- Today Occupied Rooms --}}
                <div style="background-color: white; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 0;">{{ __('admin.today_occupied_rooms') }}</p>
                        <p style="font-size: 24px; font-weight: 700; color: #0F172A; margin: 0;">{{ $todayOccupied }}</p>
                    </div>
                    <div style="background-color: #3B82F6; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <x-phosphor-user-check-fill style="width: 24px; height: 24px; color: white;" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts Row: Booking Overview + Platform Usage --}}
        <div class="charts-row" style="display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 16px;">
            {{-- Booking Overview Chart --}}
            <div style="background-color: white; border-radius: 12px; padding: 16px; border: 1px solid #F1F5F9; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background-color: #F7F7F7; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center;">
                            <x-phosphor-receipt style="width: 24px; height: 24px; color: #555555;" />
                        </div>
                        <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.booking_overview') }}</h3>
                    </div>
                    <div>
                        <select wire:model.live="bookingOverviewFilter" style="border: 1px solid #E2E8F0; border-radius: 6px; padding: 4px 8px; font-size: 13px; color: #64748B; background-color: white; cursor: pointer; outline: none;">
                            <option value="last_7_days">{{ __('admin.last_7_days') }}</option>
                            <option value="last_30_days">{{ __('admin.last_30_days') }}</option>
                            <option value="this_month">{{ __('admin.this_month') }}</option>
                            <option value="this_year">{{ __('admin.this_year') }}</option>
                        </select>
                    </div>
                </div>
                <div class="dashboard-chart-embed">
                    @livewire(\App\Filament\Widgets\BookingOverviewChart::class, ['timeFilter' => $bookingOverviewFilter], key('booking-overview-'.$bookingOverviewFilter))
                </div>
            </div>

            {{-- Platform Usage Chart --}}
            <div style="background-color: white; border-radius: 12px; padding: 16px; border: 1px solid #F1F5F9; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background-color: #F7F7F7; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center;">
                            <x-phosphor-devices style="width: 24px; height: 24px; color: #555555;" />
                        </div>
                        <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.platform_usage') }}</h3>
                    </div>
                    <div>
                        <select wire:model.live="platformUsageFilter" style="border: 1px solid #E2E8F0; border-radius: 6px; padding: 4px 8px; font-size: 13px; color: #64748B; background-color: white; cursor: pointer; outline: none;">
                            <option value="last_7_days">{{ __('admin.last_7_days') }}</option>
                            <option value="last_30_days">{{ __('admin.last_30_days') }}</option>
                            <option value="this_month">{{ __('admin.this_month') }}</option>
                            <option value="this_year">{{ __('admin.this_year') }}</option>
                        </select>
                    </div>
                </div>

                {{-- Doughnut chart with center text --}}
                <div class="dashboard-chart-embed" style="position: relative;">
                    @livewire(\App\Filament\Widgets\PlatformUsageChart::class, ['timeFilter' => $platformUsageFilter], key('platform-usage-'.$platformUsageFilter))
                    {{-- Center text overlay --}}
                    <div style="position: absolute; top: 55%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                        <p style="font-size: 28px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ number_format($platformWebCount + $platformAppCount) }}</p>
                        <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 4px 0 0 0; line-height: 1;">{{ __('admin.total_users') }}</p>
                    </div>
                </div>

                {{-- Web & App stat boxes --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                    <div style="background-color: #F0F6FF; border-radius: 10px; padding: 12px 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;">
                        <p style="font-size: 20px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ $platformWebCount }}</p>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: #FF829D;"></span>
                            <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 0; line-height: 1;">{{ __('admin.web') }}</p>
                        </div>
                    </div>
                    <div style="background-color: #F0F6FF; border-radius: 10px; padding: 12px 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;">
                        <p style="font-size: 20px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ $platformAppCount }}</p>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: #AD85FF;"></span>
                            <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 0; line-height: 1;">{{ __('admin.app') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- Rooms Booked & Revenue Chart --}}
        <div style="background-color: white; border-radius: 12px; padding: 16px; border: 1px solid #F1F5F9; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
            <div class="rooms-booked-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="border: 1px solid #E2E8F0; border-radius: 6px; padding: 6px; display: flex; align-items: center; justify-content: center;">
                        <x-phosphor-money-wavy style="width: 14px; height: 14px; color: #64748B;" />
                    </div>
                    <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.rooms_booked_and_revenue') }}</h3>
                </div>
                <div class="rooms-booked-header-controls" style="display: flex; align-items: center; gap: 16px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: #9BEDC1;"></span>
                        <span style="font-size: 13px; color: #64748B;">{{ __('admin.revenue') }}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: #EFAEAF;"></span>
                        <span style="font-size: 13px; color: #64748B;">{{ __('admin.rooms_booked') }}</span>
                    </div>
                    <select wire:model.live="roomsBookedFilter" style="border: 1px solid #E2E8F0; border-radius: 6px; padding: 4px 8px; font-size: 13px; color: #64748B; background-color: white; cursor: pointer; outline: none;">
                        <option value="last_7_days">{{ __('admin.last_7_days') }}</option>
                        <option value="last_30_days">{{ __('admin.last_30_days') }}</option>
                        <option value="this_month">{{ __('admin.this_month') }}</option>
                        <option value="this_year">{{ __('admin.this_year') }}</option>
                    </select>
                </div>
            </div>
            <div class="dashboard-chart-embed">
                @livewire(\App\Filament\Widgets\RoomsBookedRevenueChart::class, ['timeFilter' => $roomsBookedFilter], key('rooms-booked-'.$roomsBookedFilter))
            </div>
        </div>

        {{-- Summary Stat Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Total Bookings --}}
            <div style="background-color: #E8F1FD; border-radius: 16px; padding: 16px; outline: 1px solid #D3EAFD; outline-offset: -1px; display: flex; flex-direction: column; gap: 24px;">
                <div style="background-color: white; border-radius: 8px; padding: 12px; display: inline-flex; align-items: center; justify-content: center; align-self: flex-start;">
                    <x-phosphor-ticket style="width: 24px; height: 24px; color: #2196F3;" />
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <p style="font-size: 16px; font-weight: 400; color: #555555; margin: 0; line-height: 24px;">{{ __('admin.total_bookings') }}</p>
                    <p style="font-size: 24px; font-weight: 600; color: #0D0E0D; margin: 0; line-height: 32px;">{{ number_format($totalBookings) }}</p>
                </div>
            </div>

            {{-- Total Revenue Amount --}}
            <div style="background-color: #E5FAEF; border-radius: 16px; padding: 16px; outline: 1px solid #CBF6DF; outline-offset: -1px; display: flex; flex-direction: column; gap: 24px;">
                <div style="background-color: white; border-radius: 8px; padding: 12px; display: inline-flex; align-items: center; justify-content: center; align-self: flex-start;">
                    <x-phosphor-currency-circle-dollar style="width: 24px; height: 24px; color: #20B364;" />
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <p style="font-size: 16px; font-weight: 400; color: #555555; margin: 0; line-height: 24px;">{{ __('admin.total_revenue_amount') }}</p>
                    <p style="font-size: 24px; font-weight: 600; color: #0D0E0D; margin: 0; line-height: 32px;">{{ $totalRevenue }}</p>
                </div>
            </div>

            {{-- Total Refund Amount --}}
            <div style="background-color: #FEF5E6; border-radius: 16px; padding: 16px; outline: 1px solid #FDECD3; outline-offset: -1px; display: flex; flex-direction: column; gap: 24px;">
                <div style="background-color: white; border-radius: 8px; padding: 12px; display: inline-flex; align-items: center; justify-content: center; align-self: flex-start;">
                    <x-phosphor-hand-coins style="width: 24px; height: 24px; color: #2196F3;" />
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <p style="font-size: 16px; font-weight: 400; color: #555555; margin: 0; line-height: 24px;">{{ __('admin.total_refund_amount') }}</p>
                    <p style="font-size: 24px; font-weight: 600; color: #0D0E0D; margin: 0; line-height: 32px;">{{ $totalRefund }}</p>
                </div>
            </div>

            {{-- Total Active Customers --}}
            <div style="background-color: #FBEAEA; border-radius: 16px; padding: 16px; outline: 1px solid #F7D4D5; outline-offset: -1px; display: flex; flex-direction: column; gap: 24px;">
                <div style="background-color: white; border-radius: 8px; padding: 12px; display: inline-flex; align-items: center; justify-content: center; align-self: flex-start;">
                    <x-phosphor-user style="width: 24px; height: 24px; color: #D63031;" />
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <p style="font-size: 16px; font-weight: 400; color: #555555; margin: 0; line-height: 24px;">{{ __('admin.total_active_customers') }}</p>
                    <p style="font-size: 24px; font-weight: 600; color: #0D0E0D; margin: 0; line-height: 32px;">{{ $totalActiveCustomers }}</p>
                </div>
            </div>
        </div>
        {{-- Top Performing Rooms --}}
        <div style="background: white; border-radius: 16px; padding: 20px; border: 1px solid #E2E8F0;">
            <div class="section-header-mobile" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="background-color: #F1F5F9; border-radius: 8px; padding: 8px; display: inline-flex;">
                        <x-phosphor-building-apartment style="width: 20px; height: 20px; color: #475569;" />
                    </div>
                    <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.top_performing_rooms') }}</h3>
                </div>
                <div class="section-header-mobile-select">
                    <select wire:model.live="topRoomsFilter" style="border: 1px solid #E2E8F0; border-radius: 6px; padding: 4px 8px; font-size: 13px; color: #64748B; background-color: white; cursor: pointer; outline: none;">
                        <option value="last_7_days">{{ __('admin.last_7_days') }}</option>
                        <option value="last_30_days">{{ __('admin.last_30_days') }}</option>
                        <option value="this_month">{{ __('admin.this_month') }}</option>
                        <option value="this_year">{{ __('admin.this_year') }}</option>
                    </select>
                </div>
            </div>
            @livewire(\App\Filament\Widgets\TopPerformingRoomsChart::class, ['timeFilter' => $topRoomsFilter], key('top-rooms-'.$topRoomsFilter))
        </div>
        {{-- Recent Bookings --}}
        <div style="background: white; border-radius: 16px; padding: 20px; border: 1px solid #E2E8F0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="background-color: #F1F5F9; border-radius: 8px; padding: 8px; display: inline-flex;">
                        <x-phosphor-building-apartment style="width: 20px; height: 20px; color: #475569;" />
                    </div>
                    <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.recent_bookings') }}</h3>
                </div>
                <a href="{{ \App\Filament\Pages\AllBookingsManage::getUrl() }}"
                    wire:navigate
                    class="dashboard-view-all-btn"
                    style="display: inline-flex; align-items: center; gap: 4px; padding: 8px 16px; border: 1px solid var(--brand-primary); border-radius: 8px; color: var(--brand-primary); font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s;">
                    {{ __('admin.view_all_bookings') }}
                    <x-heroicon-m-arrow-right style="width: 16px; height: 16px;" />
                </a>
            </div>
            @livewire(\App\Filament\Widgets\RecentBookingsTable::class)
        </div>
    </div>
</x-filament-panels::page>