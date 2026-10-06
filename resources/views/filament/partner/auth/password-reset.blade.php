<div>
    <x-filament-panels::page.simple>

        {{-- ─── Email Step ────────────────────────────────────────────── --}}
        @if ($phase === 'email')
        <div class="space-y-5 pb-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">
                    {{ __('admin.email_address') }} <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.909A2.25 2.25 0 0 1 2.25 6.993V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.909A2.25 2.25 0 0 1 2.25 6.993V6.75" />
                        </svg>
                    </div>
                    <input wire:model="email" type="email" placeholder="e.g, bhavik.estay@gmail.com"
                        class="block w-full rounded-lg border pl-10 pr-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 {{ $errors->has('email') ? 'border-red-400 focus:ring-red-400' : 'border-gray-300' }}" />
                </div>
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <button wire:click="submitEmail" wire:loading.attr="disabled"
                class="mt-2 flex w-full items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="submitEmail">{{ __('admin.send_email') }}</span>
                <span wire:loading wire:target="submitEmail">{{ __('admin.please_wait') }}</span>
            </button>

            <p class="mt-3 text-center text-sm">
                <a href="{{ Filament\Facades\Filament::getPanel('partner')->getLoginUrl() }}" wire:navigate
                    class="flex items-center justify-center gap-2 font-medium text-gray-700 hover:text-gray-900">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    {{ __('admin.back_to_sign_in') }}
                </a>
            </p>
        </div>
        @endif
        {{-- end email step --}}


        {{-- ─── OTP Step ────────────────────────────────────────────────── --}}
        @if ($phase === 'otp')
        <div x-data="{
            digits: ['','','','','',''],
            inputs: [],
            seconds: 25,
            countdownInterval: null,
            init() {
                this.inputs = Array.from(this.$el.querySelectorAll('[data-digit]'));
                this.startCountdown();
            },
            startCountdown() {
                this.seconds = 25;
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

            <button wire:click="verifyOtp" wire:loading.attr="disabled"
                class="flex w-full items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="verifyOtp">{{ __('admin.verify_code') }}</span>
                <span wire:loading wire:target="verifyOtp">{{ __('admin.please_wait') }}</span>
            </button>

            <p class="text-center text-sm text-gray-700 font-medium">
                <template x-if="seconds > 0">
                    <span>{{ __('admin.resend_code_in') }} <span class="text-primary-600" x-text="seconds + 's'"></span></span>
                </template>
                <template x-if="seconds === 0">
                    <button type="button" x-on:click="resend()" class="font-semibold text-primary-600 hover:underline">
                        {{ __('admin.resend_code') }}
                    </button>
                </template>
            </p>

        </div>
        @endif
        {{-- end OTP step --}}


        {{-- ─── Password Step ────────────────────────────────────────────── --}}
        @if ($phase === 'password')
        <div class="space-y-5 pb-2">

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">
                    {{ __('admin.new_password') }} <span class="text-red-500">*</span>
                </label>
                <div x-data="{ show: false }" class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </div>
                    <input wire:model="password" :type="show ? 'text' : 'password'" placeholder="••••••"
                        class="block w-full rounded-lg border pl-10 pr-10 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 {{ $errors->has('password') ? 'border-red-400 focus:ring-red-400' : 'border-gray-300' }}" />
                    <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                        <svg x-show="!show" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                        <svg x-show="show" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                </div>
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">
                    {{ __('admin.confirm_password') }} <span class="text-red-500">*</span>
                </label>
                <div x-data="{ show: false }" class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </div>
                    <input wire:model="passwordConfirmation" :type="show ? 'text' : 'password'" placeholder="••••••"
                        class="block w-full rounded-lg border pl-10 pr-10 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 {{ $errors->has('passwordConfirmation') ? 'border-red-400 focus:ring-red-400' : 'border-gray-300' }}" />
                    <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                        <svg x-show="!show" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                        <svg x-show="show" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                </div>
                @error('passwordConfirmation') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <button wire:click="submitPassword" wire:loading.attr="disabled"
                class="mt-4 flex w-full items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="submitPassword">{{ __('admin.reset_password') }}</span>
                <span wire:loading wire:target="submitPassword">{{ __('admin.please_wait') }}</span>
            </button>

        </div>
        @endif
        {{-- end password step --}}

    </x-filament-panels::page.simple>
</div>
