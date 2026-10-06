<x-filament-panels::page>
    <style>
        @media (max-width: 1023px) {
            .saas-charts-row {
                grid-template-columns: 1fr !important;
            }

            .saas-section-header {
                flex-wrap: wrap;
                gap: 10px;
            }

            .saas-section-header-select {
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

    <div class="space-y-6">
        {{-- Welcome Header --}}
        <!-- <div>
            <h2 style="font-size: 20px; font-weight: 700; color: #0D0E0D; margin: 0; line-height: 28px;">
                {{ __('admin.welcome') }}, {{ $userName }}
            </h2>
            <p style="font-size: 14px; color: #555555; margin: 4px 0 0 0;">
                {{ $greeting }} &mdash; {{ $todayDate }}
            </p>
        </div> -->

        {{-- Platform Overview Section --}}
        <div>
            <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0 0 4px 0;">{{ __('admin.platform_overview') }}</h3>
            <p style="font-size: 13px; color: #94A3B8; margin: 0 0 16px 0;">{{ __('admin.high_level_platform_metrics') }}</p>

            {{-- Stat Cards Row 1 --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Total Booking --}}
                <div style="background-color: white; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; flex-direction: column; gap: 14px;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                        <div>
                            <p style="font-size: 14px; font-weight: 400; color: #64748B; margin: 0 0 8px 0;">{{ __('admin.total_booking') }}</p>
                            <p x-data="numberCounter('{{ $confirmedBookings }}')" x-text="currentFormatted" style="font-size: 28px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ $confirmedBookings }}</p>
                        </div>
                        <div style="background-color: #2563EB; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <x-phosphor-calendar-check-fill style="width: 24px; height: 24px; color: white;" />
                        </div>
                    </div>
                    <p style="font-size: 13px; color: #94A3B8; margin: 0;">{{ __('admin.confirmed_bookings') }}</p>
                </div>

                {{-- Total Revenue --}}
                <div style="background-color: white; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; flex-direction: column; gap: 14px;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                        <div>
                            <p style="font-size: 14px; font-weight: 400; color: #64748B; margin: 0 0 8px 0;">{{ __('admin.total_revenue') }}</p>
                            <p x-data="numberCounter('{{ $grossRevenue }}')" x-text="currentFormatted" style="font-size: 28px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ $grossRevenue }}</p>
                        </div>
                        <div style="background-color: #2563EB; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <x-phosphor-money-wavy-fill style="width: 24px; height: 24px; color: white;" />
                        </div>
                    </div>
                    <p style="font-size: 13px; color: #94A3B8; margin: 0;">{{ __('admin.gross_revenue') }}</p>
                </div>

                {{-- Net Revenue --}}
                <div style="background-color: white; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; flex-direction: column; gap: 14px;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                        <div>
                            <p style="font-size: 14px; font-weight: 400; color: #64748B; margin: 0 0 8px 0;">{{ __('admin.net_revenue') }}</p>
                            <p x-data="numberCounter('{{ $netRevenue }}')" x-text="currentFormatted" style="font-size: 28px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ $netRevenue }}</p>
                        </div>
                        <div style="background-color: #2563EB; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <x-phosphor-currency-circle-dollar-fill style="width: 24px; height: 24px; color: white;" />
                        </div>
                    </div>
                    <p style="font-size: 13px; color: #94A3B8; margin: 0;">{{ __('admin.net_revenue') }}</p>
                </div>

                {{-- Total Commission --}}
                <div style="background-color: white; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; flex-direction: column; gap: 14px;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                        <div>
                            <p style="font-size: 14px; font-weight: 400; color: #64748B; margin: 0 0 8px 0;">{{ __('admin.total_commission') }}</p>
                            <p x-data="numberCounter('{{ $platformEarnings }}')" x-text="currentFormatted" style="font-size: 28px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ $platformEarnings }}</p>
                        </div>
                        <div style="background-color: #2563EB; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <x-phosphor-seal-percent-fill style="width: 24px; height: 24px; color: white;" />
                        </div>
                    </div>
                    <p style="font-size: 13px; color: #94A3B8; margin: 0;">{{ __('admin.platform_earnings') }}</p>
                </div>
            </div>

            {{-- Stat Cards Row 2 --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                {{-- Refund Processed --}}
                <div style="background-color: white; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; flex-direction: column; gap: 14px;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                        <div>
                            <p style="font-size: 14px; font-weight: 400; color: #64748B; margin: 0 0 8px 0;">{{ __('admin.refund_processed') }}</p>
                            <p x-data="numberCounter('{{ $completedRefundsCount }}')" x-text="currentFormatted" style="font-size: 28px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ $completedRefundsCount }}</p>
                        </div>
                        <div style="background-color: #2563EB; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <x-phosphor-clock-countdown-fill style="width: 24px; height: 24px; color: white;" />
                        </div>
                    </div>
                    <p style="font-size: 13px; color: #94A3B8; margin: 0;">{{ __('admin.completed_refunds_label') }}: {{ $completedRefundsAmount }}</p>
                </div>

                {{-- Active Properties --}}
                <div style="background-color: white; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; flex-direction: column; gap: 14px;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                        <div>
                            <p style="font-size: 14px; font-weight: 400; color: #64748B; margin: 0 0 8px 0;">{{ __('admin.active_properties') }}</p>
                            <p x-data="numberCounter('{{ $liveListings }}')" x-text="currentFormatted" style="font-size: 28px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ $liveListings }}</p>
                        </div>
                        <div style="background-color: #2563EB; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <x-phosphor-buildings-fill style="width: 24px; height: 24px; color: white;" />
                        </div>
                    </div>
                    <p style="font-size: 13px; color: #94A3B8; margin: 0;">{{ __('admin.live_listing') }}</p>
                </div>

                {{-- Active Partners --}}
                <div style="background-color: white; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; flex-direction: column; gap: 14px;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                        <div>
                            <p style="font-size: 14px; font-weight: 400; color: #64748B; margin: 0 0 8px 0;">{{ __('admin.active_partners') }}</p>
                            <p x-data="numberCounter('{{ $verifiedPartners }}')" x-text="currentFormatted" style="font-size: 28px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ $verifiedPartners }}</p>
                        </div>
                        <div style="background-color: #2563EB; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <x-phosphor-users-four-fill style="width: 24px; height: 24px; color: white;" />
                        </div>
                    </div>
                    <p style="font-size: 13px; color: #94A3B8; margin: 0;">{{ __('admin.verified_partners') }}</p>
                </div>

                {{-- Active Customers --}}
                <div style="background-color: white; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; flex-direction: column; gap: 14px;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                        <div>
                            <p style="font-size: 14px; font-weight: 400; color: #64748B; margin: 0 0 8px 0;">{{ __('admin.active_customers') }}</p>
                            <p x-data="numberCounter('{{ $registeredUsers }}')" x-text="currentFormatted" style="font-size: 28px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ $registeredUsers }}</p>
                        </div>
                        <div style="background-color: #2563EB; border-radius: 10px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <x-phosphor-user-check-fill style="width: 24px; height: 24px; color: white;" />
                        </div>
                    </div>
                    <p style="font-size: 13px; color: #94A3B8; margin: 0;">{{ __('admin.registered_users') }}</p>
                </div>
            </div>
        </div>

        {{-- Booking Overview + Property Distribution side by side --}}
        <div class="saas-charts-row" style="display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 16px;">
            {{-- Booking Overview Chart --}}
            <div style="background-color: white; border-radius: 12px; padding: 16px; border: 1px solid #F1F5F9; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                <div class="saas-section-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background-color: #F7F7F7; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center;">
                            <x-phosphor-receipt style="width: 24px; height: 24px; color: #555555;" />
                        </div>
                        <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.booking_overview') }}</h3>
                    </div>
                    <div class="saas-section-header-select">
                        <select wire:model.live="saasBookingOverviewFilter" style="border: 1px solid #E2E8F0; border-radius: 6px; padding: 4px 8px; font-size: 13px; color: #64748B; background-color: white; cursor: pointer; outline: none;">
                            <option value="last_7_days">{{ __('admin.last_7_days') }}</option>
                            <option value="last_30_days">{{ __('admin.last_30_days') }}</option>
                            <option value="this_month">{{ __('admin.this_month') }}</option>
                            <option value="this_year">{{ __('admin.this_year') }}</option>
                        </select>
                    </div>
                </div>
                <div class="dashboard-chart-embed">
                    @livewire(\App\Filament\Widgets\BookingOverviewChart::class, ['timeFilter' => $saasBookingOverviewFilter, 'countryId' => $currentCountryId], key('saas-booking-overview-'.$saasBookingOverviewFilter.'-'.$currentCountryId))
                </div>
            </div>

            {{-- Property Distribution (Doughnut) --}}
            @livewire(\App\Livewire\SaasPropertyDistributionWidget::class, ['countryId' => $currentCountryId], key('saas-prop-dist-'.$currentCountryId))
        </div>

        {{-- Revenue & Earnings Chart --}}
        <div style="background-color: white; border-radius: 12px; padding: 16px; border: 1px solid #F1F5F9; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
            <div class="saas-section-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="background-color: #F7F7F7; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center;">
                        <x-phosphor-money-wavy style="width: 24px; height: 24px; color: #555555;" />
                    </div>
                    <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.revenue_earnings') }}</h3>
                </div>
                <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: #9BEDC1;"></span>
                        <span style="font-size: 13px; color: #64748B;">{{ __('admin.revenue') }}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: #EFAEAF;"></span>
                        <span style="font-size: 13px; color: #64748B;">{{ __('admin.refund') }}</span>
                    </div>
                    <div class="saas-section-header-select">
                        <select wire:model.live="saasRevenueFilter" style="border: 1px solid #E2E8F0; border-radius: 6px; padding: 4px 8px; font-size: 13px; color: #64748B; background-color: white; cursor: pointer; outline: none;">
                            <option value="last_7_days">{{ __('admin.last_7_days') }}</option>
                            <option value="last_30_days">{{ __('admin.last_30_days') }}</option>
                            <option value="this_month">{{ __('admin.this_month') }}</option>
                            <option value="this_year">{{ __('admin.this_year') }}</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="dashboard-chart-embed">
                @livewire(\App\Filament\Widgets\RevenueEarningsChart::class, ['timeFilter' => $saasRevenueFilter, 'countryId' => $currentCountryId], key('saas-revenue-'.$saasRevenueFilter.'-'.$currentCountryId))
            </div>
        </div>

        {{-- Top Performing Cities (Horizontal Bar) --}}
        <div style="background-color: white; border-radius: 12px; padding: 16px; border: 1px solid #F1F5F9; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
            <div class="saas-section-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="background-color: #F7F7F7; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center;">
                        <x-phosphor-building-apartment style="width: 24px; height: 24px; color: #555555;" />
                    </div>
                    <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.top_performing_cities') }}</h3>
                </div>
                <div class="saas-section-header-select">
                    <select wire:model.live="saasTopCitiesFilter" style="border: 1px solid #E2E8F0; border-radius: 6px; padding: 4px 8px; font-size: 13px; color: #64748B; background-color: white; cursor: pointer; outline: none;">
                        <option value="last_7_days">{{ __('admin.last_7_days') }}</option>
                        <option value="last_30_days">{{ __('admin.last_30_days') }}</option>
                        <option value="this_month">{{ __('admin.this_month') }}</option>
                        <option value="this_year">{{ __('admin.this_year') }}</option>
                    </select>
                </div>
            </div>
            <div class="dashboard-chart-embed">
                @livewire(\App\Filament\Widgets\TopPerformingCitiesChart::class, ['timeFilter' => $saasTopCitiesFilter, 'countryId' => $currentCountryId], key('saas-top-cities-'.$saasTopCitiesFilter.'-'.$currentCountryId))
            </div>
        </div>

        {{-- Platform Usage + Most Booked Properties side by side --}}
        <div class="saas-charts-row" style="display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); gap: 16px;">
            @livewire(\App\Livewire\SaasPlatformUsageWidget::class, key('saas-platform-usage-'.$currentCountryId))
            @livewire(\App\Livewire\MostBookedPropertiesWidget::class, ['countryId' => $currentCountryId], key('saas-most-booked-'.$currentCountryId))
        </div>

        {{-- Recent Bookings --}}
        <div style="background-color: white; border-radius: 12px; padding: 16px; border: 1px solid #F1F5F9; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="background-color: #F7F7F7; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center;">
                        <x-phosphor-building-apartment style="width: 24px; height: 24px; color: #555555;" />
                    </div>
                    <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.recent_bookings') }}</h3>
                </div>
                <a href="{{ \App\Filament\Pages\AllBookingsManage::getUrl() }}" wire:navigate style="font-size: 13px; color: #3B82F6; font-weight: 500; text-decoration: none;">
                    {{ __('admin.view_all') }}
                </a>
            </div>
            @livewire(\App\Filament\Widgets\RecentBookingsTable::class)
        </div>
    </div>
</x-filament-panels::page>