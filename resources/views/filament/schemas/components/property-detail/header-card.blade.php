@php
/**
 * Shared header card for the property detail tab pages (admin AllPropertiesView
 * and partner PartnerPropertyView). Expects:
 * - $property (App\Models\Property, with propertyType/refCity/refState loaded)
 * - $backUrl (string)
 * - $showPartner (bool) — admin sees the owning partner's name, partner viewing
 *   their own property does not.
 * - $reviewsAvgRating, $reviewsCount (float|null, int)
 */
$imageUrl = null;
$primaryImage = $property->primaryImages->firstWhere('media_type', 'image') ?? $property->primaryImages->first();
if ($primaryImage) {
    $imageUrl = asset('storage/'.$primaryImage->image_path);
}

$propertyIdLabel = '#PR-'.str_pad((string) $property->id, 4, '0', STR_PAD_LEFT);

$location = implode(' · ', array_filter([
    $property->refCity?->name,
    $property->refState?->name,
]));
@endphp

<div class="-mt-4 mb-3">
    <a href="{{ $backUrl }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
        <x-heroicon-o-arrow-left class="h-4 w-4" />
        {{ __('admin.back') }}
    </a>
</div>

<div class="rounded-2xl border border-[#EDEDED] bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
    <div class="flex items-center gap-4">
        @if ($imageUrl)
        <img src="{{ $imageUrl }}" data-gallery="property-header" class="h-[120px] w-[144px] flex-shrink-0 rounded-lg object-cover cursor-pointer transition hover:opacity-90" alt="">
        @else
        <div class="h-[120px] w-[144px] flex-shrink-0 rounded-lg bg-[var(--brand-primary-light)] dark:bg-blue-900/20"></div>
        @endif

        <div class="flex flex-1 flex-col gap-2">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-xl font-semibold text-gray-950 dark:text-white">{{ $property->name }}</h1>
                <span class="h-1 w-1 rounded-full bg-gray-400"></span>
                <span class="rounded bg-gray-950 px-2 py-1 text-xs font-medium text-white dark:bg-gray-700">
                    {{ $property->propertyType?->name ?? '-' }}
                </span>
                <span @class([
                    'rounded-lg px-2 py-1 text-xs font-medium',
                    'bg-[#E5FAEF] text-[#20B364]' => $property->status?->value === 'active',
                    'bg-[#FEF5E6] text-[#F79E1B]' => $property->status?->value === 'inactive',
                    'bg-[#FBEAEA] text-[#D63031]' => $property->status?->value === 'suspended',
                    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $property->status?->value === 'draft' || ! $property->status,
                ])>
                    {{ $property->status?->label() ?? __('admin.draft') }}
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-map-pin class="h-4 w-4" />
                    {{ $location ?: '-' }}
                </span>
                @if ($showPartner)
                <span class="flex items-center gap-2">
                    <x-heroicon-o-user-circle class="h-4 w-4" />
                    {{ __('admin.partner') }}: {{ $property->partner?->user?->name ?? '-' }}
                </span>
                @endif
            </div>

            <div class="border-t border-gray-100 pt-3 dark:border-gray-700"></div>

            <div class="flex items-center gap-4">
                <span class="font-semibold text-primary-600 dark:text-primary-400">{{ $propertyIdLabel }}</span>
                <span class="flex items-center gap-1">
                    <x-heroicon-s-star class="h-5 w-5 text-yellow-400" />
                    <span class="font-semibold text-gray-950 dark:text-white">{{ $reviewsCount > 0 ? number_format((float) $reviewsAvgRating, 1) : '-' }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">({{ $reviewsCount }} {{ __('admin.reviews') }})</span>
                </span>
            </div>
        </div>
    </div>

    @if ($property->verification_status?->value === 'rejected')
    <div class="mt-4 rounded-lg bg-[#FBEAEA] p-4 dark:bg-red-900/20">
        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('admin.property_rejected') }}</p>
        <p class="text-sm text-[#D63031]">{{ $property->verification_notes }}</p>
    </div>
    @elseif ($property->verification_status?->value === 'correction_requested')
    <div class="mt-4 rounded-lg bg-[#FEF5E6] p-4 dark:bg-amber-900/20">
        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('admin.changes_required') }}</p>
        <p class="text-sm text-[#F79E1B]">{{ $property->verification_notes }}</p>
    </div>
    @endif
</div>

{{-- Tabs --}}
<div class="mt-6 flex items-center gap-1 overflow-x-auto border-b-2 border-[#EDEDED] dark:border-gray-700">
    @foreach ($tabs as $key => $tab)
    <button
        type="button"
        wire:click="switchTab('{{ $key }}')"
        @class([
            'flex shrink-0 items-center gap-2 px-4 py-4 text-sm font-medium transition whitespace-nowrap',
            'border-b-2 border-primary-600 text-primary-600' => $activeTab === $key,
            'text-gray-950 dark:text-white' => $activeTab !== $key,
        ])
    >
        <span class="inline-flex h-5 w-5 shrink-0">{!! $this->getTabIconHtml($tab['icon']) !!}</span>
        {{ $tab['label'] }}
    </button>
    @endforeach
</div>
