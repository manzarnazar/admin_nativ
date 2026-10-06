<div class="min-h-screen">

    {{-- ═══════════════════════════════════════════════════════════════════════
         STEP 1 — Admin Account (centered card layout)
         ═══════════════════════════════════════════════════════════════════════ --}}
    @if ($currentStep === 1)
    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-12">

        {{-- Logo --}}
        <div class="mb-8">
            <!-- <div class="h-8 w-36 rounded bg-blue-200"></div> -->
        </div>

        <div class="w-full max-w-md">

            {{-- Heading --}}
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-bold text-gray-900">eStay Setup</h1>
                <p class="mt-2 text-sm text-gray-500">Create your admin account to get started.</p>
            </div>

            {{-- Form Card --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                <div class="space-y-5">

                    {{-- Name --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                            </div>
                            <input wire:model="name" type="text" placeholder="John Doe"
                                class="block w-full rounded-lg border py-2.5 pl-10 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('name') ? 'border-red-400' : 'border-gray-300' }}" />
                        </div>
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                            </div>
                            <input wire:model="email" type="email" placeholder="admin@example.com"
                                class="block w-full rounded-lg border py-2.5 pl-10 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('email') ? 'border-red-400' : 'border-gray-300' }}" />
                        </div>
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Phone --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Phone <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-2">
                            {{-- Country Code Selector --}}
                            <div class="relative w-28 flex-shrink-0" x-data="{ open: false }">
                                <button type="button" @click="open = !open"
                                    class="flex w-full items-center justify-between rounded-lg border py-2.5 pl-3 pr-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('dialCode') ? 'border-red-400' : 'border-gray-300' }}">
                                    @php
                                    $selected = $countries->firstWhere('iso2', $countryCode);
                                    @endphp
                                    <span class="flex items-center gap-1.5">
                                        <span>{{ $selected?->emoji ?? '🇮🇳' }}</span>
                                        <span class="font-medium text-gray-900">{{ $dialCode ?? '+91' }}</span>
                                    </span>
                                    <svg class="h-4 w-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                {{-- Dropdown --}}
                                <div x-show="open" @click.away="open = false" x-cloak
                                    class="absolute z-50 mt-1 max-h-60 w-64 overflow-y-auto rounded-xl border border-gray-200 bg-white py-1 shadow-xl">
                                    @foreach ($countries as $country)
                                    <button type="button"
                                        wire:click="$set('dialCode', '+{{ $country->phonecode }}'); $set('countryCode', '{{ $country->iso2 }}'); open = false"
                                        class="flex w-full items-center gap-3 px-4 py-2 text-left text-sm hover:bg-blue-50">
                                        <span class="text-base">{{ $country->emoji }}</span>
                                        <span class="flex-1 truncate text-gray-700">{{ $country->name }}</span>
                                        <span class="font-medium text-gray-400">+{{ $country->phonecode }}</span>
                                    </button>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Phone Number Input --}}
                            <div class="relative flex-1">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 6.75Z" />
                                    </svg>
                                </div>
                                <input wire:model="phone" type="tel" placeholder="1234567890"
                                    x-on:input="$event.target.value = $event.target.value.replace(/[^0-9]/g, '')"
                                    class="block w-full rounded-lg border py-2.5 pl-10 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('phone') ? 'border-red-400' : 'border-gray-300' }}" />
                            </div>
                        </div>
                        @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('dialCode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Password --}}
                    <div x-data="{ revealed: false }">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <input wire:model="password" x-bind:type="revealed ? 'text' : 'password'" placeholder="Min. 8 characters"
                                class="block w-full rounded-lg border py-2.5 pl-10 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('password') ? 'border-red-400' : 'border-gray-300' }}" />
                            <button type="button" x-on:click="revealed = !revealed" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                                <svg x-show="!revealed" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <svg x-show="revealed" x-cloak class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Confirm Password --}}
                    <div x-data="{ revealed: false }">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Confirm Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <input wire:model="passwordConfirmation" x-bind:type="revealed ? 'text' : 'password'" placeholder="Repeat your password"
                                class="block w-full rounded-lg border py-2.5 pl-10 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('passwordConfirmation') ? 'border-red-400' : 'border-gray-300' }}" />
                            <button type="button" x-on:click="revealed = !revealed" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                                <svg x-show="!revealed" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <svg x-show="revealed" x-cloak class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        @error('passwordConfirmation') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                </div>

                {{-- Next Button --}}
                <div class="mt-6">
                    <button wire:click="nextStep" wire:loading.attr="disabled" type="button"
                        class="flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60">
                        <span wire:loading.remove wire:target="nextStep">Next: System Mode &rarr;</span>
                        <span wire:loading wire:target="nextStep">Processing&hellip;</span>
                    </button>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-gray-400">&copy; {{ date('Y') }} eStay. All rights reserved.</p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         STEPS 2+ — Two-column layout (sidebar + content)
         ═══════════════════════════════════════════════════════════════════════ --}}
    @else
    <div class="flex h-screen bg-white">

        {{-- ── Left Sidebar ─────────────────────────────────────────── --}}
        <div class="flex w-56 flex-shrink-0 flex-col border-r border-gray-200">

            {{-- Logo --}}
            <div class="px-5 pb-4 pt-5">
                <div class="h-8 w-36 rounded bg-blue-200"></div>
            </div>

            {{-- Sidebar Header --}}
            <div class="border-b border-gray-100 px-5 pb-5">
                <h2 class="text-sm font-bold text-gray-900">Initial Setup</h2>
                <p class="mt-1 text-xs leading-relaxed text-gray-500">Choose how you want to manage your properties.</p>
            </div>

            {{-- Step Navigation --}}
            @php
            $configSteps = [
            2 => ['name' => 'Setup Mode', 'description' => 'System Structure'],
            3 => ['name' => 'Select Countries', 'description' => 'Operating Countries'],
            4 => ['name' => 'Property Type', 'description' => 'Property Category'],
            ];
            @endphp

            <nav class="flex-1 space-y-1 px-5 py-5">
                @foreach ($configSteps as $stepNumber => $step)
                @php
                $isActive = $currentStep === $stepNumber;
                $isCompleted = $currentStep > $stepNumber;
                @endphp
                <div class="flex items-start gap-3 py-2">
                    {{-- Circle indicator --}}
                    <div class="mt-0.5 flex-shrink-0">
                        @if ($isCompleted)
                        <div class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-600">
                            <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        @elseif ($isActive)
                        <div class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-600">
                            <div class="h-2 w-2 rounded-full bg-white"></div>
                        </div>
                        @else
                        <div class="h-5 w-5 rounded-full border-2 border-gray-300"></div>
                        @endif
                    </div>
                    {{-- Step text --}}
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
                <p class="text-xs text-gray-400">&copy; {{ date('Y') }} eStay. All rights reserved.</p>
            </div>
        </div>

        {{-- ── Right Content Area ───────────────────────────────────── --}}
        <div class="flex flex-1 flex-col overflow-hidden">

            {{-- Scrollable Content --}}
            <div class="flex-1 overflow-y-auto p-10">

                {{-- ─── Step 2: System Mode ─────────────────────────── --}}
                @if ($currentStep === 2)
                <div class="w-full">

                    {{-- Step Title --}}
                    <h1 class="text-xl font-bold text-gray-900">Select System Mode</h1>
                    <p class="mt-1 text-sm text-gray-500">How will properties be managed on this platform?</p>

                    {{-- Warning Banner --}}
                    <div class="mt-6 flex gap-4 rounded-lg border border-red-100 bg-red-50 p-4">
                        <div class="flex-shrink-0">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-500">
                                <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Note</p>
                            <p class="mt-0.5 text-sm text-gray-600">
                                Once you select the <strong>Multi-Property Setup</strong>, this configuration cannot be changed later.
                                You will not be able to switch back to the Single-Property Setup after completing the setup process.
                            </p>
                        </div>
                    </div>

                    {{-- Mode Selection Cards --}}
                    <div class="mt-6 grid grid-cols-2 gap-5">

                        {{-- Multi-Property Card --}}
                        <div wire:click="$set('systemMode', 'multi')"
                            class="cursor-pointer rounded-xl border p-5 transition-colors {{ $systemMode === 'multi' ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200 hover:border-gray-300' }}">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    {{-- Multi-Property Icon --}}
                                    <svg class="h-10 w-10 flex-shrink-0 text-gray-600" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="2" y="17" width="15" height="20" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                                        <rect x="5" y="21" width="3.5" height="3.5" rx="0.5" fill="currentColor" />
                                        <rect x="11" y="21" width="3.5" height="3.5" rx="0.5" fill="currentColor" />
                                        <rect x="5" y="27" width="3.5" height="3.5" rx="0.5" fill="currentColor" />
                                        <rect x="7" y="31" width="5" height="6" rx="0.5" fill="currentColor" />
                                        <rect x="2" y="10" width="15" height="8" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                                        <rect x="23" y="13" width="15" height="24" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                                        <rect x="26" y="18" width="3.5" height="3.5" rx="0.5" fill="currentColor" />
                                        <rect x="31" y="18" width="3.5" height="3.5" rx="0.5" fill="currentColor" />
                                        <rect x="26" y="24" width="3.5" height="3.5" rx="0.5" fill="currentColor" />
                                        <rect x="31" y="24" width="3.5" height="3.5" rx="0.5" fill="currentColor" />
                                        <rect x="28" y="30" width="5" height="7" rx="0.5" fill="currentColor" />
                                    </svg>
                                    <span class="text-sm font-semibold text-gray-900">Multi-Property Setup</span>
                                </div>
                                {{-- Radio Indicator --}}
                                @if ($systemMode === 'multi')
                                <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-blue-600">
                                    <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                @else
                                <div class="h-5 w-5 flex-shrink-0 rounded-full border-2 border-gray-300"></div>
                                @endif
                            </div>
                            <p class="mt-3 text-sm leading-relaxed text-gray-500">
                                This setup is for platforms that allow multiple property owners or partners to list their properties. Admin reviews and approves partner properties, manages compliance, and earns revenue through a commission-based model. Best suited for hotel marketplaces and multi-owner platforms.
                            </p>
                        </div>

                        {{-- Single-Property Card --}}
                        <div wire:click="$set('systemMode', 'single')"
                            class="cursor-pointer rounded-xl border p-5 transition-colors {{ $systemMode === 'single' ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200 hover:border-gray-300' }}">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    {{-- Single-Property Icon --}}
                                    <svg class="h-10 w-10 flex-shrink-0 text-gray-600" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="7" y="14" width="26" height="23" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                                        <path d="M7 14L20 6L33 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <rect x="12" y="19" width="5" height="5" rx="0.5" fill="currentColor" />
                                        <rect x="23" y="19" width="5" height="5" rx="0.5" fill="currentColor" />
                                        <rect x="12" y="27" width="5" height="5" rx="0.5" fill="currentColor" />
                                        <rect x="23" y="27" width="5" height="5" rx="0.5" fill="currentColor" />
                                        <rect x="17" y="30" width="6" height="7" rx="0.5" fill="currentColor" />
                                    </svg>
                                    <span class="text-sm font-semibold text-gray-900">Single-Property Setup</span>
                                </div>
                                {{-- Radio Indicator --}}
                                @if ($systemMode === 'single')
                                <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-blue-600">
                                    <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                @else
                                <div class="h-5 w-5 flex-shrink-0 rounded-full border-2 border-gray-300"></div>
                                @endif
                            </div>
                            <p class="mt-3 text-sm leading-relaxed text-gray-500">
                                This setup is for a single owner managing one brand with multiple branches across cities. Admin controls all operations, pricing, and bookings directly, with no partner or commission flow. Ideal for hotel chains or standalone businesses.
                            </p>
                        </div>

                    </div>
                    {{-- End Mode Cards --}}

                </div>
                @endif
                {{-- End Step 2 --}}

                {{-- ─── Step 3: Select Country ──────────────────────── --}}
                @if ($currentStep === 3)
                <div class="w-full">

                    {{-- Step Title + Search Bar --}}
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">Select Countries</h1>
                            <p class="mt-1 text-sm text-gray-500">
                                Select the countries where your business operates.
                                @if (count($selectedCountries) > 0)
                                <span class="font-medium text-blue-600">{{ count($selectedCountries) }} selected</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-shrink-0 items-center gap-2">
                            <input
                                wire:model.live.debounce.300ms="countrySearch"
                                type="text"
                                placeholder="Search Country"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                            <button type="button"
                                class="flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                                Search
                            </button>
                        </div>
                    </div>

                    {{-- Country Grid --}}
                    <div class="mt-6 grid grid-cols-4 gap-4">
                        @forelse ($countries as $country)
                        @php $isSelected = in_array($country->id, $selectedCountries); @endphp
                        <div
                            wire:click="toggleCountry({{ $country->id }})"
                            class="cursor-pointer rounded-lg border p-4 transition-colors {{ $isSelected ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200 hover:border-gray-300' }}">
                            {{-- Top row: flag + checkbox --}}
                            <div class="flex items-start justify-between">
                                <span class="text-4xl leading-none">{{ $country->emoji }}</span>
                                {{-- Checkbox indicator --}}
                                @if ($isSelected)
                                <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded border-2 border-blue-600 bg-blue-600">
                                    <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                @else
                                <div class="h-5 w-5 flex-shrink-0 rounded border-2 border-gray-300"></div>
                                @endif
                            </div>

                            {{-- Country name --}}
                            <p class="mt-3 text-sm font-semibold text-gray-900">{{ $country->name }}</p>

                            {{-- Currency --}}
                            <p class="mt-0.5 truncate text-xs text-gray-400">
                                {{ $country->currency_symbol ? $country->currency_symbol . ' ' : '' }}{{ $country->currency }} &mdash; {{ $country->currency_name }}
                            </p>
                        </div>
                        @empty
                        <div class="col-span-4 py-12 text-center text-sm text-gray-400">
                            No countries found for "{{ $countrySearch }}".
                        </div>
                        @endforelse
                    </div>

                    @error('selectedCountries')
                    <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                </div>
                @endif
                {{-- End Step 3 --}}

                {{-- ─── Step 4: Property Type ─────────────────────────── --}}
                @if ($currentStep === 4)
                <div class="w-full">

                    {{-- Step Title --}}
                    <h1 class="text-xl font-bold text-gray-900">Property Type</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        @if ($systemMode === 'multi')
                        Select all property types your platform will support. Partners will choose one type when they register.
                        @if (count($selectedPropertyTypes) > 0)
                        <span class="font-medium text-blue-600">{{ count($selectedPropertyTypes) }} selected</span>
                        @endif
                        @else
                        What type of property will you manage on this platform?
                        @endif
                    </p>

                    {{-- Property Type Grid --}}
                    <div class="mt-6 grid grid-cols-4 gap-4">
                        @foreach ($propertyTypes as $type)

                        @if ($systemMode === 'multi')
                        {{-- Multi mode: checkbox style, multi-select --}}
                        @php $isSelected = in_array($type->id, $selectedPropertyTypes); @endphp
                        <div
                            wire:click="togglePropertyType({{ $type->id }})"
                            class="cursor-pointer rounded-lg border p-4 transition-colors {{ $isSelected ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200 hover:border-gray-300' }}">
                            <div class="flex items-start justify-between">
                                <img src="{{ $type->icon_url }}" alt="{{ $type->name }}" class="h-10 w-10 object-contain" />
                                @if ($isSelected)
                                <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded border-2 border-blue-600 bg-blue-600">
                                    <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                @else
                                <div class="h-5 w-5 flex-shrink-0 rounded border-2 border-gray-300"></div>
                                @endif
                            </div>
                            <p class="mt-3 text-sm font-semibold text-gray-900">{{ $type->name }}</p>
                        </div>

                        @else
                        {{-- Single mode: radio style, single-select --}}
                        @php $isSelected = $selectedPropertyType === (string) $type->id; @endphp
                        <div
                            wire:click="$set('selectedPropertyType', '{{ $type->id }}')"
                            class="cursor-pointer rounded-lg border p-4 transition-colors {{ $isSelected ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200 hover:border-gray-300' }}">
                            <div class="flex items-start justify-between">
                                <img src="{{ $type->icon_url }}" alt="{{ $type->name }}" class="h-10 w-10 object-contain" />
                                @if ($isSelected)
                                <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border-2 border-blue-600 bg-blue-600">
                                    <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                @else
                                <div class="h-5 w-5 flex-shrink-0 rounded-full border-2 border-gray-300"></div>
                                @endif
                            </div>
                            <p class="mt-3 text-sm font-semibold text-gray-900">{{ $type->name }}</p>
                        </div>
                        @endif

                        @endforeach

                        {{-- Add Property Type Card --}}
                        <div
                            wire:click="openAddPropertyTypeModal"
                            class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-blue-200 bg-blue-50 p-4 transition-colors hover:border-blue-300 hover:bg-blue-100">
                            <p class="text-center text-sm text-gray-600">Don't see your property type?<br>Create a new one.</p>
                            <button type="button" class="mt-3 flex items-center gap-1.5 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                                Add Property Type
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    @if ($systemMode === 'multi')
                    @error('selectedPropertyTypes')
                    <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @else
                    @error('selectedPropertyType')
                    <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @endif

                </div>
                @endif
                {{-- End Step 4 --}}

            </div>
            {{-- End Scrollable Content --}}

            {{-- ── Add Property Type Modal ────────────────────────────── --}}
            @if ($showAddPropertyTypeModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">

                    {{-- Modal Header --}}
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-gray-900">Add New Property Type</h2>
                        <button wire:click="closeAddPropertyTypeModal" type="button" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-5 space-y-4">

                        {{-- Icon Upload --}}
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Property Type Icon <span class="text-red-500">*</span>
                            </label>
                            <div class="flex flex-col items-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-6">
                                @if ($newPropertyTypeIcon)
                                <img src="{{ $newPropertyTypeIcon->temporaryUrl() }}" alt="Preview" class="mb-2 h-12 w-12 object-contain" />
                                @else
                                <svg class="mb-2 h-8 w-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                @endif
                                <p class="text-sm text-gray-500">
                                    Drag and Drop file here or
                                    <label class="cursor-pointer font-medium text-blue-600 hover:text-blue-700">
                                        Choose File
                                        <input wire:model="newPropertyTypeIcon" type="file" accept=".png,.svg" class="hidden" />
                                    </label>
                                </p>
                            </div>
                            <div class="mt-1 flex justify-between text-xs text-gray-400">
                                <span>Maximum Size: 5MB</span>
                                <span>Supported Files: PNG/SVG</span>
                            </div>
                            @error('newPropertyTypeIcon') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Name --}}
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Property Type Name <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="newPropertyTypeName" type="text" placeholder="Enter Property Type Name"
                                class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                            @error('newPropertyTypeName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Description --}}
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Description <span class="text-red-500">*</span>
                            </label>
                            <textarea wire:model="newPropertyTypeDescription" rows="4" placeholder="Brief description of this property type..."
                                class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
                            @error('newPropertyTypeDescription') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                    </div>

                    {{-- Modal Footer --}}
                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button wire:click="closeAddPropertyTypeModal" type="button"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button wire:click="createPropertyType" wire:loading.attr="disabled" type="button"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="createPropertyType">Create Property Type</span>
                            <span wire:loading wire:target="createPropertyType">Creating&hellip;</span>
                        </button>
                    </div>

                </div>
            </div>
            @endif

            {{-- ── Footer Navigation ────────────────────────────────── --}}
            <div class="flex flex-shrink-0 items-center justify-between border-t border-gray-100 px-10 py-5">
                <div></div>
                <div class="flex items-center gap-3">
                    @if ($currentStep > 1)
                    <button wire:click="previousStep" type="button"
                        class="flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        &larr; Previous
                    </button>
                    @endif
                    <button wire:click="nextStep" wire:loading.attr="disabled" type="button"
                        class="flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                        @if ($currentStep === $totalSteps)
                        <span wire:loading.remove wire:target="nextStep">Finish Setup &rarr;</span>
                        @else
                        <span wire:loading.remove wire:target="nextStep">Next &rarr;</span>
                        @endif
                        <span wire:loading wire:target="nextStep">Processing&hellip;</span>
                    </button>
                </div>
            </div>

        </div>
        {{-- End Right Content Area --}}

    </div>
    @endif

</div>