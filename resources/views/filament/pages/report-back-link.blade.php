@php
    $livewire = app('livewire')->current();
@endphp

@if ($livewire instanceof \App\Filament\Contracts\IsReportPage)
    <div class="px-4 pt-4 sm:px-6 lg:px-8">
        <a
            href="{{ \App\Filament\Pages\AllReports::getUrl() }}"
            wire:navigate
            class="inline-flex w-fit items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            {{ __('admin.back_to_all_reports') }}
        </a>
    </div>
@endif
