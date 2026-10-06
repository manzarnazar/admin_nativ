<div class="fi-topbar-ctn">
    <style>
        @media (max-width: 1023px) {
            .fi-topbar-end {
                position: relative;
            }
            .partner-topbar-notif-wrapper {
                position: static;
            }
            .partner-topbar-notif-dd {
                right: 0 !important;
                left: auto !important;
                max-width: calc(100vw - 16px);
            }
        }
    </style>
    @php
    $hasNavigation = filament()->hasNavigation();
    @endphp

    <nav class="fi-topbar">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_START) }}

        {{-- Mobile sidebar toggle --}}
        @if ($hasNavigation)
        <x-filament::icon-button
            color="gray"
            :icon="\Filament\Support\Icons\Heroicon::OutlinedBars3"
            icon-size="lg"
            :label="__('filament-panels::layout.actions.sidebar.expand.label')"
            x-cloak
            x-data="{}"
            x-on:click="$store.sidebar.open()"
            x-show="! $store.sidebar.isOpen"
            class="fi-topbar-open-sidebar-btn" />

        <x-filament::icon-button
            color="gray"
            :icon="\Filament\Support\Icons\Heroicon::OutlinedXMark"
            icon-size="lg"
            :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
            x-cloak
            x-data="{}"
            x-on:click="$store.sidebar.close()"
            x-show="$store.sidebar.isOpen"
            class="fi-topbar-close-sidebar-btn" />
        @endif

        {{-- Left: sidebar collapse toggle (desktop) --}}
        <div class="fi-topbar-start">
            <button
                type="button"
                x-data="{}"
                x-on:click="$store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open()"
                class="p-2 border topbar-btn rounded-lg outline-none transition duration-75 hidden items-center justify-center -ms-1.5 lg:flex">
                <svg
                    class="h-6 w-6 text-[#555555] transition-transform duration-200"
                    :class="{ 'rotate-180': ! $store.sidebar.isOpen }"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </button>

            {{-- Property switcher (desktop) --}}
            @if ($this->topbarControls['property'] && $this->currentProperty)
            <div x-data="{ openProperty: false }" x-on:livewire:navigating.window="openProperty = false" class="relative ms-2 hidden lg:block">
                <button
                    type="button"
                    x-on:click="openProperty = !openProperty"
                    class="flex items-center gap-3 px-4 py-3 border topbar-btn rounded-lg text-[#555555] text-base font-normal leading-6 transition">
                    <div class="w-5 h-5 text-[#555555]">
                        {!! file_get_contents(resource_path('svg/Buildings.svg')) !!}
                    </div>
                    <span class="truncate max-w-[18rem] lg:max-w-[22rem]">{{ $this->currentProperty->name }}</span>
                    @if ($this->properties->count() > 1)
                    <svg class="w-5 h-5 text-[#555555] transition" :class="{ 'rotate-180': openProperty }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                    @endif
                </button>

                @if ($this->properties->count() > 1)
                <div
                    x-show="openProperty"
                    x-on:click.outside="openProperty = false"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute left-0 z-50 mt-2 w-80 origin-top-left rounded-lg border border-[#EDEDED] bg-white py-1 shadow-lg"
                    style="display: none;">
                    @foreach ($this->properties as $prop)
                    <button
                        type="button"
                        wire:click="switchProperty({{ $prop->id }})"
                        class="flex w-full items-center gap-x-2 px-3 py-2 text-sm transition hover:bg-gray-50 {{ $prop->id === $this->currentProperty->id ? 'topbar-dd-active' : 'text-gray-700' }}">
                        <x-heroicon-o-building-office class="h-4 w-4 shrink-0 text-gray-400" />
                        <span class="text-left break-words">{{ $prop->name }}</span>
                        @if ($prop->id === $this->currentProperty->id)
                        <svg class="ms-auto h-4 w-4 shrink-0 topbar-dd-check" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        @endif
                    </button>
                    @endforeach
                </div>
                @endif
            </div>
            @endif
        </div>

        {{-- Right side --}}
        <div class="fi-topbar-end flex items-center gap-x-3">

            {{-- ═══════════════════════════════════════════════════════════════
                 MOBILE ONLY: compact language + settings (country)
            ═══════════════════════════════════════════════════════════════ --}}

            {{-- Mobile: compact language button --}}
            @if ($this->currentLanguage && $this->languages->count() > 0)
            <div x-data="{ openLangMobile: false }" x-on:livewire:navigating.window="openLangMobile = false" class="relative lg:hidden">
                <button
                    type="button"
                    x-on:click="openLangMobile = !openLangMobile"
                    class="flex items-center gap-1 px-2.5 py-2 border topbar-btn rounded-lg text-[#555555] transition">
                    <span class="text-xs font-semibold tracking-wide">{{ strtoupper($this->currentLanguage->code) }}</span>
                    <svg class="w-3 h-3 text-[#555555] transition" :class="{ 'rotate-180': openLangMobile }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
                <div
                    x-show="openLangMobile"
                    x-on:click.outside="openLangMobile = false"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute left-0 z-50 mt-2 w-40 origin-top-left rounded-lg border border-[#EDEDED] bg-white py-1 shadow-lg"
                    style="display: none;">
                    @foreach ($this->languages as $lang)
                    <button
                        type="button"
                        wire:click="switchLanguage('{{ $lang->code }}')"
                        x-on:click="openLangMobile = false"
                        class="flex w-full items-center gap-x-2 px-3 py-2 text-sm transition hover:bg-gray-50 {{ $lang->code === $this->currentLanguage->code ? 'topbar-dd-active' : 'text-gray-700' }}">
                        <span>{{ $lang->name }}</span>
                        @if ($lang->code === $this->currentLanguage->code)
                        <svg class="ms-auto h-4 w-4 shrink-0 topbar-dd-check" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        @endif
                    </button>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Mobile: country settings button --}}
            @if ($this->topbarControls['country'] && $this->currentCountry && $this->partnerCountries->count() > 0)
            <div
                class="lg:hidden"
                x-data="{ openMobileCountry: false }"
                x-on:livewire:navigating.window="openMobileCountry = false">
                <button
                    type="button"
                    x-on:click="openMobileCountry = !openMobileCountry"
                    class="relative flex items-center justify-center rounded-lg p-2.5 border topbar-btn transition">
                    <svg class="h-5 w-5 text-[#555555]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                    </svg>
                </button>
                <div
                    x-show="openMobileCountry"
                    x-on:click.outside="openMobileCountry = false"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-xl border border-[#EDEDED] bg-white shadow-xl overflow-hidden"
                    style="display: none; top: 56px;">
                    <div class="px-3 py-3">
                        <p class="px-1 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('admin.country') }}</p>
                        @foreach ($this->partnerCountries as $country)
                        <button
                            type="button"
                            wire:click="switchCountry({{ $country->id }})"
                            x-on:click="openMobileCountry = false"
                            class="flex w-full items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm transition hover:bg-gray-50 {{ $country->id === $this->currentCountry->id ? 'topbar-dd-active' : 'text-gray-700' }}">
                            <img
                                src="/assets/flags/{{ strtolower($country->iso_code) }}.svg"
                                alt="{{ $country->name }}"
                                class="h-4 w-6 rounded-sm object-cover shrink-0" />
                            <span class="font-medium">{{ $country->name }}</span>
                            @if ($country->id === $this->currentCountry->id)
                            <svg class="ms-auto h-4 w-4 shrink-0 topbar-dd-check" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            @endif
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- Mobile: property settings button --}}
            @if ($this->topbarControls['property'] && $this->currentProperty && $this->properties->count() > 1)
            <div
                class="lg:hidden"
                x-data="{ openMobileProperty: false }"
                x-on:livewire:navigating.window="openMobileProperty = false">
                <button
                    type="button"
                    x-on:click="openMobileProperty = !openMobileProperty"
                    class="relative flex items-center justify-center rounded-lg p-2.5 border topbar-btn transition">
                    <div class="h-5 w-5 text-[#555555]">
                        {!! file_get_contents(resource_path('svg/Buildings.svg')) !!}
                    </div>
                </button>
                <div
                    x-show="openMobileProperty"
                    x-on:click.outside="openMobileProperty = false"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-xl border border-[#EDEDED] bg-white shadow-xl overflow-hidden"
                    style="display: none; top: 56px;">
                    <div class="px-3 py-3">
                        <p class="px-1 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('admin.property') }}</p>
                        @foreach ($this->properties as $prop)
                        <button
                            type="button"
                            wire:click="switchProperty({{ $prop->id }})"
                            x-on:click="openMobileProperty = false"
                            class="flex w-full items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm transition hover:bg-gray-50 {{ $prop->id === $this->currentProperty->id ? 'topbar-dd-active' : 'text-gray-700' }}">
                            <x-heroicon-o-building-office class="h-4 w-4 shrink-0 text-gray-400" />
                            <span class="text-left break-words font-medium">{{ $prop->name }}</span>
                            @if ($prop->id === $this->currentProperty->id)
                            <svg class="ms-auto h-4 w-4 shrink-0 topbar-dd-check" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            @endif
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════════════
                 DESKTOP: Language + Country + Notification + Profile
            ═══════════════════════════════════════════════════════════════ --}}

            {{-- Language switcher (desktop) --}}
            @if ($this->currentLanguage && $this->languages->count() > 0)
            <div x-data="{ openLang: false }" x-on:livewire:navigating.window="openLang = false" class="relative hidden lg:block">
                <button
                    type="button"
                    x-on:click="openLang = !openLang"
                    class="flex items-center gap-3 px-4 py-3 border topbar-btn rounded-lg text-[#555555] text-base font-normal leading-6 transition">
                    <span>{{ strtoupper($this->currentLanguage->code) }}</span>
                    <svg class="w-5 h-5 text-[#555555] transition" :class="{ 'rotate-180': openLang }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
                <div
                    x-show="openLang"
                    x-on:click.outside="openLang = false"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 z-50 mt-2 w-40 origin-top-right rounded-lg border border-[#EDEDED] bg-white py-1 shadow-lg"
                    style="display: none;">
                    @foreach ($this->languages as $lang)
                    <button
                        type="button"
                        wire:click="switchLanguage('{{ $lang->code }}')"
                        class="flex w-full items-center gap-x-2 px-3 py-2 text-sm transition hover:bg-gray-50 {{ $lang->code === $this->currentLanguage->code ? 'topbar-dd-active' : 'text-gray-700' }}">
                        <span>{{ $lang->name }}</span>
                        @if ($lang->code === $this->currentLanguage->code)
                        <svg class="ms-auto h-4 w-4 shrink-0 topbar-dd-check" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        @endif
                    </button>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Country switcher (desktop) --}}
            @if ($this->topbarControls['country'] && $this->currentCountry)
            <div x-data="{ openCountry: false }" x-on:livewire:navigating.window="openCountry = false" class="relative hidden lg:block">
                <button
                    type="button"
                    x-on:click="openCountry = !openCountry"
                    class="flex items-center gap-3 px-4 py-3 border topbar-btn rounded-lg text-[#555555] text-base font-normal leading-6 transition">
                    <img
                        src="/assets/flags/{{ strtolower($this->currentCountry->iso_code) }}.svg"
                        alt="{{ $this->currentCountry->name }}"
                        class="h-4 w-6 rounded-sm object-cover" />
                    <span>{{ $this->currentCountry->name }}</span>
                    @if ($this->partnerCountries->count() > 1)
                    <svg class="w-5 h-5 text-[#555555] transition" :class="{ 'rotate-180': openCountry }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                    @endif
                </button>

                @if ($this->partnerCountries->count() > 1)
                <div
                    x-show="openCountry"
                    x-on:click.outside="openCountry = false"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-lg border border-[#EDEDED] bg-white py-1 shadow-lg"
                    style="display: none;">
                    @foreach ($this->partnerCountries as $country)
                    <button
                        type="button"
                        wire:click="switchCountry({{ $country->id }})"
                        class="flex w-full items-center gap-x-2 px-3 py-2 text-sm transition hover:bg-gray-50 {{ $country->id === $this->currentCountry->id ? 'topbar-dd-active' : 'text-gray-700' }}">
                        <img
                            src="/assets/flags/{{ strtolower($country->iso_code) }}.svg"
                            alt="{{ $country->name }}"
                            class="h-4 w-6 rounded-sm object-cover" />
                        <span>{{ $country->name }}</span>
                        @if ($country->id === $this->currentCountry->id)
                        <svg class="ms-auto h-4 w-4 shrink-0 topbar-dd-check" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        @endif
                    </button>
                    @endforeach
                </div>
                @endif
            </div>
            @endif

            {{-- Notification Bell --}}
            <div x-data="{ openNotif: false }" x-on:livewire:navigating.window="openNotif = false" class="partner-topbar-notif-wrapper relative">
                <button
                    type="button"
                    x-on:click="openNotif = !openNotif"
                    class="relative flex items-center justify-center rounded-lg p-3 border topbar-btn transition">
                    <svg class="h-7 w-7 text-[#555555] @if ($this->unreadCount > 0) topbar-bell-ringing @endif" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                    @if ($this->unreadCount > 0)
                    <span class="topbar-notif-badge" style="position: absolute; top: -8px; left: 34px; min-width: 20px; height: 20px; padding: 0 5px; background-color: #db3d26; color: white; font-size: 11px; font-weight: 600; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-family: 'Manrope', sans-serif; white-space: nowrap;">
                        {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
                    </span>
                    @endif
                </button>

                {{-- Notification dropdown --}}
                <div
                    x-show="openNotif"
                    x-on:click.outside="openNotif = false"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="partner-topbar-notif-dd absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-lg border border-gray-200 bg-white shadow-lg"
                    style="display: none;">
                    @if ($this->latestNotifications->isEmpty())
                    <div class="px-4 py-6 text-center">
                        <p class="text-sm text-gray-500">{{ __('admin.no_notifications') }}</p>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-2 text-center">
                        <a href="/partner/partner-notifications" wire:navigate style="font-size: 12px; font-weight: 600; color: var(--brand-primary); text-decoration: none;">
                            {{ __('admin.view_all_notifications') }}
                        </a>
                    </div>
                    @else
                    <div class="max-h-72 overflow-y-auto">
                        @foreach ($this->latestNotifications as $notification)
                        @php
                        $isUnread = is_null($notification->pivot->read_at);
                        @endphp
                        <a
                            href="{{ $notification->link ?? '#' }}"
                            wire:navigate
                            wire:click="markAsRead('{{ $notification->id }}')"
                            class="flex gap-3 border-b border-gray-100 px-4 py-3 transition hover:bg-gray-50 {{ $isUnread ? 'topbar-notif-unread' : '' }}">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background-color: {{ $isUnread ? 'var(--brand-primary-light)' : '#f3f4f6' }}; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <x-heroicon-o-bell style="width: 18px; height: 18px; color: {{ $isUnread ? 'var(--brand-primary)' : '#9ca3af' }};" />
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <p style="font-size: 12px; font-weight: 600; color: #111827; margin: 0;">{{ $notification->title }}</p>
                                <p style="font-size: 11px; color: #6b7280; margin: 2px 0 0 0; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">{{ $notification->body ?? '' }}</p>
                            </div>
                            <span style="font-size: 10px; color: #9ca3af; white-space: nowrap; flex-shrink: 0;">{{ $notification->created_at->diffForHumans(short: true) }}</span>
                        </a>
                        @endforeach
                    </div>
                    <div class="flex items-center justify-between border-t border-gray-200 px-4 py-2">
                        <a href="/partner/partner-notifications" wire:navigate style="font-size: 12px; font-weight: 600; color: var(--brand-primary); text-decoration: none;">
                            {{ __('admin.view_all_notifications') }}
                        </a>
                        @if ($this->unreadCount > 0)
                        <button wire:click="markAllAsRead" style="font-size: 11px; color: #6b7280; background: none; border: none; cursor: pointer;">
                            {{ __('admin.mark_all_read') }}
                        </button>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            {{-- Profile / User menu --}}
            @if (filament()->auth()->check())
            @php
            $user = filament()->auth()->user();
            $menuItems = $this->getUserMenuItems();
            $itemsBeforeAndAfterThemeSwitcher = collect($menuItems)
                ->groupBy(fn (\Filament\Actions\Action $item): bool => $item->getSort() < 0, preserveKeys: true)
                ->all();
            $itemsBeforeThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[true] ?? collect();
            $itemsAfterThemeSwitcher  = $itemsBeforeAndAfterThemeSwitcher[false] ?? collect();
            @endphp

            <x-filament::dropdown placement="bottom-end" teleport class="fi-user-menu">
                <x-slot name="trigger">
                    <button
                        type="button"
                        class="flex items-center gap-3 rounded-full px-2 py-1.5 text-start transition hover:bg-gray-50">
                        <div class="border-[#595F65] border-[0.833px] border-solid overflow-hidden rounded-full shrink-0 size-[40px]">
                            <x-filament-panels::avatar.user :user="$user" class="size-full" />
                        </div>
                        <div class="hidden lg:block">
                            <p class="text-base font-semibold leading-6 text-black whitespace-nowrap">
                                {{ filament()->getUserName($user) }}
                            </p>
                            <p class="text-sm font-medium leading-5 text-[#555555] whitespace-nowrap">
                                {{ $user->partner?->business_name ?? __('admin.partner') }}
                            </p>
                        </div>
                        <svg class="hidden w-5 h-5 text-[#555555] lg:block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                </x-slot>

                @if ($itemsBeforeThemeSwitcher->isNotEmpty())
                <x-filament::dropdown.list>
                    @foreach ($itemsBeforeThemeSwitcher as $item)
                    {{ $item }}
                    @endforeach
                </x-filament::dropdown.list>
                @endif

                @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
                <x-filament::dropdown.list>
                    <x-filament-panels::theme-switcher />
                </x-filament::dropdown.list>
                @endif

                @if ($itemsAfterThemeSwitcher->isNotEmpty())
                <x-filament::dropdown.list>
                    @foreach ($itemsAfterThemeSwitcher as $item)
                    {{ $item }}
                    @endforeach
                </x-filament::dropdown.list>
                @endif
            </x-filament::dropdown>
            @endif

        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_END) }}
    </nav>

    <x-filament-actions::modals />
</div>
