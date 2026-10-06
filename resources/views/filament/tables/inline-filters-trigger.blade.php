@php
    $livewire = app('livewire')->current();
    $table = $livewire instanceof \App\Filament\Contracts\IsReportPage ? $livewire->getTable() : null;
    $activeCount = $table?->getActiveFiltersCount() ?? 0;
    $soleDateRangeFilterName = $table ? $livewire->soleDateRangeFilterName($table) : null;
@endphp

@if ($soleDateRangeFilterName)
    {{-- A report whose only filter is the date range gets it rendered directly here, compactly,
    instead of behind the funnel-button-and-panel below (inline-filters-panel.blade.php renders
    nothing in this case). Bound straight to the same tableFilters Livewire property Filament's
    own filters form uses, so the filter's ->query()/->indicateUsing() closures — and the native
    "×" indicator chip Filament renders separately for clearing it — keep working unmodified. --}}
    <div class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 dark:border-gray-600 dark:bg-gray-800">
        <x-heroicon-o-calendar class="h-4 w-4 shrink-0 text-gray-400" />
        <input
            type="text"
            wire:model.live="tableFilters.{{ $soleDateRangeFilterName }}.range"
            data-flatpickr-range="true"
            autocomplete="off"
            placeholder="{{ __('admin.date_range') }}"
            class="w-48 border-0 bg-transparent p-0 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0 dark:text-white dark:placeholder-gray-500"
        />
    </div>
@elseif ($table && count($table->getFilters()) > 0)
    {{-- Built as a plain button rather than reusing $table->getFiltersTriggerAction(): its
    styling comes from Filament's own compiled classes, which sit inside the Tailwind v4 @layer
    blocks that have repeatedly beaten plain overrides elsewhere on this page (see theme.css) —
    full control here avoids fighting that again just for a color/chevron toggle. --}}
    <button
        type="button"
        x-on:click="areFiltersOpen = ! areFiltersOpen"
        x-bind:class="areFiltersOpen
            ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700 dark:bg-primary-950/40 dark:text-primary-300'
            : 'border-gray-300 bg-white text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200'"
        class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors cursor-pointer"
    >
        <x-heroicon-o-funnel class="h-4 w-4" />
        {{ __('filament-tables::table.actions.filter.label') }}

        @if ($activeCount > 0)
            <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary-600 px-1 text-xs font-medium text-white">
                {{ $activeCount }}
            </span>
        @endif

        <x-heroicon-m-chevron-down x-bind:class="{ 'rotate-180': areFiltersOpen }" class="h-3.5 w-3.5 transition-transform" />
    </button>
@endif
