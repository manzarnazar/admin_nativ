<div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         PHASES: account + otp — card layout
         ═══════════════════════════════════════════════════════════════════════ --}}
    @if (in_array($phase, ['account', 'otp']))
    <x-filament-panels::page.simple>

        {{-- ─── Account Step ────────────────────────────────────────────── --}}
        @if ($phase === 'account')
        <div class="space-y-5 pb-2">

            {{-- Name --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">
                    {{ __('admin.name') }} <span class="text-red-500">*</span>
                </label>
                <input wire:model="name" type="text" placeholder="John Doe"
                    class="block w-full rounded-lg border px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 {{ $errors->has('name') ? 'border-red-400 focus:ring-red-400' : 'border-gray-300' }}" />
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Email --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">
                    {{ __('admin.email_address') }} <span class="text-red-500">*</span>
                </label>
                <input wire:model="email" type="email" placeholder="you@example.com"
                    class="block w-full rounded-lg border px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 {{ $errors->has('email') ? 'border-red-400 focus:ring-red-400' : 'border-gray-300' }}" />
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Password --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">
                    {{ __('admin.password') }} <span class="text-red-500">*</span>
                </label>
                <div x-data="{ show: false }" class="relative">
                    <input wire:model="password" :type="show ? 'text' : 'password'" placeholder="••••••••"
                        class="block w-full rounded-lg border px-3 py-2.5 pr-10 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 {{ $errors->has('password') ? 'border-red-400 focus:ring-red-400' : 'border-gray-300' }}" />
                    <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                        <svg x-show="!show" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <svg x-show="show" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Confirm Password --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">
                    {{ __('admin.confirm_password') }} <span class="text-red-500">*</span>
                </label>
                <div x-data="{ show: false }" class="relative">
                    <input wire:model="passwordConfirmation" :type="show ? 'text' : 'password'" placeholder="••••••••"
                        class="block w-full rounded-lg border px-3 py-2.5 pr-10 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 {{ $errors->has('passwordConfirmation') ? 'border-red-400 focus:ring-red-400' : 'border-gray-300' }}" />
                    <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                        <svg x-show="!show" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <svg x-show="show" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
                @error('passwordConfirmation') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Submit --}}
            <button wire:click="submitAccount" wire:loading.attr="disabled"
                class="mt-2 flex w-full items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="submitAccount">{{ __('admin.continue') }}</span>
                <span wire:loading wire:target="submitAccount">{{ __('admin.please_wait') }}</span>
            </button>

            {{-- Back to login --}}
            <p class="mt-3 text-center text-sm text-gray-500">
                {{ __('admin.already_have_an_account') }}
                <a href="{{ route('filament.partner.auth.login') }}" wire:navigate
                    class="font-medium text-primary-600 hover:underline">
                    {{ __('admin.sign_in') }}
                </a>
            </p>

        </div>
        @endif
        {{-- end account step --}}

        {{-- ─── OTP Step ────────────────────────────────────────────────── --}}
        @if ($phase === 'otp')
        <div x-data="{
            digits: ['','','','','',''],
            inputs: [],
            seconds: 30,
            countdownInterval: null,
            init() {
                this.inputs = Array.from(this.$el.querySelectorAll('[data-digit]'));
                this.startCountdown();
            },
            startCountdown() {
                this.seconds = 30;
                if (this.countdownInterval) clearInterval(this.countdownInterval);
                this.countdownInterval = setInterval(() => {
                    if (this.seconds > 0) { this.seconds--; } else { clearInterval(this.countdownInterval); }
                }, 1000);
            },
            onInput(e, i) {
                const v = e.target.value.replace(/\D/g, '').slice(-1);
                this.digits[i] = v;
                e.target.value = v;
                if (v && i < 5) this.inputs[i + 1]?.focus();
                $wire.set('otp', this.digits.join(''));
            },
            onBackspace(e, i) {
                if (!e.target.value && i > 0) {
                    this.inputs[i - 1]?.focus();
                }
            },
            onPaste(e) {
                const p = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                [...p].forEach((c, i) => {
                    this.digits[i] = c;
                    if (this.inputs[i]) this.inputs[i].value = c;
                });
                this.inputs[Math.min(p.length, 5)]?.focus();
                $wire.set('otp', this.digits.join(''));
            },
            async resend() {
                await $wire.resendOtp();
                this.startCountdown();
            }
        }" class="space-y-6 pb-2">

            {{-- 6-box OTP input --}}
            <div wire:ignore class="flex items-center justify-center gap-2">
                @for ($i = 0; $i < 6; $i++)
                    <input
                        data-digit="{{ $i }}"
                        type="text"
                        maxlength="1"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        x-on:input="onInput($event, {{ $i }})"
                        x-on:keydown.backspace="onBackspace($event, {{ $i }})"
                        x-on:paste.prevent="onPaste($event)"
                        class="h-12 w-12 rounded-xl border border-gray-300 text-center text-xl font-bold text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
                    />
                    @if ($i < 5)
                        <span class="select-none text-gray-300">—</span>
                    @endif
                @endfor
            </div>

            @error('otp')
                <p class="text-center text-xs text-red-600">{{ $message }}</p>
            @enderror

            {{-- Verify button --}}
            <button wire:click="verifyOtp" wire:loading.attr="disabled"
                class="flex w-full items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="verifyOtp">{{ __('admin.verify_code') }}</span>
                <span wire:loading wire:target="verifyOtp">{{ __('admin.please_wait') }}</span>
            </button>

            {{-- Resend --}}
            <p class="text-center text-sm text-gray-500">
                <template x-if="seconds > 0">
                    <span>{{ __('admin.resend_code_in') }} <span class="font-semibold text-primary-600" x-text="seconds + 's'"></span></span>
                </template>
                <template x-if="seconds === 0">
                    <button type="button" x-on:click="resend()" class="font-semibold text-primary-600 hover:underline">
                        {{ __('admin.resend_code') }}
                    </button>
                </template>
            </p>

            {{-- Back link --}}
            <p class="text-center text-sm">
                <button type="button" wire:click="$set('phase', 'account')"
                    class="text-gray-500 hover:text-gray-700 hover:underline">
                    ← {{ __('admin.back_to_sign_up') }}
                </button>
            </p>

        </div>
        @endif
        {{-- end OTP step --}}

    </x-filament-panels::page.simple>
    @endif
    {{-- end card phases --}}


    {{-- ═══════════════════════════════════════════════════════════════════════
         PHASES: property_type + country — SetupWizard-style full-screen
         ═══════════════════════════════════════════════════════════════════════ --}}
    @if (in_array($phase, ['property_type', 'country']))
    <div class="fixed inset-0 z-[100] flex h-screen bg-white">

        {{-- ── Left Sidebar ──────────────────────────────────────────────── --}}
        <div class="flex w-56 flex-shrink-0 flex-col border-r border-gray-200">

            {{-- Logo --}}
            <div class="px-5 pb-4 pt-5">
                <span class="text-sm font-bold text-gray-900">{{ config('app.name') }}</span>
            </div>

            {{-- Sidebar Header --}}
            <div class="border-b border-gray-100 px-5 pb-5">
                <h2 class="text-sm font-bold text-gray-900">{{ __('admin.partner_registration') }}</h2>
                <p class="mt-1 text-xs leading-relaxed text-gray-500">{{ __('admin.complete_your_profile_to_continue') }}</p>
            </div>

            {{-- Step Navigation --}}
            @php
            $sidebarSteps = [
                'country'       => ['name' => __('admin.select_country'), 'description' => __('admin.operating_country')],
                'property_type' => ['name' => __('admin.property_type'), 'description' => __('admin.property_category')],
            ];
            $phaseOrder = ['country', 'property_type'];
            @endphp

            <nav class="flex-1 space-y-1 px-5 py-5">
                @foreach ($sidebarSteps as $stepPhase => $step)
                @php
                    $isActive    = $phase === $stepPhase;
                    $isCompleted = array_search($phase, $phaseOrder) > array_search($stepPhase, $phaseOrder);
                @endphp
                <div class="flex items-start gap-3 py-2">
                    <div class="mt-0.5 flex-shrink-0">
                        @if ($isCompleted)
                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-primary-600">
                                <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>
                        @elseif ($isActive)
                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-primary-600">
                                <div class="h-2 w-2 rounded-full bg-white"></div>
                            </div>
                        @else
                            <div class="h-5 w-5 rounded-full border-2 border-gray-300"></div>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs {{ $isActive ? 'font-semibold text-gray-900' : 'font-medium text-gray-400' }}">
                            {{ $step['name'] }}
                        </p>
                        <p class="text-xs text-gray-400">{{ $step['description'] }}</p>
                    </div>
                </div>
                @endforeach
            </nav>

            {{-- Sidebar Footer --}}
            <div class="px-5 py-4">
                <p class="text-xs text-gray-400">&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('admin.all_rights_reserved') }}</p>
            </div>
        </div>
        {{-- end sidebar --}}

        {{-- ── Right Content Area ────────────────────────────────────────── --}}
        <div class="flex flex-1 flex-col overflow-hidden">

            {{-- Scrollable Content --}}
            <div class="flex-1 overflow-y-auto p-10">

                {{-- ─── Country Grid ───────────────────────────────────── --}}
                @if ($phase === 'country')
                <div class="w-full">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">{{ __('admin.choose_country') }}</h1>
                            <p class="mt-1 text-sm text-gray-500">{{ __('admin.choose_country_description') }}</p>
                        </div>
                        <div class="flex flex-shrink-0 items-center gap-2">
                            <input wire:model.live.debounce.300ms="countrySearch"
                                type="text"
                                placeholder="{{ __('admin.search_country') }}"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200" />
                            <button type="button" class="flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                                {{ __('admin.search') }}
                            </button>
                        </div>
                    </div>

                    @error('countryId')
                        <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-6 grid grid-cols-4 gap-4">
                        @forelse ($this->countries as $country)
                        @php
                            $isSelected = $countryId === (string) $country->id;
                            $code = strtoupper($country->iso_code);
                            $flag = mb_chr(0x1F1E6 + ord($code[0]) - ord('A')) . mb_chr(0x1F1E6 + ord($code[1]) - ord('A'));
                        @endphp
                        <div wire:click="selectCountry({{ $country->id }})"
                            class="cursor-pointer rounded-lg border p-4 transition-colors {{ $isSelected ? 'border-primary-500 ring-2 ring-primary-100' : 'border-gray-200 hover:border-gray-300' }}">
                            <div class="flex items-start justify-between">
                                <span class="text-4xl leading-none">{{ $flag }}</span>
                                @if ($isSelected)
                                    <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-primary-600">
                                        <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </div>
                                @else
                                    <div class="h-5 w-5 flex-shrink-0 rounded-full border-2 border-gray-300"></div>
                                @endif
                            </div>
                            <p class="mt-3 text-sm font-semibold text-gray-900">{{ $country->name }}</p>
                            <p class="mt-0.5 truncate text-xs text-gray-400">
                                {{ $country->currency_symbol ? $country->currency_symbol.' ' : '' }}{{ $country->currency_code }} &mdash; {{ $country->currency_name }}
                            </p>
                        </div>
                        @empty
                        <div class="col-span-4 py-12 text-center text-sm text-gray-400">
                            {{ __('admin.no_countries_found') }}
                        </div>
                        @endforelse
                    </div>
                </div>
                @endif
                {{-- end country --}}

                {{-- ─── Property Type Grid ─────────────────────────────── --}}
                @if ($phase === 'property_type')
                <div class="w-full">
                    <h1 class="text-xl font-bold text-gray-900">{{ __('admin.choose_property_type') }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ __('admin.choose_property_type_description') }}</p>

                    @error('propertyTypeId')
                        <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-6 grid grid-cols-4 gap-4">
                        @foreach ($this->propertyTypes as $type)
                        @php $isSelected = $propertyTypeId === (string) $type->id; @endphp
                        <div wire:click="selectPropertyType({{ $type->id }})"
                            class="cursor-pointer rounded-lg border p-4 transition-colors {{ $isSelected ? 'border-primary-500 ring-2 ring-primary-100' : 'border-gray-200 hover:border-gray-300' }}">
                            <div class="flex items-start justify-between">
                                <img src="{{ $type->icon_url }}" alt="{{ $type->name }}" class="h-10 w-10 object-contain" />
                                @if ($isSelected)
                                    <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-primary-600">
                                        <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </div>
                                @else
                                    <div class="h-5 w-5 flex-shrink-0 rounded-full border-2 border-gray-300"></div>
                                @endif
                            </div>
                            <p class="mt-3 text-sm font-semibold text-gray-900">{{ $type->name }}</p>
                            @if ($type->description)
                                <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ $type->description }}</p>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                {{-- end property type --}}

            </div>
            {{-- end scrollable content --}}

            {{-- ── Footer Navigation ──────────────────────────────────── --}}
            <div class="flex flex-shrink-0 items-center justify-between border-t border-gray-100 px-10 py-5">
                <div></div>
                <div class="flex items-center gap-3">
                    @if ($phase === 'country')
                        <button wire:click="$set('phase', 'otp')" type="button"
                            class="flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            &larr; {{ __('admin.previous') }}
                        </button>
                        <button wire:click="submitCountry" wire:loading.attr="disabled" type="button"
                            class="flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="submitCountry">{{ __('admin.next') }} &rarr;</span>
                            <span wire:loading wire:target="submitCountry">{{ __('admin.please_wait') }}</span>
                        </button>
                    @else
                        <button wire:click="$set('phase', 'country')" type="button"
                            class="flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            &larr; {{ __('admin.previous') }}
                        </button>
                        <button wire:click="submitPropertyType" wire:loading.attr="disabled" type="button"
                            class="flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="submitPropertyType">{{ __('admin.finish_setup') }} &rarr;</span>
                            <span wire:loading wire:target="submitPropertyType">{{ __('admin.please_wait') }}</span>
                        </button>
                    @endif
                </div>
            </div>
            {{-- end footer nav --}}

        </div>
        {{-- end right content --}}

    </div>
    @endif
    {{-- end full-screen phases --}}

</div>
