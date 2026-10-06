<div x-data class="inline-flex gap-1 rounded-xl p-1" style="background-color: var(--brand-primary-light);">
    <button
        wire:click="$set('activeView', 'calendar')"
        class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
        x-bind:style="$wire.activeView === 'calendar' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;'"
    >
        <x-heroicon-o-table-cells class="h-4 w-4" />
        {{ __('admin.calendar_view') }}
    </button>
    <button
        wire:click="$set('activeView', 'grid')"
        class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
        x-bind:style="$wire.activeView === 'grid' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;'"
    >
        <x-heroicon-o-squares-2x2 class="h-4 w-4" />
        {{ __('admin.room_grid_view') }}
    </button>
</div>
