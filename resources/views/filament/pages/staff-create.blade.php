<x-filament-panels::page>
    {{-- Back Link --}}
    <div class="-mt-4 mb-3">
        <a
            href="{{ \App\Filament\Pages\StaffManage::getUrl() }}"
            wire:navigate
            class="inline-flex items-center gap-1 text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            {{ __('admin.back_to_staff_list') }}
        </a>
    </div>

    {{-- Page Header --}}
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-medium text-gray-950 dark:text-white" style="font-family: 'Outfit', sans-serif;">
            {{ $this->record ? __('admin.edit_staff_member') : __('admin.add_new_staff_member') }}
        </h1>
        <div class="flex items-center gap-3">
            <button
                type="button"
                wire:click="cancel"
                class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-normal text-gray-700 hover:bg-gray-50 dark:border-gray-500 dark:text-gray-300"
            >
                {{ __('admin.cancel') }}
            </button>
            <button
                type="button"
                wire:click="saveStaff"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-normal text-white hover:bg-primary-700"
            >
                <x-heroicon-o-bookmark-square class="h-5 w-5" />
                {{ __('admin.save_staff_member') }}
            </button>
        </div>
    </div>

    <div class="space-y-7">

        {{-- Country-specific Note Banner --}}
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
            <p class="text-sm">
                <span class="font-semibold text-red-600 dark:text-red-400">{{ __('admin.note') }}:</span>
                <span class="font-medium text-gray-900 dark:text-gray-100"> </span>
                <span class="font-normal text-red-700 dark:text-red-300">{{ __('admin.staff_country_note') }}</span>
            </p>
        </div>

        {{-- Personal Details --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-black dark:text-white">{{ __('admin.personal_details') }}</h2>
            </div>
            <div class="p-6">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:gap-7">
                    {{-- Avatar Upload --}}
                    <div class="flex flex-col items-center gap-6 rounded-2xl border border-gray-200 bg-gray-100 p-6 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex h-32 w-32 items-center justify-center rounded-2xl border-2 border-gray-400 bg-gray-200 dark:bg-gray-700">
                            @if ($this->avatarPath)
                                <img
                                    src="{{ asset('storage/'.$this->avatarPath) }}"
                                    class="h-32 w-32 rounded-2xl object-cover"
                                    alt="Avatar"
                                />
                            @else
                                <x-heroicon-o-camera class="h-9 w-9 text-gray-500" />
                            @endif
                        </div>
                        <label class="cursor-pointer rounded-lg bg-gray-950 px-4 py-2 text-sm font-normal text-white hover:bg-gray-800 dark:bg-gray-700">
                            {{ __('admin.upload_photo') }}
                            <input
                                type="file"
                                class="hidden"
                                accept="image/png,image/jpg,image/jpeg"
                                wire:model="avatarPath"
                            />
                        </label>
                    </div>

                    {{-- Name / Gender / DOB --}}
                    <div class="flex-1 space-y-7">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-7">
                            {{-- First Name --}}
                            <div class="flex flex-col gap-1">
                                <label class="inline-flex items-center gap-0.5 text-base text-gray-900 dark:text-gray-100">
                                    {{ __('admin.first_name') }}<span class="text-lg font-medium text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model.live="firstName"
                                    placeholder="{{ __('admin.first_name_placeholder') }}"
                                    class="w-full rounded-lg border border-gray-600 bg-gray-50 px-4 py-3 text-base placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-700 dark:text-white"
                                />
                                @error('firstName') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                            </div>
                            {{-- Last Name --}}
                            <div class="flex flex-col gap-1">
                                <label class="inline-flex items-center gap-0.5 text-base text-gray-900 dark:text-gray-100">
                                    {{ __('admin.last_name') }}<span class="text-lg font-medium text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model.live="lastName"
                                    placeholder="{{ __('admin.last_name_placeholder') }}"
                                    class="w-full rounded-lg border border-gray-600 bg-gray-50 px-4 py-3 text-base placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-700 dark:text-white"
                                />
                                @error('lastName') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-7">
                            {{-- Gender --}}
                            <div class="flex flex-col gap-1">
                                <label class="text-base text-gray-900 dark:text-gray-100">{{ __('admin.gender') }}</label>
                                <select
                                    wire:model.live="gender"
                                    class="w-full rounded-lg border border-gray-600 bg-gray-50 px-4 py-3 text-base text-gray-500 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-300"
                                >
                                    <option value="">{{ __('admin.choose_gender') }}</option>
                                    @foreach ($this->getGenderOptions() as $value => $label)
                                        <option value="{{ $value }}" @selected($gender === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- Date of Birth --}}
                            <div class="flex flex-col gap-1">
                                <label class="text-base text-gray-900 dark:text-gray-100">{{ __('admin.date_of_birth') }}</label>
                                <input
                                    type="date"
                                    wire:model.live="dateOfBirth"
                                    max="{{ now()->subYears(18)->format('Y-m-d') }}"
                                    class="w-full rounded-lg border border-gray-600 bg-gray-50 px-4 py-3 text-base text-gray-500 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-300"
                                />
                                @error('dateOfBirth') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Current Address & Contact Info --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-black dark:text-white">{{ __('admin.address_contact_info') }}</h2>
            </div>
            <div class="space-y-5 p-6">
                {{-- Phones: dial code + primary phone (fused) + secondary phone, all in one row --}}
                {{ $this->phoneForm }}

                {{-- State + Zip, same row --}}
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-7">
                    <div class="flex flex-col gap-1">
                        <label class="text-base text-gray-900 dark:text-gray-100">{{ __('admin.state_province') }}</label>
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live="stateProvince"
                                placeholder="{{ __('admin.state_placeholder') }}"
                                class="w-full rounded-lg border border-gray-600 bg-gray-50 px-4 py-3 text-base placeholder-gray-400 focus:border-primary-500 dark:border-gray-500 dark:bg-gray-700 dark:text-white"
                            />
                            <x-heroicon-o-chevron-down class="absolute right-3 top-3.5 h-5 w-5 text-gray-400" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-base text-gray-900 dark:text-gray-100">{{ __('admin.zip_postal_code') }}</label>
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live="zipCode"
                                placeholder="{{ __('admin.zip_placeholder') }}"
                                class="w-full rounded-lg border border-gray-600 bg-gray-50 px-4 py-3 text-base placeholder-gray-400 focus:border-primary-500 dark:border-gray-500 dark:bg-gray-700 dark:text-white"
                            />
                            <x-heroicon-o-chevron-down class="absolute right-3 top-3.5 h-5 w-5 text-gray-400" />
                        </div>
                    </div>
                </div>

                {{-- Address --}}
                <div class="flex flex-col gap-1">
                    <label class="inline-flex items-center gap-0.5 text-base text-gray-900 dark:text-gray-100">
                        {{ __('admin.address') }}<span class="text-lg font-medium text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model.live="address"
                        placeholder="{{ __('admin.address_placeholder') }}"
                        class="w-full rounded-lg border border-gray-600 bg-gray-50 px-4 py-3 text-base placeholder-gray-400 focus:border-primary-500 dark:border-gray-500 dark:bg-gray-700 dark:text-white"
                    />
                    @error('address') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Staff Documents --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-black dark:text-white">{{ __('admin.staff_documents') }}</h2>
            </div>
            <div class="p-6">
                <div class="flex flex-col gap-1">
                    <label class="inline-flex items-center gap-1 text-base text-gray-900 dark:text-gray-100">
                        {{ __('admin.document_image') }}<span class="font-medium text-red-500">*</span>
                    </label>

                    @if ($documentImagePath)
                        <div class="relative mb-3 inline-block">
                            <img
                                src="{{ asset('storage/'.$documentImagePath) }}"
                                class="h-32 w-auto rounded-lg object-cover"
                                alt="Document"
                            />
                            <button
                                type="button"
                                wire:click="$set('documentImagePath', null)"
                                class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600"
                            >
                                <x-heroicon-s-x-mark class="h-3 w-3" />
                            </button>
                        </div>
                    @else
                        <label class="block cursor-pointer">
                            <div class="flex flex-col items-center justify-center gap-6 rounded-2xl border-2 border-gray-200 bg-gray-100 p-6 dark:border-gray-700 dark:bg-gray-800 hover:border-primary-400">
                                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-white shadow-md dark:bg-gray-700">
                                    <x-heroicon-o-arrow-up-tray class="h-6 w-6 text-primary-500" />
                                </div>
                                <div class="text-center text-base text-gray-600 dark:text-gray-400">
                                    {{ __('admin.drag_drop_or') }} <span class="font-semibold text-primary-600 underline">{{ __('admin.choose_file') }}</span>
                                </div>
                            </div>
                            <input
                                type="file"
                                class="hidden"
                                accept="image/png,image/jpg,image/jpeg"
                                wire:model="documentImagePath"
                            />
                        </label>
                    @endif

                    <div class="mt-1 flex justify-between text-sm text-gray-500 dark:text-gray-400">
                        <span>{{ __('admin.max_size_5mb') }}</span>
                        <span>{{ __('admin.supported_jpg_png') }}</span>
                    </div>
                    @error('documentImagePath') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Account & Role --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-black dark:text-white">{{ __('admin.account_role') }}</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 sm:gap-7">
                    {{-- Email --}}
                    <div class="flex flex-col gap-1">
                        <label class="inline-flex items-center gap-0.5 text-base text-gray-900 dark:text-gray-100">
                            {{ __('admin.email_address') }}<span class="text-lg font-medium text-red-500">*</span>
                        </label>
                        <input
                            type="email"
                            wire:model.live="email"
                            placeholder="{{ __('admin.email_staff_placeholder') }}"
                            class="w-full rounded-lg border border-gray-600 bg-gray-50 px-4 py-3 text-base placeholder-gray-400 focus:border-primary-500 dark:border-gray-500 dark:bg-gray-700 dark:text-white"
                        />
                        @error('email') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>

                    {{-- Password --}}
                    <div class="flex flex-col gap-1" x-data="{ showPassword: false }">
                        <label class="inline-flex items-center gap-0.5 text-base text-gray-900 dark:text-gray-100">
                            {{ __('admin.password') }}
                            @if (!$this->record)
                                <span class="text-lg font-medium text-red-500">*</span>
                            @endif
                        </label>
                        <div class="relative">
                            <input
                                :type="showPassword ? 'text' : 'password'"
                                wire:model.live="password"
                                placeholder="{{ __('admin.password_placeholder') }}"
                                class="w-full rounded-lg border border-gray-600 bg-gray-50 px-4 py-3 text-base placeholder-gray-400 focus:border-primary-500 dark:border-gray-500 dark:bg-gray-700 dark:text-white pr-10"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                tabindex="-1"
                            >
                                <x-heroicon-o-eye x-show="!showPassword" class="h-5 w-5" />
                                <x-heroicon-o-eye-slash x-show="showPassword" class="h-5 w-5" />
                            </button>
                        </div>
                        @error('password') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>

                    {{-- Assigned Role (read-only) --}}
                    <div class="flex flex-col gap-1">
                        <label class="text-base text-gray-900 dark:text-gray-100">{{ __('admin.assigned_role_readonly') }}</label>
                        <div class="flex items-center gap-3 rounded-lg border border-gray-300 bg-gray-200 px-4 py-3 dark:border-gray-600 dark:bg-gray-700">
                            <x-heroicon-o-shield-check class="h-5 w-5 flex-shrink-0 text-gray-500" />
                            <span class="text-base text-gray-600 dark:text-gray-400">
                                {{ $this->getAssignedRoleDisplay() }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500">{{ __('admin.role_managed_from') }}</p>
                    </div>
                </div>

                @if (\App\Support\SystemMode::isSingle())
                    {{-- Property Assignment --}}
                    <div class="mt-7 grid grid-cols-1 gap-5 sm:grid-cols-3 sm:gap-7">
                        <div class="flex flex-col gap-1">
                            <label class="inline-flex items-center gap-0.5 text-base text-gray-900 dark:text-gray-100">
                                {{ __('admin.assign_property') }}<span class="text-lg font-medium text-red-500">*</span>
                            </label>
                            <select
                                wire:model.live="selectedBranchId"
                                class="w-full rounded-lg border border-gray-600 bg-gray-50 px-4 py-3 text-base text-gray-700 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-300"
                            >
                                <option value="">{{ __('admin.assign_property_placeholder') }}</option>
                                @foreach ($this->getPropertyOptions() as $id => $name)
                                    <option value="{{ $id }}" @selected($selectedBranchId == $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('selectedBranchId') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>

    <x-filament-actions::modals />

    {{-- Scroll to the first validation error after the Save button is clicked.
         This page uses raw Livewire validation via wire:click="saveStaff"
         (not a Filament Schema or <form wire:submit>), so Filament's built-in
         form-validation scroll doesn't fire here. --}}
    <script>
        document.addEventListener('livewire:init', () => {
            // When the Save button is clicked, mark the time. The next Livewire
            // commit that finishes shortly after is the one we care about.
            let lastSaveClickAt = 0;
            document.addEventListener('click', (event) => {
                if (event.target?.closest?.('[wire\\:click="saveStaff"]')) {
                    lastSaveClickAt = Date.now();
                }
            }, true);

            // After every commit that follows a recent Save click, scroll to the first error.
            Livewire.hook('commit', ({ succeed }) => {
                succeed(() => {
                    if (Date.now() - lastSaveClickAt > 5000) return;
                    lastSaveClickAt = 0; // consume the trigger so we only scroll once per click

                    // Wait one tick for the morph to apply.
                    queueMicrotask(() => requestAnimationFrame(() => {
                        const errorEl = document.querySelector(
                            '[data-validation-error], p.text-red-500:not(:empty), p.text-red-600:not(:empty)'
                        );
                        if (!errorEl) return;

                        const wrapper = errorEl.closest('[data-field-wrapper], .fi-fo-field-wrp')
                            ?? errorEl.previousElementSibling
                            ?? errorEl.parentElement;
                        (wrapper ?? errorEl).scrollIntoView({ behavior: 'smooth', block: 'center' });

                        wrapper?.querySelector?.('input, select, textarea')?.focus?.({ preventScroll: true });
                    }));
                });
            });
        });
    </script>
</x-filament-panels::page>
