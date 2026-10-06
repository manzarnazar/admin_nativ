<div style="background-color: white; border-radius: 12px; padding: 16px; border: 1px solid #F1F5F9; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; flex-direction: column; height: 100%;">
    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="background-color: #F7F7F7; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center;">
                <x-phosphor-devices style="width: 24px; height: 24px; color: #555555;" />
            </div>
            <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.platform_usage') }}</h3>
        </div>
        <select wire:model.live="timeFilter" style="border: 1px solid #E2E8F0; border-radius: 6px; padding: 4px 8px; font-size: 13px; color: #64748B; background-color: white; cursor: pointer; outline: none;">
            <option value="all_time">{{ __('admin.all_time') ?? 'All Time' }}</option>
            <option value="last_7_days">{{ __('admin.last_7_days') }}</option>
            <option value="last_30_days">{{ __('admin.last_30_days') }}</option>
            <option value="this_month">{{ __('admin.this_month') }}</option>
            <option value="this_year">{{ __('admin.this_year') }}</option>
        </select>
    </div>

    {{-- Doughnut chart with center text overlay --}}
    <div class="dashboard-chart-embed" style="position: relative; margin-top: 40px; margin-bottom: 24px;">
        @livewire(\App\Filament\Widgets\PlatformUsageChart::class, ['timeFilter' => $timeFilter], key('saas-platform-usage-'.$timeFilter))
        <div style="position: absolute; top: 55%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
            <p style="font-size: 28px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ number_format($total) }}</p>
            <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 4px 0 0 0; line-height: 1;">{{ __('admin.total_users') }}</p>
        </div>
    </div>

    {{-- Web & App stat boxes --}}
    <div class="grid grid-cols-2 gap-3" style="margin-top: 16px;">
        <div style="background-color: #F0F6FF; border-radius: 10px; padding: 24px 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;">
            <p style="font-size: 26px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ $webCount }}</p>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: #FF829D;"></span>
                <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 0; line-height: 1;">{{ __('admin.web') }}</p>
            </div>
        </div>
        <div style="background-color: #F0F6FF; border-radius: 10px; padding: 24px 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;">
            <p style="font-size: 26px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ $appCount }}</p>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: #AD85FF;"></span>
                <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 0; line-height: 1;">{{ __('admin.app') }}</p>
            </div>
        </div>
    </div>
</div>