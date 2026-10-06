<div>
    @if ($this->getHasCategories())
        {{ $this->table }}
    @else
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-tag class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                </div>

                <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                    No Categories Created
                </h4>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    Categories help organize blogs and make content easier to discover.
                    Create categories before publishing your first blog.
                </p>

                <x-filament::button wire:click="mountAction('createCategory')">
                    Create Category
                </x-filament::button>
            </div>
        </div>

        <x-filament-actions::modals />
    @endif
</div>
