<x-filament-panels::page>
    {{-- Stats Cards --}}
    @php
        $stats = $this->getBookingStats();
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Bookings --}}
        <div style="background-color: #E7F4FE; border-radius: 16px; padding: 16px; display: flex; align-items: center; gap: 16px;">
            <div style="background-color: #2196F3; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                <x-heroicon-o-calendar-days style="width: 24px; height: 24px;" />
            </div>
            <div>
                <p style="font-size: 14px; font-weight: 500; color: #555555; margin: 0; line-height: 20px;">{{ __('admin.total_bookings') }}</p>
                <p style="font-size: 20px; font-weight: 700; color: #0D0E0D; margin: 0; line-height: 28px;">{{ $stats['total'] }}</p>
            </div>
        </div>

        {{-- Cancelled Bookings --}}
        <div style="background-color: #FBEAEA; border-radius: 16px; padding: 16px; display: flex; align-items: center; gap: 16px;">
            <div style="background-color: #D63031; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                <x-heroicon-o-x-circle style="width: 24px; height: 24px;" />
            </div>
            <div>
                <p style="font-size: 14px; font-weight: 500; color: #555555; margin: 0; line-height: 20px;">{{ __('admin.cancelled_bookings') }}</p>
                <p style="font-size: 20px; font-weight: 700; color: #0D0E0D; margin: 0; line-height: 28px;">{{ $stats['cancelled'] }}</p>
            </div>
        </div>

        {{-- From Application --}}
        <div style="background-color: #E5FAEF; border-radius: 16px; padding: 16px; display: flex; align-items: center; gap: 16px;">
            <div style="background-color: #20B364; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                <x-heroicon-o-device-phone-mobile style="width: 24px; height: 24px;" />
            </div>
            <div>
                <p style="font-size: 14px; font-weight: 500; color: #555555; margin: 0; line-height: 20px;">{{ __('admin.bookings_from_application') }}</p>
                <p style="font-size: 20px; font-weight: 700; color: #0D0E0D; margin: 0; line-height: 28px;">{{ $stats['from_application'] }}</p>
            </div>
        </div>

        {{-- From Website --}}
        <div style="background-color: #FEF5E6; border-radius: 16px; padding: 16px; display: flex; align-items: center; gap: 16px;">
            <div style="background-color: #F79E1B; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                <x-heroicon-o-globe-alt style="width: 24px; height: 24px;" />
            </div>
            <div>
                <p style="font-size: 14px; font-weight: 500; color: #555555; margin: 0; line-height: 20px;">{{ __('admin.bookings_from_website') }}</p>
                <p style="font-size: 20px; font-weight: 700; color: #0D0E0D; margin: 0; line-height: 28px;">{{ $stats['from_website'] }}</p>
            </div>
        </div>
    </div>

    {{-- Table (always render — Filament handles empty state internally) --}}
    {{ $this->table }}
</x-filament-panels::page>
