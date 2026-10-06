{{--
    Analytics tab body. Shared between admin (AllPropertiesView) and partner (PartnerPropertyView).
    Expects:
    - $analyticsStats: array{comparison_label, total_bookings, gross_revenue, partner_revenue, platform_earnings}
    - $revenueChartData: array{labels, partner_data, commission_data}
    - $bookingStatusData: array{total, completed, cancelled, refunded}
    - $commissionData: ?array{rate, is_overridden, lifetime_collected}  — null on partner view
    - $payoutData: array{total_pending, available_balance, total_withdrawn}
    - $showPlatformEarnings: bool  — admin sees 4 stat cards, partner sees 3
    - $showCommissionCard: bool     — admin only
    - $analyticsDatePreset: string  — bound to the page property via wire:model.live
    - $analyticsCustomDate: ?string
--}}
<div class="space-y-6">

    {{-- ── Filter bar ── --}}
    <div class="rounded-2xl border border-[#EDEDED] bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.filter') }} :</span>

                <select
                    wire:model.live="analyticsDatePreset"
                    class="rounded-xl border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                >
                    <option value="today">{{ __('admin.today') }}</option>
                    <option value="this_week">{{ __('admin.this_week') }}</option>
                    <option value="this_month">{{ __('admin.this_month') }}</option>
                    <option value="this_year">{{ __('admin.this_year') }}</option>
                </select>

                <input
                    type="date"
                    wire:model.live="analyticsCustomDate"
                    placeholder="{{ __('admin.custom_date') }}"
                    class="rounded-xl border border-gray-300 px-3 py-1.5 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                />
            </div>

            <x-filament-actions::group
                :actions="$this->getAnalyticsExportActions()"
                :label="__('admin.exports')"
                icon="heroicon-o-arrow-down-tray"
                color="dark"
                :button="true"
            />
        </div>
    </div>

    {{-- ── Stat cards ── --}}
    <div @class([
        'grid gap-4',
        'grid-cols-2 lg:grid-cols-4' => $showPlatformEarnings,
        'grid-cols-1 sm:grid-cols-3' => ! $showPlatformEarnings,
    ])>

        {{-- Total Bookings --}}
        @php $stat = $analyticsStats['total_bookings']; @endphp
        <div class="rounded-2xl border border-[#EDEDED] bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-start justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                    {!! svg('others.ticket', 'h-5 w-5')->toHtml() !!}
                </div>
                @if ($stat['change'] !== null)
                    <span @class([
                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $stat['change'] >= 0,
                        'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $stat['change'] < 0,
                    ])>
                        @if ($stat['change'] >= 0)
                            {!! svg('others.trendup2', 'h-3.5 w-3.5')->toHtml() !!}
                        @else
                            {!! svg('others.trenddown', 'h-3.5 w-3.5')->toHtml() !!}
                        @endif
                        {{ abs($stat['change']) }}%{{ $analyticsStats['comparison_label'] ? ' '.$analyticsStats['comparison_label'] : '' }}
                    </span>
                @endif
            </div>
            <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.total_bookings') }}</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $stat['subtitle'] }}</p>
        </div>

        {{-- Gross Revenue --}}
        @php $stat = $analyticsStats['gross_revenue']; @endphp
        <div class="rounded-2xl border border-[#EDEDED] bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-start justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-50 dark:bg-green-900/20">
                    {!! svg('others.currency', 'h-5 w-5')->toHtml() !!}
                </div>
                @if ($stat['change'] !== null)
                    <span @class([
                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $stat['change'] >= 0,
                        'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $stat['change'] < 0,
                    ])>
                        @if ($stat['change'] >= 0)
                            {!! svg('others.trendup2', 'h-3.5 w-3.5')->toHtml() !!}
                        @else
                            {!! svg('others.trenddown', 'h-3.5 w-3.5')->toHtml() !!}
                        @endif
                        {{ abs($stat['change']) }}%{{ $analyticsStats['comparison_label'] ? ' '.$analyticsStats['comparison_label'] : '' }}
                    </span>
                @endif
            </div>
            <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.gross_revenue') }}</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $stat['subtitle'] }}</p>
        </div>

        {{-- Partner Revenue --}}
        @php $stat = $analyticsStats['partner_revenue']; @endphp
        <div class="rounded-2xl border border-[#EDEDED] bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-start justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                    {!! svg('others.handcoins', 'h-5 w-5')->toHtml() !!}
                </div>
                @if ($stat['change'] !== null)
                    <span @class([
                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $stat['change'] >= 0,
                        'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $stat['change'] < 0,
                    ])>
                        @if ($stat['change'] >= 0)
                            {!! svg('others.trendup2', 'h-3.5 w-3.5')->toHtml() !!}
                        @else
                            {!! svg('others.trenddown', 'h-3.5 w-3.5')->toHtml() !!}
                        @endif
                        {{ abs($stat['change']) }}%{{ $analyticsStats['comparison_label'] ? ' '.$analyticsStats['comparison_label'] : '' }}
                    </span>
                @endif
            </div>
            <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.partner_revenue') }}</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $stat['subtitle'] }}</p>
        </div>

        {{-- Platform Earnings — admin only --}}
        @if ($showPlatformEarnings)
            @php $stat = $analyticsStats['platform_earnings']; @endphp
            <div class="rounded-2xl border border-[#EDEDED] bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-start justify-between">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 dark:bg-red-900/20">
                        {!! svg('others.percent', 'h-5 w-5')->toHtml() !!}
                    </div>
                    @if ($stat['previous_value'] !== null)
                        <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                            {{ $stat['previous_value'] }}{{ $analyticsStats['comparison_label'] ? ' '.$analyticsStats['comparison_label'] : '' }}
                        </span>
                    @endif
                </div>
                <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.platform_earnings') }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $stat['subtitle'] }}</p>
            </div>
        @endif
    </div>

    {{-- ── Charts row ── --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">

        {{-- Revenue Performance bar chart --}}
        {{-- No wire:key — Alpine persists and $watch reacts when $wire.revenueChartData is updated by the server --}}
        <div
            class="lg:col-span-3 rounded-2xl border border-[#EDEDED] bg-white p-6 dark:border-gray-700 dark:bg-gray-900"
            x-data="{
                chart: null,
                lastHash: '',
                init() {
                    const self = this;
                    function buildChart(d) {
                        const newHash = JSON.stringify(d);
                        if (newHash === self.lastHash) return;
                        self.lastHash = newHash;

                        const rawData = JSON.parse(newHash);
                        if (self.chart) { self.chart.destroy(); self.chart = null; }
                        
                        self.chart = new window.Chart(self.$refs.revenueCanvas, {
                            type: 'bar',
                            data: {
                                labels: rawData.labels,
                                datasets: [
                                    { label: rawData.partner_label, data: rawData.partner_data, backgroundColor: '#22C55E', borderRadius: 0, borderSkipped: false, stack: 'revenue' },
                                    { label: rawData.commission_label, data: rawData.commission_data, backgroundColor: '#F97316', borderRadius: { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 }, borderSkipped: false, stack: 'revenue' }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, boxWidth: 8 } },
                                    tooltip: {
                                        enabled: true,
                                        usePointStyle: true,
                                        callbacks: {
                                            label: function(context) {
                                                let label = context.dataset.label || '';
                                                if (label) { label += ' : '; }
                                                if (context.raw !== null && context.raw !== undefined) {
                                                    label += '{{ $this->getCurrencySymbol() }}' + Number(context.raw).toLocaleString();
                                                }
                                                return label;
                                            }
                                        }
                                    }
                                },
                                interaction: {
                                    mode: 'index',
                                    intersect: false,
                                },
                                scales: {
                                    x: { stacked: true, grid: { display: false }, border: { display: false } },
                                    y: { stacked: true, beginAtZero: true, border: { display: false }, grid: { color: '#F3F4F6' } }
                                }
                            }
                        });
                    }
                    function render() {
                        if (typeof window.Chart === 'undefined') {
                            if (!document.getElementById('chartjs-script')) {
                                let script = document.createElement('script');
                                script.id = 'chartjs-script';
                                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
                                document.head.appendChild(script);
                            }
                            setTimeout(render, 200);
                            return;
                        }
                        buildChart(self.$wire.revenueChartData);
                    }
                    self.$nextTick(render);
                    self.$watch('$wire.revenueChartData', function(newValue) { buildChart(newValue); });
                }
            }"
            x-init="init()"
        >
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800">
                        {!! svg('others.trendup', 'h-5 w-5')->toHtml() !!}
                    </div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.revenue_performance') }}</h3>
                </div>
                <select wire:model.live="revenueChartPreset" style="border: 1px solid #E2E8F0; border-radius: 6px; padding: 4px 8px; font-size: 13px; color: #64748B; background-color: white; cursor: pointer; outline: none;">
                    <option value="today">{{ __('admin.today') }}</option>
                    <option value="this_week">{{ __('admin.this_week') }}</option>
                    <option value="this_month">{{ __('admin.this_month') }}</option>
                    <option value="this_year">{{ __('admin.this_year') }}</option>
                    <option value="all_time">{{ __('admin.all_time') }}</option>
                </select>
            </div>
            {{-- wire:ignore prevents Livewire from touching the canvas when an unrelated filter morphs this element --}}
            <div wire:ignore class="h-64">
                <canvas x-ref="revenueCanvas" class="h-full w-full"></canvas>
            </div>
        </div>

        {{-- Booking Status donut chart --}}
        {{-- No wire:key — Alpine persists and $watch reacts when $wire.bookingStatusData is updated by the server --}}
        <div
            class="lg:col-span-2 rounded-2xl border border-[#EDEDED] bg-white p-6 dark:border-gray-700 dark:bg-gray-900"
            x-data="{
                chart: null,
                lastHash: '',
                init() {
                    const self = this;
                    function buildChart(d) {
                        const newHash = JSON.stringify(d);
                        if (newHash === self.lastHash) return;
                        self.lastHash = newHash;

                        const rawData = JSON.parse(newHash);
                        if (self.chart) { self.chart.destroy(); self.chart = null; }
                        
                        self.chart = new window.Chart(self.$refs.donutCanvas, {
                            type: 'doughnut',
                            data: {
                                labels: rawData.labels,
                                datasets: [{ data: [rawData.completed, rawData.cancelled, rawData.refunded], backgroundColor: ['#22C55E', '#EF4444', '#F97316'], borderWidth: 0, hoverOffset: 4 }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '72%',
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        enabled: true,
                                        usePointStyle: true
                                    }
                                }
                            }
                        });
                    }
                    function render() {
                        if (typeof window.Chart === 'undefined') {
                            if (!document.getElementById('chartjs-script')) {
                                let script = document.createElement('script');
                                script.id = 'chartjs-script';
                                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
                                document.head.appendChild(script);
                            }
                            setTimeout(render, 200);
                            return;
                        }
                        buildChart(self.$wire.bookingStatusData);
                    }
                    self.$nextTick(render);
                    self.$watch('$wire.bookingStatusData', function(newValue) { buildChart(newValue); });
                }
            }"
            x-init="init()"
        >
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800">
                        {!! svg('others.chartpieslice', 'h-5 w-5')->toHtml() !!}
                    </div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.booking_status') }}</h3>
                </div>
                <select wire:model.live="bookingStatusPreset" style="border: 1px solid #E2E8F0; border-radius: 6px; padding: 4px 8px; font-size: 13px; color: #64748B; background-color: white; cursor: pointer; outline: none;">
                    <option value="today">{{ __('admin.today') }}</option>
                    <option value="this_week">{{ __('admin.this_week') }}</option>
                    <option value="this_month">{{ __('admin.this_month') }}</option>
                    <option value="this_year">{{ __('admin.this_year') }}</option>
                    <option value="all_time">{{ __('admin.all_time') }}</option>
                </select>
            </div>

            {{-- wire:ignore preserves the canvas when an unrelated filter morphs this element --}}
            <div wire:ignore class="relative h-44">
                <canvas x-ref="donutCanvas" class="h-full w-full"></canvas>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('admin.total_booking') }}</p>
                    <p class="text-3xl font-bold text-gray-950 dark:text-white" x-text="$wire.bookingStatusData.total.toLocaleString()"></p>
                </div>
            </div>

            <div class="mt-5 space-y-2.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-2.5 w-2.5 rounded-full bg-green-500"></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ __('admin.completed') }}</span>
                    </div>
                    <span class="text-xs font-semibold text-gray-950 dark:text-white">{{ number_format($bookingStatusData['completed']) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500"></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ __('admin.cancelled') }}</span>
                    </div>
                    <span class="text-xs font-semibold text-gray-950 dark:text-white">{{ number_format($bookingStatusData['cancelled']) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-2.5 w-2.5 rounded-full bg-orange-500"></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ __('admin.refunded') }}</span>
                    </div>
                    <span class="text-xs font-semibold text-gray-950 dark:text-white">{{ number_format($bookingStatusData['refunded']) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Commission & Payouts row ── --}}
    <div @class([
        'grid grid-cols-1 gap-4',
        'lg:grid-cols-2' => $showCommissionCard,
    ])>

        {{-- Commission & Rules — admin only --}}
        @if ($showCommissionCard && $commissionData)
            <div class="rounded-2xl border border-[#EDEDED] bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800">
                        {!! svg('others.receipt', 'h-5 w-5')->toHtml() !!}
                    </div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.commission_and_rules') }}</h3>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 dark:bg-red-900/20">
                                {!! svg('others.percent', 'h-5 w-5')->toHtml() !!}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('admin.standard_rate') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.platform_base_commission') }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-bold text-gray-950 dark:text-white">{{ $commissionData['rate'] }}%</span>
                            @if ($commissionData['is_overridden'])
                                <span class="inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">
                                    {{ __('admin.override') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-between rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/20">
                                {!! svg('others.currency', 'h-5 w-5')->toHtml() !!}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('admin.total_collected') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.lifetime_commission_earnings') }}</p>
                            </div>
                        </div>
                        <span class="text-lg font-bold text-gray-950 dark:text-white">{{ $commissionData['lifetime_collected'] }}</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Partner Payouts --}}
        <div class="rounded-2xl border border-[#EDEDED] bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800">
                    {!! svg('others.handcoinsblack', 'h-5 w-5')->toHtml() !!}
                </div>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.partner_payouts') }}</h3>
            </div>
            <div class="rounded-xl bg-[#F9FAFB] p-4 dark:bg-gray-800/50">
                <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.total_amount_pending') }}</span>
                    <span class="text-sm font-bold text-gray-950 dark:text-white">{{ $payoutData['total_pending'] }}</span>
                </div>

                <div class="flex items-center justify-between border-b border-gray-200 py-3 dark:border-gray-700">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.available_balance') }}</span>
                    <span class="text-sm font-bold text-gray-950 dark:text-white">{{ $payoutData['available_balance'] }}</span>
                </div>

                <div class="mt-3 flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div>
                        <p class="text-base font-bold text-gray-950 dark:text-white">{{ __('admin.total_payout') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.lifetime_payout_amount') }}</p>
                    </div>
                    <span class="text-lg font-bold text-gray-950 dark:text-white">{{ $payoutData['total_withdrawn'] }}</span>
                </div>
            </div>
        </div>
    </div>

</div>
