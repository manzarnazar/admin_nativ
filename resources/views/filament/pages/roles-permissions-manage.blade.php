<x-filament-panels::page>
    @if ($this->getHasRoles())
        {{ $this->table }}
    @else
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-16 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-900/20">
                    <x-heroicon-o-shield-check class="h-7 w-7 text-primary-500" />
                </div>
                <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('admin.roles_not_set_up') }}
                </h4>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.roles_not_set_up_description') }}
                </p>
            </div>
        </div>

        <x-filament-actions::modals />
    @endif
</x-filament-panels::page>
