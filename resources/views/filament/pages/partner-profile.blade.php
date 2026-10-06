<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ─── Personal Info Card ───────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            {{-- Header: Avatar + Name + Edit --}}
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <div class="flex items-center gap-x-4">
                    <img
                        src="{{ $user->avatar ? (filter_var($user->avatar, FILTER_VALIDATE_URL) ? $user->avatar : asset('storage/' . $user->avatar)) : asset('avatars/defaultUser.svg') }}"
                        alt="{{ $user->name }}"
                        class="h-16 w-16 rounded-2xl object-cover border border-gray-100 dark:border-gray-700 shadow-sm cursor-pointer transition hover:opacity-90"
                        data-action="open"
                        data-url="{{ $user->avatar ? (filter_var($user->avatar, FILTER_VALIDATE_URL) ? $user->avatar : asset('storage/' . $user->avatar)) : asset('avatars/defaultUser.svg') }}"
                    />
                    <div>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $user->name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.personal_info') }}</p>
                    </div>
                </div>

                {{ $this->editPersonalInfoAction }}
            </div>

            {{-- Contact Details --}}
            <div class="flex flex-wrap gap-8 px-6 py-4">
                {{-- Phone --}}
                <div class="flex items-center gap-x-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-900/20">
                        <x-heroicon-o-phone class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.phone') }}</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ ($user->dial_code ? $user->dial_code . ' ' : '') . ($user->phone ?? __('admin.not_set')) }}
                        </p>
                    </div>
                </div>

                {{-- Email --}}
                <div class="flex items-center gap-x-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-900/20">
                        <x-heroicon-o-envelope class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.email') }}</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->email }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─── Address Card ──────────────────────────────────────────────────── --}}
        @php $partner = $user->partner; @endphp
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-map-pin class="h-5 w-5 text-gray-400" />
                    <p class="text-base font-semibold text-gray-900 dark:text-white">{{ __('admin.owner_address') }}</p>
                </div>
                {{ $this->editAddressAction }}
            </div>
            <div class="grid grid-cols-1 gap-6 p-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.street_address') }}</p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $partner?->address ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.country') }}</p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $partner?->country ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.state') }}</p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $partner?->state_province ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.city') }}</p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $partner?->city ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.zip_code') }}</p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $partner?->zip_code ?: '—' }}</p>
                </div>
            </div>
        </div>

        {{-- ─── Registration Details ──────────────────────────────────────────── --}}
        @php $regFields = $this->getPartnerRegistrationFields(); @endphp
        @if ($regFields->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.registration_details') }}</x-slot>
            <x-slot name="description">
                {{ __('admin.registration_details_profile_description') }}
                @if ($registrationCountryName)
                    &mdash; <span class="font-medium text-primary-600 dark:text-primary-400">{{ $registrationCountryName }}</span>
                @endif
            </x-slot>

            {{ $this->registrationForm }}

            <div class="mt-4 flex justify-end">
                <x-filament::button
                    wire:click="updateRegistrationDetails"
                    wire:loading.attr="disabled"
                    wire:target="updateRegistrationDetails">
                    {{ __('admin.save_details') }}
                </x-filament::button>
            </div>
        </x-filament::section>
        @endif

        {{-- ─── Change Password ────────────────────────────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.change_password') }}</x-slot>

            {{ $this->passwordForm }}

            <div class="mt-4 flex justify-end">
                <x-filament::button
                    wire:click="updatePassword"
                    wire:loading.attr="disabled"
                    wire:target="updatePassword">
                    {{ __('admin.update_password') }}
                </x-filament::button>
            </div>
        </x-filament::section>

    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
