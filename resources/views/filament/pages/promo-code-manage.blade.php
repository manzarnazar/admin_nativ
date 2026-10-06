<x-filament-panels::page>
    @if ($this->getHasPromoCodes())
        @php $stats = $this->getPromoStats(); $currency = $this->getCurrencySymbol(); @endphp

        {{-- Stats Cards --}}   
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- Active Promo Codes --}}
            <div style="background-color: #E7F4FE; border-radius: 16px; padding: 16px; display: flex; align-items: center; gap: 16px;">
                <div style="background-color: #2196F3; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                    <x-heroicon-o-ticket style="width: 24px; height: 24px;" />
                </div>
                <div>
                    <p style="font-size: 14px; font-weight: 500; color: #555555; margin: 0; line-height: 20px;">{{ __('admin.active_promo_codes') }}</p>
                    <p style="font-size: 20px; font-weight: 700; color: #0D0E0D; margin: 0; line-height: 28px;">{{ $stats['active_count'] }}</p>
                </div>
            </div>

            {{-- Total Redemptions --}}
            <div style="background-color: #E5FAEF; border-radius: 16px; padding: 16px; display: flex; align-items: center; gap: 16px;">
                <div style="background-color: #20B364; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                    <x-heroicon-o-users style="width: 24px; height: 24px;" />
                </div>
                <div>
                    <p style="font-size: 14px; font-weight: 500; color: #555555; margin: 0; line-height: 20px;">{{ __('admin.total_redemptions') }}</p>
                    <p style="font-size: 20px; font-weight: 700; color: #0D0E0D; margin: 0; line-height: 28px;">{{ number_format($stats['total_redemptions']) }}</p>
                </div>
            </div>

            {{-- Discount Value Given --}}
            <div style="background-color: #FEF5E6; border-radius: 16px; padding: 16px; display: flex; align-items: center; gap: 16px;">
                <div style="background-color: #F79E1B; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                    <x-heroicon-o-arrow-trending-up style="width: 24px; height: 24px;" />
                </div>
                <div>
                    <p style="font-size: 14px; font-weight: 500; color: #555555; margin: 0; line-height: 20px;">{{ __('admin.discount_value_given') }}</p>
                    <p style="font-size: 20px; font-weight: 700; color: #0D0E0D; margin: 0; line-height: 28px;">{{ $currency }}{{ number_format($stats['discount_value_given'], 0) }}</p>
                </div>
            </div>
        </div>

        {{-- Table --}}
        {{ $this->table }}
    @else
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-tag class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                </div>

                <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('admin.no_promo_codes_yet') }}
                </h4>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.no_promo_codes_description') }}
                </p>
            </div>
        </div>

        <x-filament-actions::modals />
    @endif
</x-filament-panels::page>
