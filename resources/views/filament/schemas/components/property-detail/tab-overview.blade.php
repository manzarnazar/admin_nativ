{{--
    Shared "Overview" tab body. Expects:
    - $property
    - $otherRegistrationValues (Collection<PropertyRegistrationValue>)
    - $showPartnerSidebar (bool) — admin only; partner viewing their own
      property doesn't need a card telling them who the partner is.
    - $partnerTotalProperties, $partnerCommission (only when $showPartnerSidebar)
--}}
<div class="grid grid-cols-1 gap-7 {{ $showPartnerSidebar ? 'lg:grid-cols-3' : '' }}">
    <div class="flex flex-col gap-6 {{ $showPartnerSidebar ? 'lg:col-span-2' : '' }}">
        {{-- About Property --}}
        <div class="rounded-2xl border border-[#EDEDED] bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-[#EDEDED] p-6 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.about_property') }}</h3>
            </div>
            <div class="p-6">
                @if ($property->description)
                <div class="text-sm text-gray-700 dark:text-gray-300 prose prose-sm max-w-none dark:prose-invert">{!! $property->description !!}</div>
                @else
                <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('admin.no_description_added') }}</p>
                @endif
            </div>
        </div>

        {{-- Payment Configuration --}}
        <div class="rounded-2xl border border-[#EDEDED] bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4 flex items-center gap-2 rounded-xl bg-gray-100 p-3 dark:bg-gray-700">
                <x-heroicon-o-credit-card class="h-6 w-6 text-gray-950 dark:text-white" />
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.payment_configuration') }}</h3>
            </div>

            @if ($property->pay_at_property)
            <div class="mb-4 flex items-center gap-1 rounded-lg bg-[#E5FAEF] px-3 py-1.5">
                <x-heroicon-s-check-circle class="h-4 w-4 text-[#20B364]" />
                <span class="text-sm font-medium text-[#20B364]">{{ __('admin.pay_at_property_supported') }}</span>
            </div>

            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <span class="text-gray-700 dark:text-gray-300">{{ __('admin.advance_required') }}</span>
                    <span class="font-semibold text-gray-950 dark:text-white">{{ number_format((float) $property->advance_percentage, 0) }}%</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-700 dark:text-gray-300">{{ __('admin.remaining_payment') }}</span>
                    <span class="font-semibold text-gray-950 dark:text-white">{{ 100 - number_format((float) $property->advance_percentage, 0) }}% {{ __('admin.at_property') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-700 dark:text-gray-300">{{ __('admin.collection_mode') }}</span>
                    <span class="font-semibold text-gray-950 dark:text-white">{{ __('admin.property_collects_from_guest') }}</span>
                </div>
            </div>

            <!-- <div class="mt-4 rounded-2xl bg-[#FBEAEA] p-4">
                <p class="text-lg font-semibold text-[#D63031]">{{ __('admin.note') }}:</p>
                <p class="text-sm text-gray-950 dark:text-white">{{ __('admin.payment_settings_note') }}</p>
            </div> -->
            @else
            <div class="mb-4 flex items-center gap-1 rounded-lg bg-[#FBEAEA] px-3 py-1.5">
                <x-heroicon-s-x-circle class="h-4 w-4 text-[#D63031]" />
                <span class="text-sm font-medium text-[#D63031]">{{ __('admin.pay_at_property_not_supported') }}</span>
            </div>
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ __('admin.no_pay_at_property_bookings') }}</p>
            @endif
        </div>

        {{-- Property Contact --}}
        <div class="rounded-2xl border border-[#EDEDED] bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4 flex items-center gap-2 rounded-xl bg-gray-100 p-3 dark:bg-gray-700">
                <x-heroicon-o-phone class="h-6 w-6 text-gray-950 dark:text-white" />
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.property_contact') }}</h3>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.phone_number') }}</p>
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ trim(($property->dial_code ? $property->dial_code.' ' : '').$property->phone) ?: '-' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.email_address') }}</p>
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $property->email ?: '-' }}</p>
                </div>
            </div>
        </div>

        {{-- Bank Details --}}
        <div class="rounded-2xl border border-[#EDEDED] bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4 flex items-center gap-2 rounded-xl bg-gray-100 p-3 dark:bg-gray-700">
                <x-heroicon-o-building-library class="h-6 w-6 text-gray-950 dark:text-white" />
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.bank_details') }}</h3>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.account_holder') }}</p>
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $property->bank_account_holder ?: '-' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.bank_name') }}</p>
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $property->bank_name ?: '-' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.account_number') }}</p>
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $property->bank_account_number ?: '-' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.bank_code') }}</p>
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $property->bank_code ?: '-' }}</p>
                </div>
            </div>
        </div>

        {{-- Other Information (non-file registration values) --}}
        @if ($otherRegistrationValues->isNotEmpty())
        <div class="rounded-2xl border border-[#EDEDED] bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4 flex items-center gap-2 rounded-xl bg-gray-100 p-3 dark:bg-gray-700">
                <x-heroicon-o-document-text class="h-6 w-6 text-gray-950 dark:text-white" />
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.other_information') }}</h3>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($otherRegistrationValues as $value)
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ ucfirst($value->registrationField?->name ?? '-') }}</p>
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ is_array($value->value) ? implode(', ', $value->value) : $value->value }}</p>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    @if ($showPartnerSidebar)
    {{-- Partner Information (admin only) --}}
    <div class="rounded-2xl border border-[#EDEDED] bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-[#EDEDED] p-6 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.partner_information') }}</h3>
        </div>
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-4">
                <img src="{{ $property->partner?->user?->getFilamentAvatarUrl() }}" data-gallery="partner-avatar" class="h-20 w-20 cursor-pointer rounded-xl border border-gray-200 object-cover transition hover:opacity-80 dark:border-gray-700" alt="">
                <div>
                    <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $property->partner?->user?->name ?? '-' }}</p>
                </div>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-700"></div>

            <div class="flex flex-col gap-4">
                <h4 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.contact_information') }}</h4>

                <div class="flex items-center gap-4 rounded-2xl bg-gray-50 p-3 dark:bg-gray-800">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--brand-primary-light)] dark:bg-blue-900/20">
                        <x-heroicon-o-map-pin class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.location') }}</p>
                        <p class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ implode(', ', array_filter([$property->partner?->address, $property->partner?->city, $property->partner?->state_province, $property->partner?->zip_code])) ?: '-' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-2xl bg-gray-50 p-3 dark:bg-gray-800">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--brand-primary-light)] dark:bg-blue-900/20">
                        <x-heroicon-o-phone class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.phone') }}</p>
                        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $property->partner?->user?->phone ?: '-' }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-2xl bg-gray-50 p-3 dark:bg-gray-800">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--brand-primary-light)] dark:bg-blue-900/20">
                        <x-heroicon-o-envelope class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.email') }}</p>
                        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $property->partner?->user?->email ?: '-' }}</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-700"></div>

            <div class="flex flex-col gap-4">
                <h4 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.ownership_context') }}</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-2xl bg-[var(--brand-primary-light)] p-4 dark:bg-blue-900/20">
                        <div class="mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-[#2196F3]">
                            <x-heroicon-o-building-office-2 class="h-5 w-5 text-white" />
                        </div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.total_properties') }}</p>
                        <p class="text-xl font-bold text-gray-950 dark:text-white">{{ $partnerTotalProperties }}</p>
                    </div>
                    <div class="rounded-2xl bg-[#E5FAEF] p-4 dark:bg-green-900/20">
                        <div class="mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-[#20B364]">
                            <x-heroicon-o-check-circle class="h-5 w-5 text-white" />
                        </div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ __('admin.commission') }}
                            @if ($partnerCommission['is_overridden'])
                            <span class="text-xs">({{ __('admin.overridden') }})</span>
                            @endif
                        </p>
                        <p class="text-xl font-bold text-gray-950 dark:text-white">{{ rtrim(rtrim(number_format($partnerCommission['rate'], 2), '0'), '.') }}%</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-700"></div>

            <a href="{{ \App\Filament\Pages\AllPartnersDetail::getUrl(['partnerId' => $property->partner_id]) }}" wire:navigate class="flex items-center justify-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-400">
                {{ __('admin.view_partner_profile') }}
                <x-heroicon-o-arrow-right class="h-4 w-4" />
            </a>
        </div>
    </div>
    @endif
</div>