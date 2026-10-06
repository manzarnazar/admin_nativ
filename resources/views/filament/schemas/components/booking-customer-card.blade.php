@php
    $customerId = $forceCustomerId ?? $get('customer_id');
    $customer = $customerId ? \App\Models\User::find($customerId) : null;
@endphp

@if ($customer)
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-100 dark:bg-primary-900">
                    @if ($customer->avatar && str_starts_with($customer->avatar, 'avatars/'))
                        <img src="{{ asset('storage/' . $customer->avatar) }}" alt="{{ $customer->name }}" class="h-full w-full object-cover" />
                    @else
                        <x-heroicon-o-user class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                    @endif
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $customer->name }}</p>
                    <div class="mt-0.5 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                        <p class="flex items-center gap-1">
                            <x-heroicon-o-phone class="h-3 w-3" />
                            {{ $customer->phone ? (($customer->dial_code ? $customer->dial_code . ' ' : '') . \App\Support\DemoMode::maskPhone($customer->phone)) : '-' }}
                        </p>
                        @if ($customer->email)
                            <p class="flex items-center gap-1">
                                <x-heroicon-o-envelope class="h-3 w-3" />
                                {{ \App\Support\DemoMode::maskEmail($customer->email) }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs font-semibold text-primary-600 dark:text-primary-400">{{ __('admin.customer_id') }}</p>
                @if (\Filament\Facades\Filament::getCurrentPanel()?->getId() === 'admin')
                    <a href="{{ \App\Filament\Pages\CustomerView::getUrl(['record' => $customer->id]) }}" wire:navigate class="text-lg font-bold text-gray-950 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                        #{{ str_pad((string) $customer->id, 2, '0', STR_PAD_LEFT) }}
                    </a>
                @else
                    <p class="text-lg font-bold text-gray-950 dark:text-white">
                        #{{ str_pad((string) $customer->id, 2, '0', STR_PAD_LEFT) }}
                    </p>
                @endif
            </div>
        </div>
    </div>
@endif
