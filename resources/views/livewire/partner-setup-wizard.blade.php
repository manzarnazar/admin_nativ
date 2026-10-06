<div class="flex h-screen flex-col bg-white">
    @php
        $logo = \App\Models\Setting::get('logo');
        $logoPath = is_array($logo) ? ($logo[0] ?? null) : $logo;
        $logoPath = is_array($logoPath) ? ($logoPath[0] ?? null) : $logoPath;
        $logoUrl = filled($logoPath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath)
            : null;
    @endphp

    {{-- ── Top Navigation Bar (Full Width Header) ──────────────────── --}}
    <div class="flex h-16 w-full flex-shrink-0 items-center bg-white" style="border-bottom: 1px solid #EDEDED;">
        {{-- Top Left Logo Container (Matches Sidebar Width w-64) --}}
        <div class="flex h-full w-64 flex-shrink-0 items-center px-6" style="border-right: 1px solid #EDEDED;">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" class="h-8 w-auto object-contain">
            @else
                <span class="text-xl font-extrabold tracking-tight text-blue-600">{{ config('app.name') }}</span>
            @endif
        </div>

        {{-- Right Section of Top Bar --}}
        <div class="flex flex-1 items-center justify-end bg-white px-6">
            <form method="POST" action="{{ route('filament.partner.auth.logout') }}">
                @csrf
                <button type="submit"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3H21" />
                    </svg>
                    {{ __('admin.log_out') }}
                </button>
            </form>
        </div>
    </div>

        {{-- ── Main Layout Body ───────────────────────────────────────── --}}
        <div class="flex flex-1 overflow-hidden">

            {{-- ── Left Sidebar Column ─────────────────────────────────── --}}
            <div class="flex w-64 flex-shrink-0 flex-col bg-white" style="border-right: 1px solid #EDEDED;">

                {{-- Sidebar Header --}}
                <div class="px-6 pb-6 pt-6" style="border-bottom: 1px solid #EDEDED;">
                    <h2 class="text-base font-bold text-gray-900">{{ __('admin.partner_onboarding') }}</h2>
                    <p class="mt-1 text-xs leading-relaxed text-gray-500">{{ __('admin.partner_onboarding_description') }}</p>
                </div>

                {{-- Step Navigation --}}
                @php
                $steps = [
                    1 => ['name' => __('admin.select_country'),      'description' => __('admin.operating_country')],
                    2 => ['name' => __('admin.property_type'),       'description' => __('admin.property_category')],
                    3 => ['name' => __('admin.profile'),             'description' => __('admin.personal_details')],
                    4 => ['name' => __('admin.address'),             'description' => __('admin.location_details')],
                    5 => ['name' => __('admin.verification'),        'description' => __('admin.required_information')],
                ];
                @endphp

                <nav class="flex-1 px-6 py-8 overflow-y-auto">
                    <div class="flex flex-col">
                        @foreach ($steps as $stepNumber => $step)
                        @php
                        $isActive    = ! $isSubmittedForReview && $currentStep === $stepNumber;
                        $isCompleted = $isSubmittedForReview || $currentStep > $stepNumber;
                        $isLast      = $stepNumber === array_key_last($steps);
                        @endphp
                        <div class="relative flex items-start gap-4" style="{{ !$isLast ? 'margin-bottom: 32px;' : '' }}">
                            {{-- Left Column: Circle + Line --}}
                            <div class="flex flex-col items-center">
                                {{-- Circle indicator --}}
                                <div class="relative z-10 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-white">
                                    @if ($isCompleted)
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-600">
                                        <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    @elseif ($isActive)
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full border-2 border-blue-600 bg-white">
                                        <div class="h-1.5 w-1.5 rounded-full bg-blue-600"></div>
                                    </div>
                                    @else
                                    <div class="h-5 w-5 rounded-full border-2 border-gray-300 bg-white"></div>
                                    @endif
                                </div>

                                {{-- Vertical Connecting Line --}}
                                @if (!$isLast)
                                <div style="position: absolute; left: 9px; top: 20px; bottom: -32px; width: 2px; background-color: {{ $isCompleted ? '#2563eb' : '#d1d5db' }};"></div>
                                @endif
                            </div>

                            {{-- Right Column: Step Name + Description --}}
                            <div class="flex-1 min-w-0 pt-0.5">
                                <p class="text-sm font-bold text-gray-900 leading-none">
                                    {{ $step['name'] }}
                                </p>
                                <p class="mt-1.5 text-xs leading-relaxed text-gray-500">{{ $step['description'] }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </nav>

                {{-- Sidebar Footer --}}
                <div class="px-6 py-4" style="border-top: 1px solid #EDEDED;">
                    <p class="text-xs text-gray-400">&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('admin.all_rights_reserved') }}</p>
                </div>
            </div>

            {{-- ── Right Content Area ───────────────────────────────────── --}}
            <div class="flex flex-1 flex-col overflow-hidden bg-gray-50/50">

                @if ($isSubmittedForReview)
                {{-- ── Post-submission status: wizard is finished, waiting on / denied review ── --}}
                <div class="flex-1 overflow-y-auto p-10">
                    <div class="flex w-full flex-col gap-4">

                        @if ($partnerVerificationStatus === \App\Enums\PartnerVerificationStatus::Rejected)
                        <div class="rounded-xl border border-red-200 bg-red-50 p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex items-start gap-3">
                                    <x-heroicon-o-x-circle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600" />
                                    <div>
                                        <p class="font-semibold text-red-800">{{ __('admin.partner_rejected_title') }}</p>
                                        <p class="mt-1 text-sm text-red-700">{{ __('admin.partner_rejected_description') }}</p>
                                        @if ($partnerRejectionReason)
                                        <p class="mt-2 text-sm font-medium text-red-800">
                                            <span class="uppercase tracking-wide">{{ __('admin.rejection_reason') }}</span> : {{ $partnerRejectionReason }}
                                        </p>
                                        @endif
                                    </div>
                                </div>
                                <button type="button" wire:click="startEditing"
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-red-700 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-red-800">
                                    {{ __('admin.make_changes') }}
                                    <x-heroicon-o-arrow-right class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        @elseif ($partnerVerificationStatus === \App\Enums\PartnerVerificationStatus::CorrectionRequested)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex items-start gap-3">
                                    <x-heroicon-o-information-circle class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" />
                                    <div>
                                        <p class="font-semibold text-amber-800">{{ __('admin.partner_correction_title') }}</p>
                                        <p class="mt-1 text-sm text-amber-700">{{ __('admin.partner_correction_description') }}</p>
                                        @if ($partnerRejectionReason)
                                        <p class="mt-2 text-sm font-medium text-amber-800">
                                            <span class="uppercase tracking-wide">{{ __('admin.correction_reason') }}</span> : {{ $partnerRejectionReason }}
                                        </p>
                                        @endif
                                    </div>
                                </div>
                                <button type="button" wire:click="startEditing"
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-amber-700 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-amber-800">
                                    {{ __('admin.make_changes') }}
                                    <x-heroicon-o-arrow-right class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        @elseif ($partnerVerificationStatus === \App\Enums\PartnerVerificationStatus::Resubmission)
                        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                            <div class="flex items-center gap-3">
                                <x-heroicon-o-clock class="h-5 w-5 flex-shrink-0 text-blue-600" />
                                <p class="text-sm text-blue-700">{{ __('admin.partner_resubmission_note') }}</p>
                            </div>
                        </div>
                        @elseif ($partnerVerificationStatus === \App\Enums\PartnerVerificationStatus::Pending)
                        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                            <div class="flex items-center gap-3">
                                <x-heroicon-o-clock class="h-5 w-5 flex-shrink-0 text-blue-600" />
                                <p class="text-sm text-blue-700">{{ __('admin.partner_account_pending_approval') }}</p>
                            </div>
                        </div>
                        @endif

                        <div class="flex flex-col items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 p-10 text-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-sm">
                                <x-phosphor-hourglass-simple class="h-6 w-6 text-blue-500" />
                            </div>
                            <p class="font-semibold text-gray-900">{{ __('admin.partner_under_review_title') }}</p>
                            <p class="max-w-md text-sm text-gray-500">{{ __('admin.partner_under_review_description') }}</p>
                        </div>

                    </div>
                </div>
                @else
                {{-- Step Title Header (White Background Section) --}}
                <div class="flex-shrink-0 bg-white px-10 py-6" style="border-bottom: 1px solid #EDEDED;">
                    @if ($currentStep === 1)
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">{{ __('admin.select_country') }}</h1>
                            <p class="mt-1 text-sm text-gray-500">Choose how you want to manage your properties.</p>
                        </div>
                        <div class="flex items-center rounded-xl border border-gray-300 bg-white p-1 shadow-xs min-w-[280px]">
                            <input
                                wire:model.live.debounce.300ms="countrySearch"
                                type="text"
                                placeholder="{{ __('admin.search_country') }}"
                                class="w-full bg-transparent px-3 py-1.5 text-sm text-gray-700 placeholder-gray-400 focus:outline-none" />
                            <button type="button"
                                class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-black px-4 py-2 text-xs font-semibold text-white hover:bg-gray-800">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                                {{ __('admin.search_button') }}
                            </button>
                        </div>
                    </div>
                    @elseif ($currentStep === 2)
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">{{ __('admin.property_type') }}</h1>
                        <p class="mt-1 text-sm text-gray-500">{{ __('admin.what_type_of_property') }}</p>
                    </div>
                    @elseif ($currentStep === 3)
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">{{ __('admin.tell_us_who_you_are') }}</h1>
                        <p class="mt-1 text-sm text-gray-500">{{ __('admin.provide_personal_information') }}</p>
                    </div>
                    @elseif ($currentStep === 4)
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">{{ __('admin.tell_us_who_you_are') }}</h1>
                        <p class="mt-1 text-sm text-gray-500">{{ __('admin.provide_personal_information') }}</p>
                    </div>
                    @elseif ($currentStep === 5)
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">{{ __('admin.verify_your_identity') }}</h1>
                        <p class="mt-1 text-sm text-gray-500">{{ __('admin.legal_documents_compliance_details') }}</p>
                    </div>
                    @endif
                </div>

                {{-- Scrollable Content Body --}}
                <div class="flex-1 overflow-y-auto p-10">

                    {{-- ─── Step 1: Select Country ──────────────────────── --}}
                    @if ($currentStep === 1)
                    <div class="w-full">

                    <div class="mt-6 grid grid-cols-4 gap-4">
                        @forelse ($countries as $country)
                        @php $isSelected = $selectedCountryId === $country->id; @endphp
                        <div
                            wire:click="selectCountry({{ $country->id }})"
                            class="cursor-pointer rounded-lg border p-4 transition-colors {{ $isSelected ? 'border-blue-500 ring-2 ring-blue-100 bg-white' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                            <div class="flex items-start justify-between">
                                <span class="text-4xl leading-none">{{ $country->refCountry?->emoji ?? '🏳' }}</span>
                                @if ($isSelected)
                                <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border-2 border-blue-600 bg-blue-600">
                                    <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                @else
                                <div class="h-5 w-5 flex-shrink-0 rounded-full border-2 border-gray-300"></div>
                                @endif
                            </div>
                            <p class="mt-3 text-sm font-semibold text-gray-900">{{ $country->name }}</p>
                            <p class="mt-0.5 truncate text-xs text-gray-400">
                                {{ $country->currency_symbol ? $country->currency_symbol.' ' : '' }}{{ $country->currency_code }} &mdash; {{ $country->currency_name }}
                            </p>
                        </div>
                        @empty
                        <div class="col-span-4 py-12 text-center text-sm text-gray-400">
                            {{ __('admin.no_countries_found_for', ['search' => $countrySearch]) }}
                        </div>
                        @endforelse
                    </div>

                    @error('selectedCountryId')
                    <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                </div>
                @endif

                {{-- ─── Step 2: Property Type ─────────────────────────── --}}
                @if ($currentStep === 2)
                <div class="w-full">

                    <div class="grid grid-cols-4 gap-4">
                        @foreach ($propertyTypes as $type)
                        @php $isSelected = $selectedPropertyTypeId === $type->id; @endphp
                        <div
                            wire:click="selectPropertyType({{ $type->id }})"
                            class="cursor-pointer rounded-lg border p-4 transition-colors {{ $isSelected ? 'border-blue-500 ring-2 ring-blue-100 bg-white' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                            <div class="flex items-start justify-between">
                                <img src="{{ $type->icon_url }}" alt="{{ $type->name }}" class="h-10 w-10 object-contain" />
                                @if ($isSelected)
                                <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border-2 border-blue-600 bg-blue-600">
                                    <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                @else
                                <div class="h-5 w-5 flex-shrink-0 rounded-full border-2 border-gray-300"></div>
                                @endif
                            </div>
                            <p class="mt-3 text-sm font-semibold text-gray-900">{{ $type->name }}</p>
                        </div>
                        @endforeach
                    </div>

                    @error('selectedPropertyTypeId')
                    <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                </div>
                @endif

                {{-- ─── Step 3: Profile ──────────────────────────────── --}}
                @if ($currentStep === 3)
                <div class="w-full max-w-4xl">

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 md:p-8 shadow-sm space-y-6">

                        {{-- Avatar Upload --}}
                        @php
                            $existingAvatarUrl = ($authUser->avatar && !str_contains($authUser->getFilamentAvatarUrl() ?? '', 'defaultUser')) ? $authUser->getFilamentAvatarUrl() : null;
                            // Inlined as a data URI rather than $profileAvatar->temporaryUrl() — that
                            // route is Livewire's own signed preview endpoint, which 401s under this
                            // app's partner auth guard. This needs no separate authenticated request.
                            $profileAvatarPreviewUrl = $profileAvatar ? 'data:'.$profileAvatar->getMimeType().';base64,'.base64_encode($profileAvatar->get()) : null;
                        @endphp
                        <div>
                            <div
                                class="flex items-center gap-5 rounded-xl border border-gray-200 p-5 bg-gray-50/50"
                                x-data="{ preview: @js($profileAvatarPreviewUrl), pick() { document.getElementById('profileAvatarInput').click() } }"
                            >
                                <input
                                    id="profileAvatarInput"
                                    wire:model="profileAvatar"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="
                                        const file = $event.target.files[0];
                                        if (file) {
                                            const reader = new FileReader();
                                            reader.onload = (e) => { preview = e.target.result; };
                                            reader.readAsDataURL(file);
                                        }
                                    "
                                />

                                <div class="relative flex-shrink-0">
                                    <div
                                        @click="pick()"
                                        class="cursor-pointer flex h-20 w-20 items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-white overflow-hidden"
                                    >
                                        <img x-show="preview" :src="preview" class="h-full w-full object-cover" style="display:none" />
                                        <div x-show="!preview" class="h-full w-full flex items-center justify-center">
                                            @if ($existingAvatarUrl)
                                                <img src="{{ $existingAvatarUrl }}" class="h-full w-full object-cover" />
                                            @else
                                                <svg class="h-7 w-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                                                </svg>
                                            @endif
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        x-show="preview"
                                        @click.stop="preview = null; $wire.set('profileAvatar', null); document.getElementById('profileAvatarInput').value = ''"
                                        class="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-gray-900 text-white shadow-sm hover:bg-gray-700"
                                        style="display:none"
                                        aria-label="{{ __('admin.remove') }}"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <div>
                                    <p class="text-sm font-medium text-gray-700">{{ __('admin.drag_an_image_here') }}</p>
                                    <p class="mt-0.5 text-xs text-gray-400">{{ __('admin.jpg_png_square_best') }}</p>
                                    <button type="button" @click="pick()" class="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-xs font-medium text-white hover:bg-gray-700">
                                        {{ __('admin.upload_photo') }}
                                    </button>
                                </div>
                            </div>
                            @error('profileAvatar')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.personal_details') }}</h2>
                            <p class="mt-0.5 text-xs text-gray-500">{{ __('admin.use_name_on_government_id') }}</p>
                        </div>

                        <div class="space-y-4">
                            {{-- First Name + Last Name --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">
                                        {{ __('admin.first_name') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input wire:model="profileFirstName" type="text" placeholder="{{ __('admin.first_name_placeholder') }}"
                                        class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                                    @error('profileFirstName')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">
                                        {{ __('admin.last_name') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input wire:model="profileLastName" type="text" placeholder="{{ __('admin.last_name_placeholder') }}"
                                        class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                                    @error('profileLastName')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- Email + Phone --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">
                                        {{ __('admin.email_address') }} <span class="text-red-500">*</span>
                                    </label>
                                    @if ($isEmailLocked)
                                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-600">
                                        <span class="flex-1 truncate">{{ $profileEmail }}</span>
                                        <svg class="h-4 w-4 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                        </svg>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-400">{{ __('admin.registered_email_cannot_change') }}</p>
                                    @else
                                    <input wire:model="profileEmail" type="email" placeholder="e.g, abcexample@gmail.com"
                                        class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                                    @error('profileEmail')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                    @endif
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">
                                        {{ __('admin.phone_number') }} @if($isEmailLocked)<span class="text-red-500">*</span>@endif
                                    </label>
                                    @if ($isEmailLocked)
                                    <div class="flex overflow-hidden rounded-lg border border-gray-300 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-200">
                                        @if($authUser->dial_code)
                                        <span class="flex items-center border-r border-gray-300 bg-gray-50 px-3 text-sm text-gray-500">{{ $authUser->dial_code }}</span>
                                        @endif
                                        <input wire:model="profilePhone" type="text" inputmode="numeric" maxlength="15"
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                            placeholder="9998887777"
                                            class="block flex-1 bg-white px-4 py-2.5 text-sm outline-none" />
                                    </div>
                                    @else
                                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-600">
                                        @if($authUser->dial_code)
                                            <span class="font-medium">{{ $authUser->dial_code }}</span>
                                            <span class="text-gray-300">|</span>
                                        @endif
                                        <span class="flex-1">{{ $authUser->phone ?? '—' }}</span>
                                        <span class="text-xs text-gray-400">{{ __('admin.registered') }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            {{-- DOB + Gender --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.date_of_birth') }}</label>
                                    <input wire:model="profileDob" type="date"
                                        class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                                    @error('profileDob')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.gender') }}</label>
                                    <select wire:model="profileGender"
                                        class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                        <option value="">{{ __('admin.choose_gender') }}</option>
                                        <option value="male">{{ __('admin.male') }}</option>
                                        <option value="female">{{ __('admin.female') }}</option>
                                        <option value="other">{{ __('admin.other') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                @endif

                {{-- ─── Step 4: Address ──────────────────────────────── --}}
                @if ($currentStep === 4)
                <div class="w-full max-w-4xl">

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 md:p-8 shadow-sm space-y-5">

                        {{-- Hidden lat/lng for state sync --}}
                        <input type="hidden" wire:model.live="addressLat">
                        <input type="hidden" wire:model.live="addressLng">

                        {{-- Row 1: Country + PIN Code --}}
                        <div class="grid grid-cols-2 gap-5" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem;">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                                    {{ __('admin.country') }}
                                </label>
                                <div class="relative">
                                    <select disabled
                                        class="block w-full appearance-none rounded-lg border border-gray-200 bg-gray-100 px-3.5 py-2.5 text-sm text-gray-600 cursor-not-allowed">
                                        @foreach ($countries as $country)
                                        <option value="{{ $country->id }}" @selected($selectedCountryId === $country->id)>{{ $country->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                                    PIN Code <span class="text-red-500">*</span>
                                </label>
                                <input wire:model.live.debounce.400ms="addressZipCode" type="text" placeholder="e.g, 450325"
                                    class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                                @error('addressZipCode')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Row 2: Street Address --}}
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                                {{ __('admin.street_address') }} <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="addressLine1" type="text" placeholder="House or building number, street, and area."
                                class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                            @error('addressLine1')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Row 3: Flat, floor or landmark --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-700">
                                {{ __('admin.flat_floor_or_landmark') }}
                            </label>
                            <input wire:model="addressLine2" type="text" placeholder="House or building number, street, and area."
                                class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                            <p class="mt-1 text-xs text-gray-400">(optional)</p>
                        </div>

                        {{-- Row 4: State + City --}}
                        <div class="grid grid-cols-2 gap-5" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem;">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                                    State
                                </label>
                                <div class="relative">
                                    <select wire:model.live="addressStateId"
                                        class="block w-full appearance-none rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                                        <option value="0">Choose State</option>
                                        @foreach ($states as $state)
                                        <option value="{{ $state->id }}">{{ $state->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </div>
                                </div>
                                @error('addressStateId')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                                    {{ __('admin.city') }}
                                </label>
                                <div class="relative">
                                    <select wire:model.live="addressCityId"
                                        class="block w-full appearance-none rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                        @disabled(!$addressStateId)>
                                        <option value="0">e.g, Mumbai</option>
                                        @foreach ($cities as $city)
                                        <option value="{{ $city->id }}">{{ $city->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </div>
                                </div>
                                @error('addressCityId')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Row 5: Map with Search --}}
                        @if ($mapProvider === 'openstreetmap')
                        <div
                            wire:ignore
                            x-data="{
                                lat: $wire.$entangle('addressLat'),
                                lng: $wire.$entangle('addressLng'),
                                streetAddress: $wire.$entangle('addressLine1'),
                                zipCode: $wire.$entangle('addressZipCode'),
                                countryIso: '{{ $countries->firstWhere('id', $selectedCountryId)?->iso_code ?? '' }}',
                                photonUrl: '{{ config('maps.photon_url', 'https://photon.komoot.io') }}',
                                defaultZoom: {{ config('maps.default_zoom', 13) }},
                                map: null,
                                marker: null,
                                debounceTimer: null,
                                suggestions: [],
                                showSuggestions: false,
                                searchQuery: '',
                                isLoading: false,
                                init() { this.$nextTick(() => this.setupMap()); },
                                setupMap() {
                                    if (typeof L !== 'undefined') { this.initLeaflet(); return; }
                                    if (!document.querySelector('link[href*=leaflet]')) {
                                        const css = document.createElement('link');
                                        css.rel = 'stylesheet';
                                        css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                                        document.head.appendChild(css);
                                    }
                                    const script = document.createElement('script');
                                    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                                    script.onload = () => this.initLeaflet();
                                    document.head.appendChild(script);
                                },
                                initLeaflet() {
                                    const defLat = parseFloat(this.lat) || 23.0225;
                                    const defLng = parseFloat(this.lng) || 72.5714;
                                    const has = !!(this.lat && this.lng);
                                    this.map = L.map(this.$refs.map).setView([defLat, defLng], has ? this.defaultZoom : 11);
                                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                        attribution: '© <a href=\'https://www.openstreetmap.org/copyright\'>OpenStreetMap</a> contributors',
                                        maxZoom: 19,
                                    }).addTo(this.map);
                                    if (has) { this.placeMarker(defLat, defLng); }
                                    this.map.on('click', (e) => {
                                        this.lat = e.latlng.lat.toFixed(8);
                                        this.lng = e.latlng.lng.toFixed(8);
                                        this.placeMarker(e.latlng.lat, e.latlng.lng);
                                    });
                                    this.$watch('lat', () => this.sync());
                                    this.$watch('lng', () => this.sync());
                                },
                                placeMarker(lat, lng) {
                                    if (this.marker) { this.marker.setLatLng([lat, lng]); }
                                    else {
                                        this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
                                        this.marker.on('dragend', (e) => {
                                            const p = e.target.getLatLng();
                                            this.lat = p.lat.toFixed(8);
                                            this.lng = p.lng.toFixed(8);
                                        });
                                    }
                                    this.map.setView([lat, lng], this.defaultZoom);
                                },
                                sync() {
                                    if (!this.lat || !this.lng || !this.map) return;
                                    const lat = parseFloat(this.lat), lng = parseFloat(this.lng);
                                    if (!isNaN(lat) && !isNaN(lng)) this.placeMarker(lat, lng);
                                },
                                onSearchInput() {
                                    clearTimeout(this.debounceTimer);
                                    if (this.searchQuery.length < 3) {
                                        this.suggestions = [];
                                        this.showSuggestions = false;
                                        return;
                                    }
                                    this.debounceTimer = setTimeout(() => this.fetchSuggestions(), 400);
                                },
                                async fetchSuggestions() {
                                    this.isLoading = true;
                                    const params = new URLSearchParams({ q: this.searchQuery, limit: '10', lang: 'en' });
                                    if (this.countryIso) { params.set('countrycode', this.countryIso.toLowerCase()); }
                                    let biasLat = parseFloat(this.lat);
                                    let biasLng = parseFloat(this.lng);
                                    if ((isNaN(biasLat) || isNaN(biasLng)) && this.map) {
                                        const centre = this.map.getCenter();
                                        biasLat = centre.lat;
                                        biasLng = centre.lng;
                                    }
                                    if (!isNaN(biasLat) && !isNaN(biasLng)) {
                                        params.set('lat', biasLat.toString());
                                        params.set('lon', biasLng.toString());
                                        params.set('location_bias_scale', '0.5');
                                    }
                                    try {
                                        const res = await fetch(this.photonUrl + '/api?' + params.toString());
                                        const data = await res.json();
                                        this.suggestions = (data.features || []).map(f => {
                                            const p = f.properties;
                                            const parts = [p.name, p.street, p.district || p.suburb, p.city || p.town || p.village || p.county, p.state].filter(Boolean);
                                            return { ...f, _label: [...new Set(parts)].join(', '), _id: (p.osm_id || '') + '_' + (p.osm_type || '') };
                                        });
                                        this.showSuggestions = this.suggestions.length > 0;
                                    } catch (e) {
                                        this.suggestions = [];
                                    } finally {
                                        this.isLoading = false;
                                    }
                                },
                                selectSuggestion(item) {
                                    this.searchQuery = item._label;
                                    this.showSuggestions = false;
                                    this.suggestions = [];
                                    const lng = item.geometry.coordinates[0];
                                    const lat = item.geometry.coordinates[1];
                                    this.lat = lat.toFixed(8);
                                    this.lng = lng.toFixed(8);
                                    this.placeMarker(lat, lng);
                                    const p = item.properties;
                                    if (p.postcode) { this.zipCode = p.postcode; }
                                    const streetParts = [p.housenumber, p.street, p.district].filter(Boolean);
                                    if (streetParts.length) { this.streetAddress = streetParts.join(', '); }
                                    else if (p.name) { this.streetAddress = p.name; }
                                    const stateName = p.state || '';
                                    const cityName = p.city || p.town || p.county || '';
                                    if (stateName) { $wire.setStateAndCityFromPlace(stateName, cityName); }
                                },
                            }"
                            class="space-y-3"
                            @click.outside="showSuggestions = false"
                        >
                            {{-- Search Bar --}}
                            <div class="relative" style="z-index: 1000;">
                                <div class="flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 shadow-xs focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100">
                                    <svg class="h-4 w-4 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                    </svg>
                                    <input
                                        type="text"
                                        x-model="searchQuery"
                                        @input="onSearchInput"
                                        @keydown.escape="showSuggestions = false"
                                        placeholder="Search address or location on map..."
                                        class="w-full bg-transparent text-sm text-gray-900 placeholder-gray-400 outline-none"
                                    />
                                    <svg x-show="isLoading" class="h-4 w-4 animate-spin flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>

                                {{-- Suggestions dropdown --}}
                                <div
                                    x-show="showSuggestions"
                                    x-transition
                                    class="absolute mt-1 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg max-h-60 overflow-y-auto"
                                    style="z-index: 1001;"
                                >
                                    <template x-for="item in suggestions" :key="item._id">
                                        <button
                                            type="button"
                                            @click="selectSuggestion(item)"
                                            class="flex w-full cursor-pointer items-start gap-2.5 px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-blue-50/60 transition-colors"
                                        >
                                            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                            </svg>
                                            <span x-text="item._label" class="line-clamp-2"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div x-ref="map" style="height: 240px;" class="w-full rounded-xl border border-gray-200 overflow-hidden shadow-xs"></div>
                        </div>

                        @elseif ($mapProvider === 'google' && $googleMapsApiKey)
                        <div
                            wire:ignore
                            x-data="{
                                lat: $wire.$entangle('addressLat'),
                                lng: $wire.$entangle('addressLng'),
                                streetAddress: $wire.$entangle('addressLine1'),
                                zipCode: $wire.$entangle('addressZipCode'),
                                countryIso: '{{ $countries->firstWhere('id', $selectedCountryId)?->iso_code ?? '' }}',
                                defaultZoom: {{ config('maps.default_zoom', 13) }},
                                map: null,
                                marker: null,
                                autocomplete: null,
                                init() {
                                    if (typeof google !== 'undefined' && google.maps && google.maps.places) { this.setupMap(); return; }
                                    if (!document.querySelector('script[src*=\'maps.googleapis.com\']')) {
                                        const s = document.createElement('script');
                                        s.src = 'https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&libraries=places&callback=Function.prototype';
                                        s.async = true; s.defer = true;
                                        s.onload = () => this.setupMap();
                                        document.head.appendChild(s);
                                    } else {
                                        const interval = setInterval(() => {
                                            if (typeof google !== 'undefined' && google.maps && google.maps.places) {
                                                clearInterval(interval);
                                                this.setupMap();
                                            }
                                        }, 100);
                                    }
                                },
                                setupMap() {
                                    const defLat = parseFloat(this.lat) || 23.0225;
                                    const defLng = parseFloat(this.lng) || 72.5714;
                                    const has = !!(this.lat && this.lng);
                                    this.map = new google.maps.Map(this.$refs.map, {
                                        center: { lat: defLat, lng: defLng },
                                        zoom: has ? this.defaultZoom : 11,
                                        mapTypeControl: false, streetViewControl: false, fullscreenControl: false,
                                    });
                                    this.marker = new google.maps.Marker({ map: this.map, draggable: true, position: has ? { lat: defLat, lng: defLng } : null, visible: has });
                                    this.marker.addListener('dragend', (e) => {
                                        this.lat = e.latLng.lat().toFixed(8);
                                        this.lng = e.latLng.lng().toFixed(8);
                                    });
                                    this.map.addListener('click', (e) => {
                                        this.lat = e.latLng.lat().toFixed(8);
                                        this.lng = e.latLng.lng().toFixed(8);
                                        this.marker.setPosition(e.latLng); this.marker.setVisible(true);
                                    });
                                    this.$watch('lat', () => this.sync());
                                    this.$watch('lng', () => this.sync());

                                    if (this.$refs.searchContainer) {
                                        this.autocomplete = new google.maps.places.PlaceAutocompleteElement();
                                        if (this.countryIso) { this.autocomplete.includedRegionCodes = [this.countryIso.toLowerCase()]; }
                                        this.autocomplete.style.display = 'block';
                                        this.autocomplete.style.width = '100%';
                                        this.$refs.searchContainer.replaceChildren(this.autocomplete);
                                        this.autocomplete.addEventListener('gmp-select', async ({ placePrediction }) => {
                                            const place = placePrediction.toPlace();
                                            await place.fetchFields({ fields: ['formattedAddress', 'location', 'addressComponents'] });
                                            if (!place.location) return;
                                            this.map.setCenter(place.location);
                                            this.map.setZoom(this.defaultZoom);
                                            this.marker.setPosition(place.location);
                                            this.marker.setVisible(true);
                                            this.lat = place.location.lat().toFixed(8);
                                            this.lng = place.location.lng().toFixed(8);

                                            if (place.addressComponents) {
                                                const streetTypes = ['subpremise', 'premise', 'street_number', 'route', 'neighborhood', 'sublocality_level_2', 'sublocality_level_1'];
                                                let stateName = '', cityName = '', cityComponent = null;
                                                for (const comp of place.addressComponents) {
                                                    if (comp.types.includes('administrative_area_level_1')) { stateName = comp.longText; }
                                                    if (comp.types.includes('locality')) { cityName = comp.longText; cityComponent = comp; }
                                                    else if (!cityName && comp.types.includes('administrative_area_level_2')) { cityName = comp.longText; cityComponent = comp; }
                                                    else if (!cityName && comp.types.includes('sublocality_level_1')) { cityName = comp.longText; cityComponent = comp; }
                                                }
                                                const streetParts = [];
                                                for (const comp of place.addressComponents) {
                                                    if (comp.types.includes('postal_code')) { this.zipCode = comp.longText; }
                                                    if (comp === cityComponent) continue;
                                                    if (streetTypes.some(t => comp.types.includes(t))) { streetParts.push(comp.longText); }
                                                }
                                                this.streetAddress = streetParts.length ? streetParts.join(', ') : (place.formattedAddress || '');
                                                if (stateName) { $wire.setStateAndCityFromPlace(stateName, cityName); }
                                            }
                                        });
                                    }
                                },
                                sync() {
                                    if (!this.lat || !this.lng || !this.map || !this.marker) return;
                                    const pos = { lat: parseFloat(this.lat), lng: parseFloat(this.lng) };
                                    if (isNaN(pos.lat) || isNaN(pos.lng)) return;
                                    this.marker.setPosition(pos); this.marker.setVisible(true);
                                    this.map.setCenter(pos); this.map.setZoom(15);
                                },
                            }"
                            class="space-y-3"
                        >
                            <div class="map-picker-search">
                                <div x-ref="searchContainer" style="display: block; width: 100%; min-height: 2.5rem;"></div>
                            </div>
                            <div x-ref="map" style="height: 240px;" class="w-full rounded-xl border border-gray-200 overflow-hidden shadow-xs"></div>
                        </div>
                        @endif

                    </div>
                </div>
                @endif

                {{-- ─── Step 5: Registration Details ─────────────────── --}}
                @if ($currentStep === 5)
                <div class="w-full max-w-4xl">

                    @if ($registrationFields->isEmpty())
                    <div class="flex flex-col items-center justify-center rounded-2xl border border-gray-200 bg-white p-12 shadow-sm">
                        <svg class="h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <p class="mt-3 text-sm font-medium text-gray-500">{{ __('admin.no_additional_documentation') }}</p>
                        <p class="mt-1 text-xs text-gray-400">{{ __('admin.all_set_click_finish') }}</p>
                    </div>
                    @else
                    @php
                        $standardFields = $registrationFields->reject(fn($f) => $f->field_type->value === 'file_upload');
                        $fileFields     = $registrationFields->filter(fn($f) => $f->field_type->value === 'file_upload');
                        $hasStandard    = $standardFields->isNotEmpty();
                        $hasFiles       = $fileFields->isNotEmpty();
                    @endphp
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 md:p-8 shadow-sm space-y-6">

                        {{-- ── Standard fields (text / number / date / dropdown / textarea / checkboxes) ── --}}
                        @if ($hasStandard)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            @foreach ($standardFields as $field)
                            @php
                                $key          = 'field_' . $field->id;
                                $isFullWidth  = in_array($field->field_type->value, ['text_area', 'checkboxes']);
                                $helperText   = match ($field->field_type->value) {
                                    'text_field', 'text_area' => $field->max_length ? "Max {$field->max_length} characters." : null,
                                    // min_number/max_number mean digit COUNT here (e.g. 12 for
                                    // Aadhar), not a numeric value range.
                                    'number_input' => match (true) {
                                        (bool) $field->min_number && (bool) $field->max_number && $field->min_number === $field->max_number => "Must be exactly {$field->min_number} digits.",
                                        (bool) $field->min_number && (bool) $field->max_number => "Must be between {$field->min_number} and {$field->max_number} digits.",
                                        (bool) $field->min_number => "Minimum {$field->min_number} digits.",
                                        (bool) $field->max_number => "Maximum {$field->max_number} digits.",
                                        default => null,
                                    },
                                    default => null,
                                };
                            @endphp
                            <div class="{{ $isFullWidth ? 'col-span-1 sm:col-span-2' : '' }}">
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                                    {{ $field->name }}
                                    @if ($field->is_mandatory) <span class="text-red-500">*</span> @endif
                                </label>

                                @if ($field->field_type->value === 'text_field')
                                <input wire:model="regValues.{{ $key }}" type="text"
                                    @if ($field->max_length) maxlength="{{ $field->max_length }}" @endif
                                    class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />

                                @elseif ($field->field_type->value === 'text_area')
                                <textarea wire:model="regValues.{{ $key }}" rows="4"
                                    @if ($field->max_length) maxlength="{{ $field->max_length }}" @endif
                                    class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>

                                @elseif ($field->field_type->value === 'number_input')
                                {{-- type=text + inputmode=numeric, not type=number: min_number/max_number are a
                                     digit COUNT (e.g. 12 for Aadhar), and a native number input's min/max would
                                     instead cap the numeric VALUE, rejecting any real multi-digit ID number. --}}
                                <input wire:model="regValues.{{ $key }}" type="text" inputmode="numeric" pattern="[0-9]*"
                                    @if ($field->min_number) minlength="{{ $field->min_number }}" @endif
                                    @if ($field->max_number) maxlength="{{ $field->max_number }}" @endif
                                    class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />

                                @elseif ($field->field_type->value === 'date')
                                <input wire:model="regValues.{{ $key }}" type="date"
                                    class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />

                                @elseif ($field->field_type->value === 'dropdown')
                                <select wire:model="regValues.{{ $key }}"
                                    class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <option value="">Select an option</option>
                                    @foreach ($field->options ?? [] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>

                                @elseif ($field->field_type->value === 'checkboxes')
                                <div class="mt-1 grid grid-cols-2 gap-x-4 gap-y-2">
                                    @foreach ($field->options ?? [] as $option)
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input wire:model="regValues.{{ $key }}" type="checkbox" value="{{ $option }}"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                        {{ $option }}
                                    </label>
                                    @endforeach
                                </div>
                                @endif

                                @if ($helperText)
                                <p class="mt-1 text-xs text-gray-400">{{ $helperText }}</p>
                                @endif

                                @error('regValues.' . $key)
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            @endforeach
                        </div>
                        @endif

                        {{-- ── File upload fields ─────────────────────────────────────────── --}}
                        @if ($hasFiles)
                        <div class="{{ $hasStandard ? 'border-t border-gray-200 pt-5' : '' }}">
                            <p class="mb-4 text-sm font-semibold text-gray-900">{{ __('admin.required_documents') }}</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                @foreach ($fileFields as $field)
                                @php $key = 'field_' . $field->id; $fileKey = 'file_' . $field->id; @endphp
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                                        {{ $field->name }}
                                        @if ($field->is_mandatory) <span class="text-red-500">*</span> @endif
                                    </label>
                                    <div
                                        x-data="{ isDragging: false, fileName: null, fileError: null }"
                                        @dragover.prevent="isDragging = true"
                                        @dragleave.prevent="isDragging = false"
                                        @drop.prevent="
                                            isDragging = false;
                                            fileError = null;
                                            const files = $event.dataTransfer.files;
                                            if (files.length) {
                                                const file = files[0];
                                                const allowed = ['image/jpeg', 'image/png', 'application/pdf'];
                                                if (!allowed.includes(file.type)) {
                                                    fileError = 'Only JPG, PNG, and PDF files are allowed.';
                                                    return;
                                                }
                                                const maxMb = {{ $field->max_file_size ?? 2 }};
                                                if (file.size > maxMb * 1024 * 1024) {
                                                    fileError = 'File exceeds the ' + maxMb + 'MB limit.';
                                                    return;
                                                }
                                                const input = $el.querySelector('input[type=file]');
                                                const dt = new DataTransfer();
                                                dt.items.add(file);
                                                input.files = dt.files;
                                                fileName = file.name;
                                                input.dispatchEvent(new Event('change'));
                                            }
                                        "
                                        :class="isDragging ? 'border-blue-400 bg-blue-50' : 'border-gray-300 bg-gray-50'"
                                        class="flex flex-col items-center rounded-lg border-2 border-dashed px-4 py-6 transition-colors">
                                        @if (isset($regFiles[$fileKey]) && $regFiles[$fileKey])
                                        <svg class="mb-2 h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        <p class="text-sm font-medium text-green-600">{{ $regFiles[$fileKey]->getClientOriginalName() }}</p>
                                        <label class="mt-2 cursor-pointer text-xs text-blue-600 hover:text-blue-700">
                                            Change file
                                            <input wire:model="regFiles.{{ $fileKey }}" type="file"
                                                accept=".jpg,.jpeg,.png,.pdf" class="hidden" />
                                        </label>
                                        @elseif (isset($existingRegFileLabels[$fileKey]))
                                        <svg class="mb-2 h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m5.231 13.481L15 17.25m-1.519-3.75L12 17.25m-3.75 0h7.5" />
                                        </svg>
                                        <p class="text-sm font-medium text-gray-600">{{ __('admin.already_uploaded') }}: {{ $existingRegFileLabels[$fileKey] }}</p>
                                        <label class="mt-2 cursor-pointer text-xs text-blue-600 hover:text-blue-700">
                                            {{ __('admin.replace_file') }}
                                            <input wire:model="regFiles.{{ $fileKey }}" type="file"
                                                accept=".jpg,.jpeg,.png,.pdf" class="hidden" />
                                        </label>
                                        @else
                                        <template x-if="fileName">
                                            <div class="flex flex-col items-center">
                                                <svg class="mb-2 h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                                <p class="text-sm font-medium text-green-600" x-text="fileName"></p>
                                            </div>
                                        </template>
                                        <template x-if="!fileName">
                                            <div class="flex flex-col items-center">
                                                <svg class="mb-2 h-8 w-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                                </svg>
                                                <p class="text-sm text-gray-500">
                                                    Drag and drop or
                                                    <label class="cursor-pointer font-medium text-blue-600 hover:text-blue-700">
                                                        choose file
                                                        <input wire:model="regFiles.{{ $fileKey }}" type="file"
                                                            accept=".jpg,.jpeg,.png,.pdf" class="hidden"
                                                            x-on:change="fileName = $event.target.files[0]?.name ?? null" />
                                                    </label>
                                                </p>
                                            </div>
                                        </template>
                                        <p class="mt-2 text-xs text-gray-400">
                                            Max {{ $field->max_file_size ? $field->max_file_size . 'MB' : '2MB' }} &mdash; JPG, PNG, PDF
                                        </p>
                                        @endif
                                    </div>
                                    <p x-show="fileError" x-text="fileError" class="mt-1 text-xs text-red-600"></p>
                                    @error('regFiles.' . $fileKey)
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                    </div>
                    @endif

                </div>
                @endif

            </div>
            {{-- End Scrollable Content --}}

            {{-- ── Footer Navigation ────────────────────────────────── --}}
            <div class="flex flex-shrink-0 items-center justify-between bg-white px-8 py-4" style="border-top: 1px solid #EDEDED;">
                <div></div>
                <div class="flex items-center gap-3">
                    @if ($currentStep > 1)
                    <button wire:click="previousStep" type="button"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 shadow-xs">
                        <svg class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        {{ __('admin.previous') }}
                    </button>
                    @endif

                    @if ($currentStep < $totalSteps)
                    <button wire:click="nextStep" wire:loading.attr="disabled" type="button"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 shadow-xs disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="nextStep" class="inline-flex items-center gap-2">
                            {{ __('admin.next') }}
                            <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="nextStep">{{ __('admin.processing_dots') }}</span>
                    </button>
                    @else
                    <button wire:click="complete" wire:loading.attr="disabled" type="button"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 shadow-xs disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="complete" class="inline-flex items-center gap-2">
                            {{ __('admin.finish_partner_setup') }}
                            <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="complete">{{ __('admin.processing_dots') }}</span>
                    </button>
                    @endif
                </div>
            </div>
            @endif

        </div>
        {{-- End Right Content Area --}}

    </div>

</div>
