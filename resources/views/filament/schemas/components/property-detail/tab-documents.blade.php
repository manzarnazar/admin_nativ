{{--
    Shared "Documentations" tab body. Expects:
    - $property (with `verifiedBy` eager loaded)
    - $documentCount

    The actual documents table is a standalone Livewire TableComponent
    (App\Livewire\PropertyDocumentsTable) rather than a second table() on
    this page — Filament only supports one table per component, and a
    second table() here would share pagination/search state with the
    Rooms & Pricing tab's table.
--}}
<div class="rounded-2xl border border-[#EDEDED] bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
<div class="space-y-6">
    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.documentation') }}</h3>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
        <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900">
                <x-heroicon-o-document-text class="h-5 w-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.documents_uploaded') }}</p>
                <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $documentCount }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900">
                <x-heroicon-o-check-badge class="h-5 w-5 text-green-600 dark:text-green-400" />
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.property_verified_on') }}</p>
                <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $property->verified_at?->format('d M, Y') ?? '—' }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900">
                <x-heroicon-o-user-circle class="h-5 w-5 text-orange-600 dark:text-orange-400" />
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.verified_by') }}</p>
                <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $property->verifiedBy?->name ?? '—' }}</p>
            </div>
        </div>
    </div>

    @livewire('property-documents-table', ['propertyId' => $property->id], key('property-documents-table-'.$property->id))
</div>
</div>
