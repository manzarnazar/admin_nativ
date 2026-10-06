<div class="space-y-4">
    <div class="flex justify-end">
        <x-filament::button wire:click="mountAction('addOverride')" icon="heroicon-o-plus">
            {{ __('admin.add_partner_override') }}
        </x-filament::button>
    </div>

    @if ($this->getHasOverrides())
        {{ $this->table }}
    @else
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-user-group class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                </div>

                <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('admin.no_partner_overrides') }}
                </h4>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.no_partner_overrides_description') }}
                </p>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
