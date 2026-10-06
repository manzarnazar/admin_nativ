@php
    $livewire = app('livewire')->current();
    $table = $livewire instanceof \App\Filament\Contracts\IsReportPage ? $livewire->getTable() : null;
    $soleDateRangeFilterName = $table ? $livewire->soleDateRangeFilterName($table) : null;
@endphp

{{-- The compact pill in inline-filters-trigger.blade.php replaces this whole panel when the
report's only filter is the date range — nothing to toggle open, so nothing to render here. --}}
@if ($table && ! $soleDateRangeFilterName && count($table->getFilters()) > 0)
    <div
        x-cloak
        x-show="areFiltersOpen"
        x-bind:class="{ 'fi-open': areFiltersOpen }"
        class="fi-ta-filters-above-content-ctn fi-inline-filters-panel"
    >
        <x-filament-tables::filters
            :apply-action="$table->getFiltersApplyAction()"
            :form="$table->getFiltersForm()"
            :reset-action-position="$table->getFiltersResetActionPosition()"
        />
    </div>
@endif
