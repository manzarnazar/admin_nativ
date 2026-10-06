<x-filament-panels::page>

    {{-- Platform Warning Banner --}}
    @if ($platformWarning)
        <div class="rounded-xl px-5 py-4 flex items-start gap-3" style="background-color: #fff8e6;">
            <div class="mt-0.5 flex-shrink-0">
                <svg class="w-5 h-5" style="color: #f59e0b;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold" style="color: #92400e;">
                    @if ($platformWarning === 'web')
                        {{ __('admin.no_web_homepage_sections_found') }}
                    @elseif ($platformWarning === 'app')
                        {{ __('admin.no_app_homepage_sections_found') }}
                    @else
                        {{ __('admin.no_homepage_sections_found') }}
                    @endif
                </p>
                <p class="text-sm mt-0.5" style="color: #78350f;">
                    @if ($platformWarning === 'web')
                        {{ __('admin.no_web_sections_description') }}
                    @elseif ($platformWarning === 'app')
                        {{ __('admin.no_app_sections_description') }}
                    @else
                        {{ __('admin.no_web_app_sections_description') }}
                    @endif
                </p>
            </div>
        </div>
    @endif

    {{-- Country / Global Tabs --}}
    <div class="flex items-center justify-between">
        <div class="inline-flex gap-1 rounded-xl p-1 dark:bg-gray-800" style="background-color: var(--brand-primary-light);">
            <button
                wire:click="switchTab('country')"
                class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
                style="{{ $activeTab === 'country' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
            >
                <x-heroicon-o-map-pin class="h-4 w-4" />
                {{ $countryName }}
            </button>
            <button
                wire:click="switchTab('global')"
                class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
                style="{{ $activeTab === 'global' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
            >
                <x-heroicon-o-globe-alt class="h-4 w-4" />
                {{ __('admin.global') }}
            </button>
        </div>
    </div>

    {{ $this->table }}

    <x-filament-actions::modals />
</x-filament-panels::page>
