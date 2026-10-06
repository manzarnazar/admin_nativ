<x-filament-panels::page>
    <form wire:submit="createLanguage">
        {{ $this->form }}

        <div class="mt-4 flex gap-4">
            <x-filament::button type="submit" color="primary" :disabled="! $this::canCreate()">
                {{ __('admin.add_language') }}
            </x-filament::button>
        </div>
    </form>
    
    <div class="mt-8">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
