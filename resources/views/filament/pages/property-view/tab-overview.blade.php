{{-- About Property --}}
<div class="space-y-6">
    <div>
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.about_property') }}</h3>
        <div class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400 prose dark:prose-invert max-w-none">
            {!! $property->description ?: '-' !!}
        </div>
    </div>

    {{-- Payment Configuration --}}
    <div class="rounded-lg bg-gray-50 p-5 dark:bg-gray-800">
        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
            <x-heroicon-o-banknotes class="h-4 w-4" />
            {{ __('admin.payment_configuration') }}
        </h4>

        @if ($property->pay_at_property)
        <div class="mt-3 space-y-2">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 shrink-0 rounded-full bg-green-500"></span>
                <span class="text-sm text-green-700 dark:text-green-400">{{ __('admin.pay_at_property_supported') }}</span>
            </div>
            <div class="mt-3 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white text-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-900">
                <div class="pay-cfg-row flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5 px-3 py-2 text-gray-600 dark:text-gray-400">
                    <span>{{ __('admin.advance_required') }}</span>
                    <span class="shrink-0 font-medium text-gray-950 dark:text-white">{{ $property->advance_percentage ?? 0 }}%</span>
                </div>
                <div class="pay-cfg-row flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5 px-3 py-2 text-gray-600 dark:text-gray-400">
                    <span>{{ __('admin.remaining_payment') }}</span>
                    <span class="shrink-0 font-medium text-gray-950 dark:text-white">{{ 100 - ($property->advance_percentage ?? 0) }}% {{ __('admin.at_property') }}</span>
                </div>
                <div class="pay-cfg-row flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5 px-3 py-2 text-gray-600 dark:text-gray-400">
                    <span class="shrink-0">{{ __('admin.collection_mode') }}</span>
                    <span class="min-w-0 text-right font-medium text-gray-950 dark:text-white">{{ __('admin.property_collects_from_guest') }}</span>
                </div>
            </div>
            <!-- 
                <div class="flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2.5 dark:bg-red-950/30">
                    <x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
                    <span class="text-xs leading-relaxed text-red-700 dark:text-red-300">
                        <span class="font-semibold">{{ __('admin.note') }}:</span>
                        {{ __('admin.payment_settings_note') }}
                    </span>
                </div> -->
        </div>
        @else
        <div class="mt-3 space-y-2">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                <span class="text-sm text-red-700 dark:text-red-400">{{ __('admin.pay_at_property_not_supported') }}</span>
            </div>
            <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                <li class="flex items-center gap-2">
                    <span class="text-gray-400">&bull;</span>
                    {{ __('admin.no_pay_at_property_bookings') }}
                </li>
                <li class="flex items-center gap-2">
                    <span class="text-gray-400">&bull;</span>
                    {{ __('admin.full_payment_online_note') }}
                </li>
            </ul>
        </div>
        @endif
    </div>

    {{-- Property Contact --}}
    <div class="rounded-lg bg-gray-50 p-5 dark:bg-gray-800">
        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
            <x-heroicon-o-phone class="h-4 w-4" />
            {{ __('admin.property_contact') }}
        </h4>
        <div class="mt-3 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div class="prop-contact-field">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.phone_number') }}</span>
                <p class="mt-1 break-all font-medium text-gray-950 dark:text-white">{{ $property->phone ? trim(($property->dial_code ? $property->dial_code.' ' : '').$property->phone) : '-' }}</p>
            </div>
            <div class="prop-contact-field">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.email_address') }}</span>
                <p class="mt-1 break-all font-medium text-gray-950 dark:text-white">{{ $property->email ?: '-' }}</p>
            </div>
            @if ($property->landline)
            <div class="prop-contact-field">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.landline') }}</span>
                <p class="mt-1 break-all font-medium text-gray-950 dark:text-white">{{ trim(($property->landline_dial_code ? $property->landline_dial_code.' ' : '').$property->landline) }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Bank Details --}}
    <div class="rounded-lg bg-gray-50 p-5 dark:bg-gray-800">
        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
            <x-heroicon-o-building-library class="h-4 w-4" />
            {{ __('admin.bank_details') }}
        </h4>
        <div class="mt-3 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div class="bank-detail-field">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.account_holder_name') }}</span>
                <p class="mt-1 break-words font-medium text-gray-950 dark:text-white">{{ $property->bank_account_holder ?: '-' }}</p>
            </div>
            <div class="bank-detail-field">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.bank_name') }}</span>
                <p class="mt-1 break-words font-medium text-gray-950 dark:text-white">{{ $property->bank_name ?: '-' }}</p>
            </div>
            <div class="bank-detail-field">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.account_number') }}</span>
                <p class="mt-1 break-all font-medium text-gray-950 dark:text-white">{{ $property->bank_account_number ?: '-' }}</p>
            </div>
            <div class="bank-detail-field">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.bank_code') }}</span>
                <p class="mt-1 break-all font-medium text-gray-950 dark:text-white">{{ $property->bank_code ?: '-' }}</p>
            </div>
        </div>
    </div>
</div>