<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ─── Back Link ──────────────────────────────────────────────────────── --}}
        <div>
            <a href="{{ $backUrl }}" wire:navigate
                class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                {{ __('admin.back') }}
            </a>
        </div>

        @if ($this->partner)

        {{-- ─── Partner Header Card ─────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-start justify-between gap-4">

                {{-- Left: Avatar + Info --}}
                <div class="flex items-start gap-4">
                    @php
                    $av = $this->partner->user->avatar;
                    $avatarSrc = $av
                    ? (filter_var($av, FILTER_VALIDATE_URL) ? $av : asset('storage/' . $av))
                    : asset('avatars/defaultUser.svg');
                    @endphp
                    @php $avatarExt = $av ? strtolower(pathinfo($av, PATHINFO_EXTENSION)) : 'jpg'; @endphp
                    <img
                        src="{{ $avatarSrc }}"
                        alt="{{ $this->partner->user->name }}"
                        class="h-16 w-16 flex-shrink-0 cursor-pointer rounded-xl object-cover ring-2 ring-gray-100 transition hover:ring-primary-400 dark:ring-gray-700"
                        @click="$dispatch('open-document-modal', {
                            url: '{{ $avatarSrc }}',
                            title: '{{ addslashes($this->partner->user->name) }}',
                            subtitle: '{{ __('admin.profile_photo') }}',
                            ext: '{{ $avatarExt }}'
                        })" />
                    <div class="space-y-1">
                        {{-- Name + Status Badge --}}
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $this->partner->user->name }}
                            </h3>
                            @php $status = $this->partner->verification_status; @endphp
                            <span @class([ 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium' , 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'=> $status === \App\Enums\PartnerVerificationStatus::Pending,
                                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' => $status === \App\Enums\PartnerVerificationStatus::Approved,
                                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' => $status === \App\Enums\PartnerVerificationStatus::Rejected,
                                'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $status === \App\Enums\PartnerVerificationStatus::CorrectionRequested || $status === \App\Enums\PartnerVerificationStatus::Suspended,
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $status === \App\Enums\PartnerVerificationStatus::Resubmission,
                                ])>{{ $status->label() }}</span>
                        </div>

                        {{-- Joining Date --}}
                        <div class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                            <x-heroicon-o-calendar-days class="h-3.5 w-3.5" />
                            {{ __('admin.joining_date') }}: {{ $this->partner->created_at->format('d-m-Y') }}
                        </div>

                        {{-- Partner Number --}}
                        <div class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                            #PR-{{ str_pad($this->partner->id, 4, '0', STR_PAD_LEFT) }}
                        </div>
                    </div>
                </div>

                {{-- Right: Suspend / Unsuspend Toggle --}}
                <div class="flex-shrink-0">
                    {{ $this->toggleSuspensionAction }}
                </div>
            </div>
        </div>

        {{-- ─── Tabs & Content Area ─────────────────────────────────────────────── --}}
        <div class="-mx-4 -mb-4 flex flex-col sm:-mx-6 sm:-mb-6 lg:-mx-8 lg:-mb-8">

            {{-- Tabs --}}
            <div class="border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8 dark:border-gray-700 dark:bg-gray-900">
                <nav class="-mb-px flex gap-6 overflow-x-auto">
                    @foreach ([
                    ['key' => 'overview', 'label' => __('admin.overview'), 'icon' => 'partner.question'],
                    ['key' => 'properties', 'label' => __('admin.properties'), 'icon' => 'partner.building'],
                    ['key' => 'documentations', 'label' => __('admin.documentations'), 'icon' => 'partner.docs'],
                    ['key' => 'audit_logs', 'label' => __('admin.audit_logs'), 'icon' => 'partner.add'],
                    ] as $tab)
                    <button
                        wire:click="switchTab('{{ $tab['key'] }}')"
                        @class([ 'inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium transition' , 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'=> $this->activeTab === $tab['key'],
                        'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300' => $this->activeTab !== $tab['key'],
                        ])
                        >
                        {!! svg($tab['icon'], 'h-4 w-4')->toHtml() !!}
                        {{ $tab['label'] }}
                    </button>
                    @endforeach
                </nav>
            </div>

            {{-- Tab Content --}}
            <div class="flex-1 bg-[#F7F7F7] px-4 py-6 sm:px-6 lg:px-8 dark:bg-gray-900/50">

                {{-- ─── Tab: Overview ──────────────────────────────────────────────────── --}}
                @if ($this->activeTab === 'overview')
                @php $stats = $this->getOverviewStats(); @endphp
                <div class="space-y-4">

                    {{-- 4 Stat Cards --}}
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">

                        <div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                                {!! svg('partner.vector-6', 'h-5 w-5')->toHtml() !!}
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.total_properties') }}</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $stats['totalProperties'] }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-green-50 dark:bg-green-900/20">
                                {!! svg('partner.vector-7', 'h-5 w-5')->toHtml() !!}
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.active_properties') }}</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $stats['activeProperties'] }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-rose-50 dark:bg-rose-900/20">
                                {!! svg('partner.bookmark', 'h-5 w-5')->toHtml() !!}
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.total_bookings') }}</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $stats['totalBookings'] }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/20">
                                {!! svg('partner.money', 'h-5 w-5')->toHtml() !!}
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.total_revenue') }}</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $stats['currencySymbol'] }}{{ number_format($stats['totalRevenue'], 0) }}</p>
                            </div>
                        </div>

                    </div>

                    {{-- Account Information --}}
                    <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5 dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-4 flex items-center gap-2 rounded-lg bg-[#F7F7F7] px-4 py-3 dark:bg-gray-700/50">
                            {!! svg('partner.profile', 'h-5 w-5')->toHtml() !!}
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('admin.account_information') }}</h3>
                        </div>
                        <div class="grid grid-cols-1 gap-6 px-2 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.first_name') }}</p>
                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $this->partner->user->first_name ?: ($this->partner->user->name ? explode(' ', $this->partner->user->name)[0] : '—') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.last_name') }}</p>
                                <p class="mt-1 font-medium text-gray-900 dark:text-white">
                                    @php
                                    $parts = explode(' ', $this->partner->user->name ?? '');
                                    $lastName = $this->partner->user->last_name ?: (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '—');
                                    @endphp
                                    {{ $lastName }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.email_address') }}</p>
                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $this->partner->user->email ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.phone_number') }}</p>
                                <p class="mt-1 font-medium text-gray-900 dark:text-white">
                                    {{ trim(($this->partner->user->dial_code ?? '').' '.($this->partner->user->phone ?? '')) ?: '—' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Owner Address --}}
                    @php
                    $mapsUrl = collect([
                    $this->partner->address,
                    $this->partner->refAddressCity?->name,
                    $this->partner->refAddressState?->name,
                    $this->partner->zip_code,
                    $this->partner->refAddressCountry?->name,
                    ])->filter()->isNotEmpty()
                    ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode(
                    collect([
                    $this->partner->address,
                    $this->partner->refAddressCity?->name,
                    $this->partner->refAddressState?->name,
                    $this->partner->zip_code,
                    $this->partner->refAddressCountry?->name,
                    ])->filter()->join(', ')
                    )
                    : null;
                    @endphp
                    <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5 dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-4 flex items-center justify-between rounded-lg bg-[#F7F7F7] px-4 py-3 dark:bg-gray-700/50">
                            <div class="flex items-center gap-2">
                                {!! svg('partner.location', 'h-5 w-5')->toHtml() !!}
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('admin.owner_address') }}</h3>
                            </div>
                            <a href="{{ $mapsUrl ?? '#' }}" target="_blank"
                                class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                                {!! svg('partner.location', 'h-4 w-4')->toHtml() !!}
                                {{ __('admin.open_on_google_maps') }}
                            </a>
                        </div>
                        <div class="grid grid-cols-1 gap-6 px-2 sm:grid-cols-2">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.street_address') }}</p>
                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $this->partner->address ?: '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.country') }}</p>
                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $this->partner->refAddressCountry?->name ?? $this->partner->country ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.state') }}</p>
                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $this->partner->refAddressState?->name ?? $this->partner->state_province ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.city') }}</p>
                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $this->partner->refAddressCity?->name ?? $this->partner->city ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.zip_code') }}</p>
                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $this->partner->zip_code ?: '—' }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Registration Details (non-file fields) --}}
                    @php
                    $regValues = $this->partner->registrationValues
                    ->filter(fn ($v) => $v->registrationField !== null && $v->registrationField->field_type !== \App\Enums\RegistrationFieldType::FileUpload)
                    ->values();
                    @endphp
                    @if ($regValues->isNotEmpty())
                    <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5 dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-4 flex items-center gap-2 rounded-lg bg-[#F7F7F7] px-4 py-3 dark:bg-gray-700/50">
                            <x-heroicon-o-clipboard-document-list class="h-5 w-5 text-gray-400" />
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('admin.registration_details') }}</h3>
                        </div>
                        <div class="grid grid-cols-1 gap-6 px-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($regValues as $regVal)
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $regVal->registrationField->name }}</p>
                                <p class="mt-1 font-medium text-gray-900 dark:text-white">
                                    @php
                                    $val = $regVal->value;
                                    $display = is_array($val) ? implode(', ', array_filter((array) $val)) : ($val ?? '');
                                    @endphp
                                    {{ $display ?: '—' }}
                                </p>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                </div>
                @endif

                {{-- ─── Tab: Properties ────────────────────────────────────────────────── --}}
                @if ($this->activeTab === 'properties')
                @php $properties = $this->getProperties(); $grouped = $properties->groupBy('country_id'); @endphp

                @if ($properties->isEmpty())
                <div class="rounded-xl border border-gray-200 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-800">
                    <x-heroicon-o-building-office-2 class="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-gray-600" />
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.no_properties_found') }}</p>
                </div>
                @else
                <div class="space-y-4">
                    @php $countryIndex = 1; @endphp
                    @foreach ($grouped as $countryId => $countryProperties)
                    @php $country = $countryProperties->first()->country; @endphp
                    <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5 dark:border-gray-700 dark:bg-gray-800">
                        {{-- Country Header --}}
                        <div class="mb-4 flex items-center gap-3 rounded-lg bg-[#F7F7F7] px-4 py-3 dark:bg-gray-700/50">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-200 text-xs font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                {{ str_pad($countryIndex, 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $country?->name ?? '—' }}</span>
                            <span class="rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                                {{ $countryProperties->count() }} {{ __('admin.properties') }}
                            </span>
                        </div>

                        {{-- Properties List --}}
                        <div class="divide-y divide-gray-100 px-2 dark:divide-gray-700">
                            @foreach ($countryProperties as $property)
                            @php
                            $thumb = $property->primaryImages->first()?->image_path;
                            $city = $property->refCity?->name;
                            $state = $property->refState?->name;
                            $location = collect([$city, $state])->filter()->join(' · ');
                            @endphp
                            <div class="flex items-center justify-between gap-4 px-5 py-4">
                                <div class="flex items-center gap-4">
                                    @if ($thumb)
                                    <img src="{{ asset('storage/'.$thumb) }}" alt="{{ $property->name }}"
                                        class="h-14 w-20 flex-shrink-0 rounded-lg object-cover" />
                                    @else
                                    <div class="flex h-14 w-20 flex-shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700">
                                        <x-heroicon-o-building-office-2 class="h-6 w-6 text-gray-400" />
                                    </div>
                                    @endif
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $property->name }}</p>
                                        @if ($location)
                                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $location }}</p>
                                        @endif
                                        @php
                                        $propStatus = $property->status;
                                        $propStatusColor = match ($propStatus) {
                                        \App\Enums\PropertyStatus::Active => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                                        \App\Enums\PropertyStatus::Inactive => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                                        default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                        };
                                        @endphp
                                        <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $propStatusColor }}">
                                            {{ $propStatus->label() }}
                                        </span>
                                    </div>
                                </div>
                                <a href="{{ \App\Filament\Pages\AllPropertiesView::getUrl(['record' => $property->id]) }}" wire:navigate
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700">
                                    {{ __('admin.view_property') }}
                                    <x-heroicon-o-arrow-right class="h-4 w-4" />
                                </a>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @php $countryIndex++; @endphp
                    @endforeach
                </div>
                @endif
                @endif

                {{-- ─── Tab: Documentations ────────────────────────────────────────────── --}}
                @if ($this->activeTab === 'documentations')
                @php
                $docs = $this->getDocuments();
                $docCount = count($docs);
                $isApproved = $this->partner->verification_status === \App\Enums\PartnerVerificationStatus::Approved;
                @endphp

                <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5 dark:border-gray-700 dark:bg-gray-800">

                    {{-- Card Heading --}}
                    <div class="mb-6 rounded-lg bg-[#F7F7F7] px-4 py-3 dark:bg-gray-700/50">
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('admin.documentation') }}</h3>
                    </div>

                    {{-- Stats Row --}}
                    <div class="mb-6 grid grid-cols-1 divide-y divide-gray-100 rounded-xl border border-gray-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-gray-700 dark:border-gray-700">
                        <div class="flex items-center gap-4 p-4 sm:p-5">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                                <x-heroicon-o-document-duplicate class="h-5 w-5 text-blue-500 dark:text-blue-400" />
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.documents_uploaded') }}</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $docCount }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 p-4 sm:p-5">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-green-50 dark:bg-green-900/20">
                                <x-heroicon-o-check-circle class="h-5 w-5 text-green-500 dark:text-green-400" />
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.property_verified_on') }}</p>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">
                                    {{ $this->partner->verified_at ? $this->partner->verified_at->format('d M, Y') : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 p-4 sm:p-5">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-900/20">
                                <x-heroicon-o-user-circle class="h-5 w-5 text-orange-500 dark:text-orange-400" />
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.verified_by') }}</p>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $this->getVerifiedByName() }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Documents Table --}}
                    @if (empty($docs))
                    <div class="p-12 text-center">
                        <x-heroicon-o-document class="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-gray-600" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.no_partner_documents_uploaded') }}</p>
                    </div>
                    @else
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-100 bg-[#F7F7F7] dark:border-gray-700 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-6 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.document') }}</th>
                                <th class="px-6 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.uploaded_on') }}</th>
                                <th class="px-6 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.status') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($docs as $doc)
                            @php $ext = strtolower(pathinfo($doc['path'], PATHINFO_EXTENSION)); @endphp
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        @if ($ext === 'pdf')
                                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/20">
                                            <span class="text-xs font-bold text-red-600 dark:text-red-400">PDF</span>
                                        </div>
                                        @else
                                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/20">
                                            <span class="text-xs font-bold uppercase text-blue-600 dark:text-blue-400">{{ strtoupper($ext) ?: 'IMG' }}</span>
                                        </div>
                                        @endif
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $doc['name'] }}</p>
                                            @php
                                            $filePath = storage_path('app/public/' . $doc['path']);
                                            $fileSize = file_exists($filePath) ? round(filesize($filePath) / 1024, 0) . ' KB' : '';
                                            @endphp
                                            @if ($fileSize)
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $fileSize }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $doc['uploaded_at'] ? \Carbon\Carbon::parse($doc['uploaded_at'])->format('d M, Y') : '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    @if ($isApproved)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                        <x-heroicon-o-check-circle class="h-3.5 w-3.5" />
                                        {{ __('admin.verified') }}
                                    </span>
                                    @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                        {{ __('admin.pending') }}
                                    </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button"
                                            @click="$dispatch('open-document-modal', {
                                            url: '{{ asset('storage/'.$doc['path']) }}',
                                            title: '{{ addslashes($doc['name']) }}',
                                            subtitle: '{{ basename($doc['path']) }}',
                                            ext: '{{ $ext }}'
                                        })"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600"
                                            title="{{ __('admin.view_document') }}">
                                            <x-phosphor-eye class="h-4 w-4" />
                                        </button>
                                        <a href="{{ asset('storage/'.$doc['path']) }}" download
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600"
                                            title="{{ __('admin.download_document') }}">
                                            <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
                @endif

                {{-- ─── Tab: Audit Logs ─────────────────────────────────────────────────── --}}
                @if ($this->activeTab === 'audit_logs')
                @php $logs = $this->getAuditLogs(); @endphp

                <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('admin.audit_logs') }}</h3>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.filter') }} :</span>
                            <input
                                type="date"
                                wire:change="setAuditLogDate($event.target.value)"
                                value="{{ $this->auditLogDate ?? '' }}"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300" />
                        </div>
                    </div>

                    @if ($logs->isEmpty())
                    <div class="p-12 text-center">
                        <x-heroicon-o-clipboard-document-list class="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-gray-600" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.no_audit_logs') }}</p>
                    </div>
                    @else
                    <ul class="flex flex-col gap-4">
                        @foreach ($logs as $log)
                        @php
                            $changes = $log->properties['attributes'] ?? [];
                            $oldValues = $log->properties['old'] ?? [];
                            $isEmptyLogValue = fn (mixed $val): bool => $val === null || $val === '';
                            $changes = array_filter(
                                $changes,
                                fn (mixed $newValue, string $field): bool => ! ($isEmptyLogValue($oldValues[$field] ?? null) && $isEmptyLogValue($newValue)),
                                ARRAY_FILTER_USE_BOTH
                            );
                        @endphp
                        <li class="rounded-xl bg-[#F7F7F7] p-4 sm:px-5 dark:bg-gray-700/50" x-data="{ open: false }">
                            <div @if(!empty($changes)) @click="open = !open" class="flex cursor-pointer items-center gap-4" @else class="flex items-center gap-4" @endif>
                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full border border-blue-100 bg-white shadow-sm dark:border-blue-900/50 dark:bg-gray-800">
                                    {!! svg('others.PencilSimpleLine', 'h-4 w-4 text-blue-500 dark:text-blue-400')->toHtml() !!}
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $log->description }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('admin.by') }} {{ $log->causer?->name ?? __('admin.system') }} - <span x-data x-text="new Date('{{ $log->created_at->utc()->toIso8601String() }}').toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })"></span>
                                        <!-- ~~~~ get browser time -->
                                    </p>
                                </div>
                                @if(!empty($changes))
                                <x-heroicon-o-chevron-down
                                    class="h-5 w-5 shrink-0 text-gray-400 transition-transform duration-200"
                                    x-bind:class="{ 'rotate-180': open }" />
                                @endif
                            </div>

                            @if(!empty($changes))
                            @php
                            $fileFields = $log->properties['file_fields'] ?? [];
                            $imageFields = $log->properties['image_fields'] ?? [];
                            $isFilePathValue = function(mixed $val): bool {
                                if (!is_string($val) || empty($val) || $val === '—') return false;
                                $parts = explode('|', $val);
                                foreach ($parts as $p) {
                                    $ext = strtolower(pathinfo(trim($p), PATHINFO_EXTENSION));
                                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx'])) {
                                        return true;
                                    }
                                }
                                return false;
                            };
                            $renderFileCardValue = function(mixed $value) use ($log): string {
                                if ($value === '—' || $value === '' || $value === null) {
                                    return '<span class="flex h-24 w-24 items-center justify-center rounded-xl border border-dashed border-gray-200 bg-gray-50 text-xs text-gray-400 dark:border-gray-600 dark:bg-gray-800">—</span>';
                                }
                                $paths = array_values(array_filter(array_map('trim', explode('|', (string)$value))));
                                if (empty($paths)) {
                                    return '<span class="flex h-24 w-24 items-center justify-center rounded-xl border border-dashed border-gray-200 bg-gray-50 text-xs text-gray-400 dark:border-gray-600 dark:bg-gray-800">—</span>';
                                }
                                $cards = array_map(function (string $p): string {
                                    $isUrl = filter_var($p, FILTER_VALIDATE_URL);
                                    $url = e($isUrl ? $p : asset('storage/' . $p));
                                    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
                                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                                    $isPdf = $ext === 'pdf';
                                    $filename = e(basename($p));

                                    $existsOnDisk = $isUrl || \Illuminate\Support\Facades\Storage::disk('public')->exists($p);

                                    if (!$existsOnDisk) {
                                        return '<div class="flex h-24 w-24 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-gray-300 bg-gray-50/80 p-2 text-center text-gray-400 dark:border-gray-700 dark:bg-gray-800/60" title="File was removed or deleted from server">'
                                            . '<svg class="h-6 w-6 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>'
                                            . '<span class="text-[10px] font-medium text-gray-400">File Deleted</span>'
                                            . '</div>';
                                    }

                                    $fallbackHtml = e('<div class="flex h-full w-full flex-col items-center justify-center gap-1 bg-gray-50 p-1 text-center text-gray-400 dark:bg-gray-800"><svg class="h-6 w-6 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg><span class="text-[10px]">File Deleted</span></div>');

                                    if ($isImage) {
                                        return '<button type="button" @click="$dispatch(\'open-document-modal\', { url: \'' . $url . '\', title: \'Document Preview\', subtitle: \'' . $filename . '\', ext: \'' . $ext . '\' })"'
                                            . ' class="block h-24 w-24 shrink-0 overflow-hidden rounded-xl border border-gray-200 bg-gray-50 shadow-sm transition hover:opacity-80 dark:border-gray-700 dark:bg-gray-800" title="Click to view image">'
                                            . '<img src="' . $url . '" class="h-full w-full object-cover" alt="Image Preview" onerror="this.onerror=null; this.parentElement.innerHTML=\'' . $fallbackHtml . '\';" />'
                                            . '</button>';
                                    }

                                    if ($isPdf) {
                                        return '<button type="button" @click="$dispatch(\'open-document-modal\', { url: \'' . $url . '\', title: \'Document Preview\', subtitle: \'' . $filename . '\', ext: \'pdf\' })"'
                                            . ' class="flex h-24 w-24 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border border-red-200 bg-red-50/80 p-2 text-red-600 shadow-sm transition hover:bg-red-100 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-400" title="Click to view PDF">'
                                            . '<svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>'
                                            . '<span class="text-[11px] font-bold uppercase tracking-wider">PDF</span>'
                                            . '</button>';
                                    }

                                    $upperExt = strtoupper($ext ?: 'DOC');
                                    return '<button type="button" @click="$dispatch(\'open-document-modal\', { url: \'' . $url . '\', title: \'Document Preview\', subtitle: \'' . $filename . '\', ext: \'' . $ext . '\' })"'
                                        . ' class="flex h-24 w-24 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border border-blue-200 bg-blue-50/80 p-2 text-blue-600 shadow-sm transition hover:bg-blue-100 dark:border-blue-900/40 dark:bg-blue-950/40 dark:text-blue-400" title="Click to view document">'
                                        . '<svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                                        . '<span class="text-[10px] font-bold uppercase tracking-wider">' . e($upperExt) . '</span>'
                                        . '</button>';
                                }, $paths);

                                return '<div class="flex items-center gap-2 flex-wrap">' . implode('', $cards) . '</div>';
                            };
                            @endphp
                            <div x-show="open" x-collapse class="mt-3 ml-14 rounded-xl border border-gray-200/80 bg-white p-3.5 dark:border-gray-700/80 dark:bg-gray-800">
                                <div class="space-y-3">
                                    @foreach ($changes as $field => $newValue)
                                    @php
                                        $oldValue = $log->properties['old'][$field] ?? null;
                                        $isFile = in_array($field, $imageFields) || in_array($field, $fileFields) || $isFilePathValue($oldValue) || $isFilePathValue($newValue);
                                    @endphp
                                    @if($isFile)
                                    <div class="rounded-xl border border-gray-100 bg-[#FAFAFA] p-3.5 dark:border-gray-700/60 dark:bg-gray-800/60">
                                        <div class="mb-2.5 flex items-center gap-2">
                                            <span class="inline-flex h-2 w-2 rounded-full bg-primary-500"></span>
                                            <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200">{{ $field }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            {!! $renderFileCardValue($oldValue ?? '—') !!}
                                            <x-heroicon-o-arrow-right class="h-4 w-4 shrink-0 text-gray-400" />
                                            {!! $renderFileCardValue($newValue ?? '—') !!}
                                        </div>
                                    </div>
                                    @else
                                    <div class="flex flex-col gap-2 rounded-xl border border-gray-100 bg-[#FAFAFA] p-3.5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700/60 dark:bg-gray-800/60">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex h-2 w-2 rounded-full bg-gray-400"></span>
                                            <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200">{{ $field }}</span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2 text-xs">
                                            <span class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-1.5 font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                {{ ($oldValue === null || $oldValue === '') ? '—' : $oldValue }}
                                            </span>
                                            <x-heroicon-o-arrow-right class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                            <span class="inline-flex items-center rounded-lg border border-primary-200/80 bg-primary-50 px-3 py-1.5 font-semibold text-primary-700 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-300">
                                                {{ ($newValue === null || $newValue === '') ? '—' : $newValue }}
                                            </span>
                                        </div>
                                    </div>
                                    @endif
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                    @endif
                </div>
                @endif

            </div> {{-- end gray tab container --}}

            @endif {{-- end $this->partner --}}

        </div>

        <x-filament-actions::modals />
</x-filament-panels::page>