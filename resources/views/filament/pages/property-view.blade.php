<x-filament-panels::page>
    @php
        $property = $this->getProperty();
        $tabs = [
            'overview' => ['label' => __('admin.overview'), 'icon' => 'heroicon-o-information-circle'],
            'facilities' => ['label' => __('admin.facilities'), 'icon' => 'heroicon-o-squares-2x2'],
            'rules' => ['label' => __('admin.property_rules'), 'icon' => 'heroicon-o-clipboard-document-list'],
            'location' => ['label' => __('admin.location_and_nearby'), 'icon' => 'heroicon-o-map-pin'],
            'media' => ['label' => __('admin.media'), 'icon' => 'heroicon-o-photo'],
            'documents' => ['label' => __('admin.documents'), 'icon' => 'heroicon-o-document-text'],
        ];
    @endphp

    {{-- Back Link --}}
    <div class="mb-2">
        <a
            href="{{ \App\Filament\Pages\PropertyManage::getUrl() }}"
            wire:navigate
            class="inline-flex items-center gap-1 text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            {{ __('admin.back_to_all_properties') }}
        </a>
    </div>

    {{-- Property Header Card --}}
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-start gap-4 sm:gap-5">
            {{-- Property Image --}}
            <div class="h-24 w-24 shrink-0 overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                @php $thumbnail = $property->primaryImages->firstWhere('media_type', 'image'); @endphp
                @if ($thumbnail)
                    <img
                        src="{{ asset('storage/' . $thumbnail->image_path) }}"
                        alt="{{ $property->name }}"
                        class="h-full w-full object-cover"
                    />
                @else
                    <div class="flex h-full w-full items-center justify-center">
                        <x-heroicon-o-building-office class="h-10 w-10 text-gray-400" />
                    </div>
                @endif
            </div>

            {{-- Property Info --}}
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <h1 class="text-xl font-bold text-gray-950 dark:text-white">
                        {{ $property->name }}
                    </h1>
                    @if ($property->propertyType)
                        <span class="inline-flex items-center rounded-md bg-gray-800 px-2.5 py-1 text-xs font-medium text-white dark:bg-gray-700">
                            {{ $property->propertyType->name }}
                        </span>
                    @endif
                    <span @class([
                        'inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium',
                        'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => $property->status?->value === 'active',
                        'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' => $property->status?->value === 'draft' || !$property->status,
                        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => $property->status?->value === 'inactive',
                    ])>
                        {{ $property->status?->label() ?? 'Draft' }}
                    </span>
                </div>

                <div class="mt-1.5 flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-map-pin class="h-4 w-4" />
                    {{ collect([$property->refCity?->name, $property->refState?->name])->filter()->implode(', ') ?: '-' }}
                </div>

                <div class="mt-2 flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-sm font-semibold" style="color: var(--brand-primary);">
                        #PR-{{ str_pad((string) $property->id, 4, '0', STR_PAD_LEFT) }}
                    </span>

                    {{-- Rating --}}
                    <div class="flex items-center gap-1.5 text-sm">
                        <x-heroicon-s-star class="h-4 w-4 shrink-0 text-yellow-400" />
                        @if ($property->reviews_count > 0)
                            <span class="font-semibold text-gray-950 dark:text-white">{{ number_format((float) $property->reviews_avg_rating, 1) }}</span>
                            <span class="text-gray-500 dark:text-gray-400">({{ $property->reviews_count }} {{ __('admin.reviews') }})</span>
                        @else
                            <span class="font-semibold text-gray-950 dark:text-white">—</span>
                            <span class="text-gray-500 dark:text-gray-400">(0 {{ __('admin.reviews') }})</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tab Navigation --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex gap-6 overflow-x-auto">
            @foreach ($tabs as $key => $tab)
                <button
                    wire:click="switchTab('{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition',
                        'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' => $this->activeTab === $key,
                        'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $this->activeTab !== $key,
                    ])
                >
                    <x-dynamic-component :component="$tab['icon']" class="h-4 w-4" />
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Tab Content --}}
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6 dark:bg-gray-900 dark:ring-white/10">
        @if ($this->activeTab === 'overview')
            @include('filament.pages.property-view.tab-overview', ['property' => $property])
        @elseif ($this->activeTab === 'facilities')
            @include('filament.pages.property-view.tab-facilities')
        @elseif ($this->activeTab === 'rules')
            @include('filament.pages.property-view.tab-rules', ['property' => $property])
        @elseif ($this->activeTab === 'location')
            @include('filament.pages.property-view.tab-location', ['property' => $property])
        @elseif ($this->activeTab === 'media')
            @include('filament.pages.property-view.tab-media', ['property' => $property])
        @elseif ($this->activeTab === 'documents')
            @include('filament.pages.property-view.tab-documents')
        @endif
    </div>
</x-filament-panels::page>
