<div style="background-color: white; border-radius: 12px; padding: 16px; border: 1px solid #F1F5F9; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
    {{-- Header --}}
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
        <div style="background-color: #F7F7F7; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center;">
            <x-phosphor-chart-pie-slice style="width: 24px; height: 24px; color: #555555;" />
        </div>
        <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.property_distribution') }}</h3>
    </div>

    {{-- Doughnut chart with center total overlay --}}
    <div class="dashboard-chart-embed" style="position: relative;">
        @livewire(\App\Filament\Widgets\PropertyDistributionChart::class, ['countryId' => $countryId], key('saas-prop-dist-chart-'.$countryId))
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none; white-space: nowrap;">
            <p style="font-size: 28px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">{{ number_format($total) }}</p>
            <p style="font-size: 13px; font-weight: 500; color: #64748B; margin: 4px 0 0 0; line-height: 1;">{{ __('admin.total_properties') }}</p>
        </div>
    </div>

    {{-- Per-type counts --}}
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 8px 20px; margin-top: 12px;">
        @foreach ($types as $type)
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: {{ $type['color'] }}; flex-shrink: 0;"></span>
                <span style="font-size: 13px; color: #64748B;">{{ $type['name'] }}</span>
                <span style="font-size: 13px; font-weight: 700; color: #0F172A;">{{ number_format($type['count']) }}</span>
            </div>
        @endforeach
    </div>
</div>
