<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ─── Back Link ──────────────────────────────────────────────────────── --}}
        <div>
            <a href="{{ \App\Filament\Pages\PartnerVerificationManage::getUrl() }}" wire:navigate
                class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                {{ __('admin.back_to_all_partners') }}
            </a>
        </div>

        @if ($this->partner)

        {{-- ─── Partner Header Card ────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

                {{-- Avatar + Info --}}
                <div class="flex items-start gap-4">
                    @php
                        $avatar    = $this->partner->user->avatar;
                        $avatarSrc = $avatar
                            ? (filter_var($avatar, FILTER_VALIDATE_URL) ? $avatar : asset('storage/' . $avatar))
                            : asset('avatars/defaultUser.svg');
                        $avatarExt = $avatar ? strtolower(pathinfo($avatar, PATHINFO_EXTENSION)) : 'jpg';
                        $avatarExt = in_array($avatarExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? $avatarExt : 'jpg';
                    @endphp
                    <img
                        src="{{ $avatarSrc }}"
                        alt="{{ $this->partner->user->name }}"
                        class="h-24 w-24 flex-shrink-0 cursor-pointer rounded-xl object-cover ring-2 ring-gray-100 transition hover:ring-primary-400 dark:ring-gray-700"
                        @click="$dispatch('open-document-modal', {
                            url: '{{ $avatarSrc }}',
                            title: '{{ addslashes($this->partner->user->name) }}',
                            subtitle: '{{ __('admin.profile_photo') }}',
                            ext: '{{ $avatarExt }}'
                        })"
                    />
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $this->partner->user->name }}
                        </h3>

                        {{-- Status Badge --}}
                        @php $status = $this->partner->verification_status; @endphp
                        <span @class([
                            'mt-1 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' => $status === \App\Enums\PartnerVerificationStatus::Pending,
                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' => $status === \App\Enums\PartnerVerificationStatus::Approved,
                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' => $status === \App\Enums\PartnerVerificationStatus::Rejected,
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $status === \App\Enums\PartnerVerificationStatus::CorrectionRequested || $status === \App\Enums\PartnerVerificationStatus::Suspended,
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $status === \App\Enums\PartnerVerificationStatus::Resubmission,
                        ])>{{ $status->label() }}</span>

                        {{-- Countries --}}
                        @if ($this->partner->countries->isNotEmpty())
                        <p class="mt-1.5 flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                            <x-heroicon-o-map-pin class="h-3.5 w-3.5 flex-shrink-0" />
                            {{ $this->partner->countries->pluck('name')->join(', ') }}
                        </p>
                        @endif

                        {{-- Property Type --}}
                        @if ($this->partner->propertyType)
                        <p class="mt-0.5 flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                            <x-heroicon-o-building-office class="h-3.5 w-3.5 flex-shrink-0" />
                            {{ $this->partner->propertyType->name }}
                        </p>
                        @endif

                        {{-- Applied On --}}
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('admin.applied_on') }}: {{ $this->partner->created_at->format('d M, Y') }}
                        </p>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-wrap items-center gap-4">
                    {{ $this->approveAction }}
                    {{ $this->requestCorrectionAction }}
                    {{ $this->rejectAction }}
                </div>
            </div>
        </div>

        {{-- ─── Rejection / Correction Reason Alert ───────────────────────────── --}}
        @if ($this->partner->rejection_reason)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
            <div class="flex items-start gap-3">
                <x-heroicon-o-information-circle class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600 dark:text-amber-400" />
                <div>
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                        @if ($this->partner->verification_status === \App\Enums\PartnerVerificationStatus::CorrectionRequested)
                            {{ __('admin.correction_reason') }}
                        @else
                            {{ __('admin.rejection_reason') }}
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
                        {{ $this->partner->rejection_reason }}
                    </p>
                </div>
            </div>
        </div>
        @endif

        {{-- ─── Personal Information ───────────────────────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.personal_info') }}</x-slot>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('admin.full_name') }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $this->partner->user->name ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('admin.email') }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $this->partner->user->email ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('admin.phone') }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        {{ ($this->partner->user->dial_code ? $this->partner->user->dial_code . ' ' : '') . ($this->partner->user->phone ?? '—') }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('admin.date_of_birth') }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $this->partner->user->date_of_birth?->format('d M, Y') ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('admin.gender') }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $this->partner->user->gender?->label() ?? '—' }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        {{-- ─── Address ────────────────────────────────────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.owner_address') }}</x-slot>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('admin.street_address') }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $this->partner->address ?? '—' }}
                    </p>
                </div>
                @if ($this->partner->address_line2)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('admin.address_line_2') }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $this->partner->address_line2 }}
                    </p>
                </div>
                @endif
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('admin.city') }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $this->partner->city ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('admin.state') }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $this->partner->state_province ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('admin.country') }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $this->partner->country ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('admin.zip_code') }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $this->partner->zip_code ?? '—' }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        {{-- ─── Registration Details (non-file fields) ────────────────────────── --}}
        @php
            $regValues = $this->partner->registrationValues
                ->filter(fn ($v) => $v->registrationField !== null && $v->registrationField->field_type !== \App\Enums\RegistrationFieldType::FileUpload)
                ->values();
        @endphp
        @if ($regValues->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.registration_details') }}</x-slot>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($regValues as $regVal)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $regVal->registrationField->name }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        @php
                            $val = $regVal->value;
                            $display = is_array($val) ? implode(', ', array_filter((array) $val)) : ($val ?? '');
                        @endphp
                        {{ $display ?: '—' }}
                    </p>
                </div>
                @endforeach
            </div>
        </x-filament::section>
        @endif

        {{-- ─── Documentation (file upload fields) ───────────────────────────── --}}
        @php
            $docValues = $this->partner->registrationValues
                ->filter(fn ($v) => $v->registrationField !== null && $v->registrationField->field_type === \App\Enums\RegistrationFieldType::FileUpload)
                ->values();
        @endphp
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.documentation') }}</x-slot>

            @if ($docValues->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.no_partner_documents_uploaded') }}
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="pb-3 pr-6 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ __('admin.document') }}
                                </th>
                                <th class="pb-3 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ __('admin.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($docValues as $docVal)
                                @php
                                    $files = is_array($docVal->value) ? $docVal->value : [$docVal->value];
                                    $files = array_filter((array) $files);
                                @endphp
                                @foreach ($files as $file)
                                @php
                                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                    $fileUrl = asset('storage/' . $file);
                                @endphp
                                <tr>
                                    <td class="py-3 pr-6">
                                        <div class="flex items-center gap-3">
                                            @if ($ext === 'pdf')
                                                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/20">
                                                    <x-heroicon-o-document-text class="h-5 w-5 text-red-600 dark:text-red-400" />
                                                </div>
                                            @else
                                                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/20">
                                                    <x-heroicon-o-photo class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                                </div>
                                            @endif
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    {{ $docVal->registrationField->name }}
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ basename($file) }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="flex items-center gap-2">
                                            <button type="button"
                                                @click="$dispatch('open-document-modal', {
                                                    url: '{{ $fileUrl }}',
                                                    title: '{{ addslashes($docVal->registrationField->name) }}',
                                                    subtitle: '{{ basename($file) }}',
                                                    ext: '{{ $ext }}'
                                                })"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600"
                                                title="{{ __('admin.view_document') }}">
                                                <x-phosphor-eye class="h-4 w-4" />
                                            </button>
                                            <a href="{{ $fileUrl }}"
                                                download
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600"
                                                title="{{ __('admin.download_document') }}">
                                                <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        @endif {{-- end $this->partner --}}

    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
