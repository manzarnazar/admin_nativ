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
        <a href="{{ \App\Filament\Pages\PropertyVerificationManage::getUrl() }}" wire:navigate
            class="inline-flex items-center gap-1 text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            {{ __('admin.back_to_all_properties') }}
        </a>
    </div>

    @if ($property)

    {{-- Property Header Card --}}
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-4 sm:gap-5">
                {{-- Property Image --}}
                <div class="h-24 w-24 shrink-0 overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                    @php $thumbnail = $property->primaryImages->firstWhere('media_type', 'image'); @endphp
                    @if ($thumbnail)
                        <img src="{{ asset('storage/' . $thumbnail->image_path) }}" alt="{{ $property->name }}" class="h-full w-full object-cover" />
                    @else
                        <div class="flex h-full w-full items-center justify-center">
                            <x-heroicon-o-building-office class="h-10 w-10 text-gray-400" />
                        </div>
                    @endif
                </div>

                {{-- Property Info --}}
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                        <h1 class="text-xl font-bold text-gray-950 dark:text-white">{{ $property->name }}</h1>
                        @if ($property->propertyType)
                            <span class="inline-flex items-center rounded-md bg-gray-800 px-2.5 py-1 text-xs font-medium text-white dark:bg-gray-700">
                                {{ $property->propertyType->name }}
                            </span>
                        @endif
                        @php $status = $property->verification_status; @endphp
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' => $status === \App\Enums\PropertyVerificationStatus::Pending,
                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' => $status === \App\Enums\PropertyVerificationStatus::Approved,
                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' => $status === \App\Enums\PropertyVerificationStatus::Rejected,
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $status === \App\Enums\PropertyVerificationStatus::CorrectionRequested,
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $status === \App\Enums\PropertyVerificationStatus::Resubmission,
                        ])>{{ $status?->label() }}</span>
                    </div>

                    <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1.5">
                            <x-heroicon-o-map-pin class="h-4 w-4" />
                            {{ collect([$property->refCity?->name, $property->refState?->name])->filter()->implode(', ') ?: '-' }}
                        </span>
                        <span class="flex items-center gap-1.5">
                            <x-heroicon-o-user-circle class="h-4 w-4" />
                            {{ __('admin.partner') }}: {{ $property->partner?->user?->name ?? '-' }}
                        </span>
                    </div>

                    <div class="mt-2">
                        <span class="text-sm font-semibold" style="color: var(--brand-primary);">
                            ID-{{ $property->id }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-wrap items-center gap-3">
                {{ $this->approveAction }}
                {{ $this->requestCorrectionAction }}
                {{ $this->rejectAction }}
            </div>
        </div>
    </div>

    {{-- Rejection / Correction Reason Alert --}}
    @if ($property->verification_notes)
    <div class="mt-4 rounded-lg border p-4 @class([
        'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20' => $property->verification_status === \App\Enums\PropertyVerificationStatus::Rejected,
        'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20' => $property->verification_status !== \App\Enums\PropertyVerificationStatus::Rejected,
    ])">
        <div class="flex items-start gap-3">
            <x-heroicon-o-information-circle @class([
                'mt-0.5 h-5 w-5 flex-shrink-0',
                'text-red-600 dark:text-red-400' => $property->verification_status === \App\Enums\PropertyVerificationStatus::Rejected,
                'text-amber-600 dark:text-amber-400' => $property->verification_status !== \App\Enums\PropertyVerificationStatus::Rejected,
            ]) />
            <div>
                <p @class([
                    'text-sm font-medium',
                    'text-red-800 dark:text-red-300' => $property->verification_status === \App\Enums\PropertyVerificationStatus::Rejected,
                    'text-amber-800 dark:text-amber-300' => $property->verification_status !== \App\Enums\PropertyVerificationStatus::Rejected,
                ])>
                    {{ $property->verification_status === \App\Enums\PropertyVerificationStatus::Rejected ? __('admin.rejection_reason') : __('admin.correction_reason') }}
                </p>
                <p @class([
                    'mt-1 text-sm',
                    'text-red-700 dark:text-red-400' => $property->verification_status === \App\Enums\PropertyVerificationStatus::Rejected,
                    'text-amber-700 dark:text-amber-400' => $property->verification_status !== \App\Enums\PropertyVerificationStatus::Rejected,
                ])>
                    {{ $property->verification_notes }}
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Tab Navigation --}}
    <div class="mt-6 border-b border-gray-200 dark:border-gray-700">
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

    {{-- Tab Content (reuses the same partials PropertyView.php uses) --}}
    <div class="mt-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6 dark:bg-gray-900 dark:ring-white/10">
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

    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
