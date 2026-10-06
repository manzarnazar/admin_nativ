<x-filament-panels::page>

    {{--
        No verification-status banner here: EnsurePartnerSetupComplete now keeps any
        partner who isn't Approved on /partner/setup, which shows that status instead
        (see resources/views/livewire/partner-setup-wizard.blade.php). A partner only
        ever reaches this dashboard once $isApproved is true.
    --}}
    @if ($isApproved && !$isSetupComplete)
        <div class="space-y-6">
            {{-- Header --}}
            <div>
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ __('admin.platform_setup_checklist') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('admin.complete_these_essential_steps_to_fully_configure_your_platform') }}
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full bg-[var(--brand-primary-light)] px-3 py-1 text-sm font-semibold text-[var(--brand-primary)]">
                        {{ $completedCount }} of {{ $totalTasks }} tasks completed
                    </span>
                </div>
            </div>

            {{-- Cards grid in gray container --}}
            <div class="rounded-2xl border border-[#ededed] bg-[#f7f7f7] p-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($checklistTasks as $task)
                        <div class="flex flex-col rounded-2xl border bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 {{ $task['isCompleted'] ? 'border-green-200 dark:border-green-800' : 'border-gray-200' }}">
                            {{-- Icon --}}
                            @php
                                $iconColor = $task['isCompleted'] ? 'text-green-600 dark:text-green-400' : 'text-[var(--brand-primary)]';
                                $bgColor = $task['isCompleted'] ? 'bg-green-50 dark:bg-green-900/20' : 'bg-[var(--brand-primary-light)]';
                            @endphp
                            <div class="mb-5 inline-flex items-center justify-center rounded-xl {{ $bgColor }} p-3" style="width: fit-content">
                                @switch($task['key'])
                                    @case('partner_profile')
                                        <svg class="h-6 w-6 {{ $iconColor }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                        </svg>
                                        @break
                                    @case('add_city')
                                        <svg class="h-6 w-6 {{ $iconColor }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                        </svg>
                                        @break
                                    @case('cancellation_policy')
                                        <svg class="h-6 w-6 {{ $iconColor }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        @break
                                @endswitch
                            </div>

                            {{-- Title & Description --}}
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                                {{ $task['label'] }}
                            </h3>
                            <p class="mt-2 text-base text-gray-500 dark:text-gray-400">
                                {{ $task['description'] }}
                            </p>

                            {{-- CTA --}}
                            <div class="mt-auto pt-6">
                                @if ($task['isCompleted'])
                                    <span class="inline-flex items-center gap-x-1.5 rounded-lg bg-green-50 px-3 py-2 text-sm font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        {{ __('admin.completed') }}
                                    </span>
                                @else
                                    <a
                                        href="{{ $task['route'] }}"
                                        @if ($task['route'] !== '#') wire:navigate @endif
                                        class="inline-flex items-center gap-x-1.5 rounded-lg bg-gray-950 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-black"
                                    >
                                        {{ $task['buttonLabel'] }}
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @elseif ($isApproved && $isSetupComplete)
        {{-- Full dashboard shown after setup complete --}}
        <style>
            @media (max-width: 1023px) {
                .partner-rooms-booked-header {
                    flex-wrap: wrap;
                    gap: 10px;
                }
                .partner-rooms-booked-header-controls {
                    width: 100%;
                    flex-wrap: wrap;
                }
                .partner-section-header-mobile {
                    flex-wrap: wrap;
                    gap: 10px;
                }
                .partner-section-header-mobile-select {
                    width: 100%;
                }
            }
        </style>

        <script>
            const numberCounterLogic = (valueString) => ({
                current: 0,
                target: 0,
                prefix: '',
                suffix: '',
                currentFormatted: valueString,
                
                init() {
                    const match = String(valueString).match(/^([^\d]*)(\d[,\d]*)(.*)$/);
                    if (match) {
                        this.prefix = match[1];
                        this.target = parseInt(match[2].replace(/,/g, ''), 10);
                        this.suffix = match[3];
                        this.currentFormatted = this.prefix + '0' + this.suffix;
                        this.animate();
                    }
                },
                animate() {
                    const duration = 1500;
                    let startTime = null;
                    
                    const step = (timestamp) => {
                        if (!startTime) startTime = timestamp;
                        const progress = Math.min((timestamp - startTime) / duration, 1);
                        const easeOut = 1 - Math.pow(1 - progress, 3);
                        this.current = Math.floor(easeOut * this.target);
                        
                        const numStr = new Intl.NumberFormat('en-IN').format(this.current);
                        this.currentFormatted = this.prefix + numStr + this.suffix;
                        
                        if (progress < 1) {
                            window.requestAnimationFrame(step);
                        } else {
                            this.currentFormatted = valueString;
                        }
                    };
                    window.requestAnimationFrame(step);
                }
            });

            if (window.Alpine) {
                window.Alpine.data('numberCounter', numberCounterLogic);
            } else {
                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('numberCounter', numberCounterLogic);
                });
            }

            // Intercept Chart.js to prevent Filament from globally disabling animations
            let chartInterval = setInterval(() => {
                if (window.Chart && window.Chart.defaults && window.Chart.defaults.animation) {
                    clearInterval(chartInterval);
                    let actualDuration = 2000;
                    Object.defineProperty(window.Chart.defaults.animation, 'duration', {
                        get: function() { return actualDuration; },
                        set: function(val) { 
                            // Ignore Filament's strict duration = 0 override
                            if (val !== 0) {
                                actualDuration = val;
                            }
                        }
                    });
                }
            }, 10);
        </script>

        <div class="space-y-4">
            {{-- Welcome Header --}}
            <div x-data="{
                greeting: '',
                init() {
                    const h = new Date().getHours();
                    if (h < 12) this.greeting = '{{ __('admin.good_morning') }}';
                    else if (h < 17) this.greeting = '{{ __('admin.good_afternoon') }}';
                    else this.greeting = '{{ __('admin.good_evening') }}';
                }
            }">
                <h2 style="font-size: 20px; font-weight: 700; color: #0D0E0D; margin: 0; line-height: 28px;">
                    {{ __('admin.welcome') }}, {{ $userName }}
                </h2>
                <p x-text="greeting" style="font-size: 14px; color: #555555; margin: 4px 0 0 0;"></p>
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
                            <p x-data="numberCounter('{{ $todayCheckIn }}')" x-text="currentFormatted" style="font-size: 24px; font-weight: 700; color: #0F172A; margin: 0;">{{ $todayCheckIn }}</p>
                        </div>
                        <div style="background-color: #3B82F6; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <x-phosphor-calendar-check-fill style="width: 24px; height: 24px; color: white;" />
                        </div>
                    </div>

                    {{-- Today Check-Out --}}
                    <div style="background-color: white; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 0;">{{ __('admin.today_check_out') }}</p>
                            <p x-data="numberCounter('{{ $todayCheckOut }}')" x-text="currentFormatted" style="font-size: 24px; font-weight: 700; color: #0F172A; margin: 0;">{{ $todayCheckOut }}</p>
                        </div>
                        <div style="background-color: #3B82F6; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <x-phosphor-calendar-x-fill style="width: 24px; height: 24px; color: white;" />
                        </div>
                    </div>

                    {{-- Total Rooms --}}
                    <div style="background-color: white; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 0;">{{ __('admin.total_rooms') }}</p>
                            <p x-data="numberCounter('{{ $totalRooms }}')" x-text="currentFormatted" style="font-size: 24px; font-weight: 700; color: #0F172A; margin: 0;">{{ $totalRooms }}</p>
                        </div>
                        <div style="background-color: #3B82F6; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <x-phosphor-bed-fill style="width: 24px; height: 24px; color: white;" />
                        </div>
                    </div>

                    {{-- Today Occupied Rooms --}}
                    <div style="background-color: white; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 0;">{{ __('admin.today_occupied_rooms') }}</p>
                            <p x-data="numberCounter('{{ $todayOccupied }}')" x-text="currentFormatted" style="font-size: 24px; font-weight: 700; color: #0F172A; margin: 0;">{{ $todayOccupied }}</p>
                        </div>
                        <div style="background-color: #3B82F6; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <x-phosphor-user-check-fill style="width: 24px; height: 24px; color: white;" />
                        </div>
                    </div>
                </div>
            </div>

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
                    @livewire(\App\Filament\Partner\Widgets\BookingOverviewChart::class, ['timeFilter' => $bookingOverviewFilter], key('partner-booking-overview-'.$bookingOverviewFilter))
                </div>
            </div>

            {{-- Rooms Booked & Revenue Chart --}}
            <div style="background-color: white; border-radius: 12px; padding: 16px; border: 1px solid #F1F5F9; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                <div class="partner-rooms-booked-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background-color: #F7F7F7; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center;">
                            <x-phosphor-money-wavy style="width: 24px; height: 24px; color: #555555;" />
                        </div>
                        <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.rooms_booked_and_revenue') }}</h3>
                    </div>
                    <div class="partner-rooms-booked-header-controls" style="display: flex; align-items: center; gap: 16px;">
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
                    @livewire(\App\Filament\Partner\Widgets\RoomsBookedRevenueChart::class, ['timeFilter' => $roomsBookedFilter], key('partner-rooms-booked-'.$roomsBookedFilter))
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
                        <p x-data="numberCounter('{{ number_format($totalBookings) }}')" x-text="currentFormatted" style="font-size: 24px; font-weight: 600; color: #0D0E0D; margin: 0; line-height: 32px;">{{ number_format($totalBookings) }}</p>
                    </div>
                </div>

                {{-- Total Revenue Amount --}}
                <div style="background-color: #E5FAEF; border-radius: 16px; padding: 16px; outline: 1px solid #CBF6DF; outline-offset: -1px; display: flex; flex-direction: column; gap: 24px;">
                    <div style="background-color: white; border-radius: 8px; padding: 12px; display: inline-flex; align-items: center; justify-content: center; align-self: flex-start;">
                        <x-phosphor-currency-circle-dollar style="width: 24px; height: 24px; color: #20B364;" />
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <p style="font-size: 16px; font-weight: 400; color: #555555; margin: 0; line-height: 24px;">{{ __('admin.total_revenue_amount') }}</p>
                        <p x-data="numberCounter('{{ $totalRevenue }}')" x-text="currentFormatted" style="font-size: 24px; font-weight: 600; color: #0D0E0D; margin: 0; line-height: 32px;">{{ $totalRevenue }}</p>
                    </div>
                </div>

                {{-- Total Refund Amount --}}
                <div style="background-color: #FEF5E6; border-radius: 16px; padding: 16px; outline: 1px solid #FDECD3; outline-offset: -1px; display: flex; flex-direction: column; gap: 24px;">
                    <div style="background-color: white; border-radius: 8px; padding: 12px; display: inline-flex; align-items: center; justify-content: center; align-self: flex-start;">
                        <x-phosphor-hand-coins style="width: 24px; height: 24px; color: #2196F3;" />
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <p style="font-size: 16px; font-weight: 400; color: #555555; margin: 0; line-height: 24px;">{{ __('admin.total_refund_amount') }}</p>
                        <p x-data="numberCounter('{{ $totalRefund }}')" x-text="currentFormatted" style="font-size: 24px; font-weight: 600; color: #0D0E0D; margin: 0; line-height: 32px;">{{ $totalRefund }}</p>
                    </div>
                </div>

                {{-- Total Active Customers --}}
                <div style="background-color: #FBEAEA; border-radius: 16px; padding: 16px; outline: 1px solid #F7D4D5; outline-offset: -1px; display: flex; flex-direction: column; gap: 24px;">
                    <div style="background-color: white; border-radius: 8px; padding: 12px; display: inline-flex; align-items: center; justify-content: center; align-self: flex-start;">
                        <x-phosphor-user style="width: 24px; height: 24px; color: #D63031;" />
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <p style="font-size: 16px; font-weight: 400; color: #555555; margin: 0; line-height: 24px;">{{ __('admin.total_active_customers') }}</p>
                        <p x-data="numberCounter('{{ $totalActiveCustomers }}')" x-text="currentFormatted" style="font-size: 24px; font-weight: 600; color: #0D0E0D; margin: 0; line-height: 32px;">{{ $totalActiveCustomers }}</p>
                    </div>
                </div>
            </div>

            {{-- Top Performing Rooms --}}
            <div style="background: white; border-radius: 16px; padding: 20px; border: 1px solid #E2E8F0;">
                <div class="partner-section-header-mobile" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="background-color: #F7F7F7; border-radius: 8px; padding: 12px; display: inline-flex; align-items: center; justify-content: center;">
                            <x-phosphor-building-apartment style="width: 24px; height: 24px; color: #555555;" />
                        </div>
                        <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.top_performing_rooms') }}</h3>
                    </div>
                    <div class="partner-section-header-mobile-select">
                        <select wire:model.live="topRoomsFilter" style="border: 1px solid #E2E8F0; border-radius: 6px; padding: 4px 8px; font-size: 13px; color: #64748B; background-color: white; cursor: pointer; outline: none;">
                            <option value="last_7_days">{{ __('admin.last_7_days') }}</option>
                            <option value="last_30_days">{{ __('admin.last_30_days') }}</option>
                            <option value="this_month">{{ __('admin.this_month') }}</option>
                            <option value="this_year">{{ __('admin.this_year') }}</option>
                        </select>
                    </div>
                </div>
                @livewire(\App\Filament\Partner\Widgets\TopPerformingRoomsChart::class, ['timeFilter' => $topRoomsFilter], key('partner-top-rooms-'.$topRoomsFilter))
            </div>

            {{-- Recent Bookings --}}
            <div style="background: white; border-radius: 16px; padding: 20px; border: 1px solid #E2E8F0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="background-color: #F7F7F7; border-radius: 8px; padding: 12px; display: inline-flex; align-items: center; justify-content: center;">
                            <x-phosphor-building-apartment style="width: 24px; height: 24px; color: #555555;" />
                        </div>
                        <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.recent_bookings') }}</h3>
                    </div>
                    <a href="{{ \App\Filament\Partner\Pages\PartnerBookingsManage::getUrl() }}"
                       wire:navigate
                       class="dashboard-view-all-btn"
                       style="display: inline-flex; align-items: center; gap: 4px; padding: 8px 16px; border: 1px solid var(--brand-primary); border-radius: 8px; color: var(--brand-primary); font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s;">
                        {{ __('admin.view_all_bookings') }}
                        <x-heroicon-m-arrow-right style="width: 16px; height: 16px;" />
                    </a>
                </div>
                @livewire(\App\Filament\Partner\Widgets\RecentBookingsTable::class)
            </div>
        </div>
    @endif
</x-filament-panels::page>
