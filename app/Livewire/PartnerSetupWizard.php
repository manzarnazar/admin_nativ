<?php

namespace App\Livewire;

use App\Enums\PartnerVerificationStatus;
use App\Enums\RegistrationFieldScope;
use App\Enums\RegistrationFieldType;
use App\Enums\Status;
use App\Enums\UserRole;
use App\Models\Country;
use App\Models\Partner;
use App\Models\PartnerRegistrationValue;
use App\Models\PropertyType;
use App\Models\RefCity;
use App\Models\RefState;
use App\Models\RegistrationField;
use App\Models\Setting;
use App\Models\User;
use App\Services\PartnerVerificationService;
use App\Support\MapProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.setup')]
class PartnerSetupWizard extends Component
{
    use WithFileUploads;

    public int $currentStep = 1;

    // ── Post-submission status (already completed the wizard, awaiting/denied review) ──

    public bool $isSubmittedForReview = false;

    public ?PartnerVerificationStatus $partnerVerificationStatus = null;

    public ?string $partnerRejectionReason = null;

    // ── Step 1: Country ──────────────────────────────────────────────────────

    public int $selectedCountryId = 0;

    public string $countrySearch = '';

    // ── Step 2: Property Type ────────────────────────────────────────────────

    public int $selectedPropertyTypeId = 0;

    // ── Step 3: Profile ──────────────────────────────────────────────────────

    /** @var TemporaryUploadedFile|null */
    public $profileAvatar = null;

    public string $profileFirstName = '';

    public string $profileLastName = '';

    public string $profileEmail = '';

    public string $profilePhone = '';

    public string $profileDob = '';

    public string $profileGender = '';

    // ── Step 4: Address ──────────────────────────────────────────────────────

    public string $addressLine1 = '';

    public string $addressLine2 = '';

    public string $addressZipCode = '';

    public int $addressStateId = 0;

    public int $addressCityId = 0;

    public string $addressLat = '';

    public string $addressLng = '';

    // ── Step 5: Registration Details ─────────────────────────────────────────

    /** @var array<string, mixed> Dynamic registration field values keyed by "field_<id>" */
    public array $regValues = [];

    /** @var array<string, mixed> File upload properties keyed by "file_<id>" */
    public array $regFiles = [];

    /** @var array<string, string> Existing stored filenames keyed by "file_<id>", set when editing after Rejected/CorrectionRequested */
    public array $existingRegFileLabels = [];

    // ────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || $user->role !== UserRole::Partner) {
            $this->redirect('/');

            return;
        }

        /** @var Partner|null $partner */
        $partner = $user->partner;

        // A partner who finished the wizard at least once and isn't currently
        // being re-edited via startEditing() (setup_step reset to 0 in complete()).
        $isFullyCompleted = $partner?->property_type_id !== null && (int) ($partner->setup_step ?? 0) === 0;

        if ($isFullyCompleted) {
            if ($partner->verification_status === PartnerVerificationStatus::Approved) {
                $this->redirect('/partner');

                return;
            }

            $this->isSubmittedForReview = true;
            $this->partnerVerificationStatus = $partner->verification_status;
            $this->partnerRejectionReason = $partner->rejection_reason;

            return;
        }

        $this->prefillProfileFromUser($user);
        $this->resumeFromDraft($partner);
        $this->loadExistingRegistrationFileLabels($partner);
    }

    /**
     * A Rejected/CorrectionRequested partner clicking "Make Changes" on the
     * status screen re-enters the same stepper instead of a separate edit page.
     * Seeds setup_draft from their real saved data (there's no draft left after
     * complete() cleared it) and resumes through the normal resumeFromDraft()
     * path, so a mid-edit refresh behaves exactly like a mid-signup refresh.
     */
    public function startEditing(): void
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Partner|null $partner */
        $partner = $user->partner;

        if (! $partner || ! in_array($partner->verification_status, [
            PartnerVerificationStatus::Rejected,
            PartnerVerificationStatus::CorrectionRequested,
        ], true)) {
            return;
        }

        $this->prefillProfileFromUser($user);

        $partner->update([
            // Opens on the Profile step, not Country/Property Type — those are
            // rarely what a rejection/correction is actually about. Previous
            // still works normally if they do need to go back and fix those.
            'setup_step' => 3,
            'setup_draft' => $this->buildDraftFromPartner($partner),
        ]);

        $this->isSubmittedForReview = false;

        $this->resumeFromDraft($partner->fresh());
        $this->loadExistingRegistrationFileLabels($partner);
    }

    private function prefillProfileFromUser(User $user): void
    {
        $this->profileFirstName = $user->first_name ?? '';
        $this->profileLastName = $user->last_name ?? '';
        $this->profileEmail = $user->email ?? '';
        $this->profilePhone = $user->phone ?? '';
        $this->profileDob = $user->date_of_birth ? $user->date_of_birth->format('Y-m-d') : '';
        $this->profileGender = $user->gender?->value ?? '';
    }

    /** @return array<string, mixed> */
    private function buildDraftFromPartner(Partner $partner): array
    {
        $activeCountryId = $partner->countries()->wherePivot('is_active', true)->first()?->id
            ?? $partner->countries()->first()?->id;

        $regValues = [];

        foreach ($partner->registrationValues()->with('registrationField')->get() as $regValue) {
            $field = $regValue->registrationField;

            if (! $field || $field->field_type === RegistrationFieldType::FileUpload) {
                continue;
            }

            $regValues['field_'.$field->id] = $field->field_type === RegistrationFieldType::Checkboxes
                ? $regValue->value
                : ($regValue->value[0] ?? '');
        }

        return [
            'selectedCountryId' => (int) $activeCountryId,
            'selectedPropertyTypeId' => (int) $partner->property_type_id,
            'profileFirstName' => $this->profileFirstName,
            'profileLastName' => $this->profileLastName,
            'profileEmail' => $this->profileEmail,
            'profilePhone' => $this->profilePhone,
            'profileDob' => $this->profileDob,
            'profileGender' => $this->profileGender,
            'addressLine1' => (string) $partner->address,
            'addressLine2' => (string) $partner->address_line2,
            'addressZipCode' => (string) $partner->zip_code,
            'addressStateId' => (int) $partner->ref_state_id,
            'addressCityId' => (int) $partner->ref_city_id,
            'addressLat' => (string) $partner->latitude,
            'addressLng' => (string) $partner->longitude,
            'regValues' => $regValues,
        ];
    }

    /**
     * Existing registration documents aren't restorable into $regFiles (they're
     * real stored files, not TemporaryUploadedFile instances) — this just tracks
     * which file fields already have something on record, so the step 5 UI can
     * show "already uploaded" instead of an empty drop zone, and so re-submitting
     * without touching a field doesn't wipe or re-require its file.
     */
    private function loadExistingRegistrationFileLabels(?Partner $partner): void
    {
        $this->existingRegFileLabels = [];

        if (! $partner) {
            return;
        }

        foreach ($partner->registrationValues()->with('registrationField')->get() as $regValue) {
            $field = $regValue->registrationField;

            if (! $field || $field->field_type !== RegistrationFieldType::FileUpload) {
                continue;
            }

            $firstPath = $regValue->value[0] ?? null;

            if ($firstPath) {
                $this->existingRegFileLabels['file_'.$field->id] = basename((string) $firstPath);
            }
        }
    }

    public function nextStep(): void
    {
        $this->validateCurrentStep();

        $this->currentStep++;
        $this->persistProgress();
    }

    public function previousStep(): void
    {
        $this->currentStep = max(1, $this->currentStep - 1);
        $this->persistProgress();
    }

    /**
     * Rehydrate step position and field values from a partial save so a page
     * refresh mid-wizard resumes where the partner left off instead of
     * restarting at step 1. Doesn't touch property_type_id/address/etc. —
     * those are only written atomically in complete(), since several places
     * (EnsurePartnerSetupComplete, PartnerVerificationService) treat
     * property_type_id !== null as "onboarding fully finished".
     */
    private function resumeFromDraft(?Partner $partner): void
    {
        if (! $partner || $partner->setup_step < 1 || ! is_array($partner->setup_draft)) {
            return;
        }

        $draft = $partner->setup_draft;

        $this->selectedCountryId = (int) ($draft['selectedCountryId'] ?? $this->selectedCountryId);
        $this->selectedPropertyTypeId = (int) ($draft['selectedPropertyTypeId'] ?? $this->selectedPropertyTypeId);
        $this->profileFirstName = $draft['profileFirstName'] ?? $this->profileFirstName;
        $this->profileLastName = $draft['profileLastName'] ?? $this->profileLastName;
        $this->profileEmail = $draft['profileEmail'] ?? $this->profileEmail;
        $this->profilePhone = $draft['profilePhone'] ?? $this->profilePhone;
        $this->profileDob = $draft['profileDob'] ?? $this->profileDob;
        $this->profileGender = $draft['profileGender'] ?? $this->profileGender;
        $this->addressLine1 = $draft['addressLine1'] ?? $this->addressLine1;
        $this->addressLine2 = $draft['addressLine2'] ?? $this->addressLine2;
        $this->addressZipCode = $draft['addressZipCode'] ?? $this->addressZipCode;
        $this->addressStateId = (int) ($draft['addressStateId'] ?? $this->addressStateId);
        $this->addressCityId = (int) ($draft['addressCityId'] ?? $this->addressCityId);
        $this->addressLat = $draft['addressLat'] ?? $this->addressLat;
        $this->addressLng = $draft['addressLng'] ?? $this->addressLng;
        $this->regValues = is_array($draft['regValues'] ?? null) ? $draft['regValues'] : $this->regValues;

        // Same as selectCountry(): Checkboxes fields must already be an array
        // before Livewire renders them, or the group's checkboxes bind to one
        // shared value instead of toggling independently. A resumed draft may
        // predate the partner ever touching a given checkbox field.
        foreach ($this->loadRegistrationFields() as $field) {
            if ($field->field_type === RegistrationFieldType::Checkboxes && ! is_array($this->regValues['field_'.$field->id] ?? null)) {
                $this->regValues['field_'.$field->id] = [];
            }
        }

        $this->currentStep = max(1, min($partner->setup_step, $this->getTotalStepsProperty()));
    }

    /**
     * Saves progress to a draft column so it survives a refresh. File
     * uploads (avatar, registration file fields) aren't serializable to
     * JSON and are left for the partner to re-attach if they refresh
     * before completing.
     */
    private function persistProgress(): void
    {
        $partner = Auth::user()?->partner;

        if (! $partner) {
            return;
        }

        $partner->update([
            'setup_step' => $this->currentStep,
            'setup_draft' => [
                'selectedCountryId' => $this->selectedCountryId,
                'selectedPropertyTypeId' => $this->selectedPropertyTypeId,
                'profileFirstName' => $this->profileFirstName,
                'profileLastName' => $this->profileLastName,
                'profileEmail' => $this->profileEmail,
                'profilePhone' => $this->profilePhone,
                'profileDob' => $this->profileDob,
                'profileGender' => $this->profileGender,
                'addressLine1' => $this->addressLine1,
                'addressLine2' => $this->addressLine2,
                'addressZipCode' => $this->addressZipCode,
                'addressStateId' => $this->addressStateId,
                'addressCityId' => $this->addressCityId,
                'addressLat' => $this->addressLat,
                'addressLng' => $this->addressLng,
                'regValues' => $this->regValues,
            ],
        ]);
    }

    public function selectCountry(int $countryId): void
    {
        $this->selectedCountryId = $countryId;
        $this->addressStateId = 0;
        $this->addressCityId = 0;
        $this->addressLat = '';
        $this->addressLng = '';

        // Livewire only binds multiple checkboxes sharing one wire:model path to
        // individual array membership when that property is already an array before
        // they render — otherwise all checkboxes in the group toggle together as one
        // shared scalar. Registration fields are country-scoped, so this is the
        // earliest point they're known.
        foreach ($this->loadRegistrationFields() as $field) {
            if ($field->field_type === RegistrationFieldType::Checkboxes) {
                $this->regValues['field_'.$field->id] = [];
            }
        }
    }

    public function selectPropertyType(int $typeId): void
    {
        $this->selectedPropertyTypeId = $typeId;
    }

    public function updatedAddressZipCode(): void
    {
        $zip = trim($this->addressZipCode);

        if (strlen($zip) < 3) {
            return;
        }

        $countryIso = Country::query()
            ->where('id', $this->selectedCountryId)
            ->value('iso_code') ?? 'IN';

        try {
            $url = 'https://nominatim.openstreetmap.org/search?postalcode='.urlencode($zip).'&country='.urlencode($countryIso).'&format=json&addressdetails=1';
            $userAgent = (string) config('app.name', 'eStay').'/1.0 ('.url('/').')';
            $response = Http::withHeaders(['User-Agent' => $userAgent])->timeout(3)->get($url);

            if ($response->successful()) {
                $data = $response->json();

                if (! empty($data[0])) {
                    $item = $data[0];

                    if (! empty($item['lat']) && ! empty($item['lon'])) {
                        $this->addressLat = (string) $item['lat'];
                        $this->addressLng = (string) $item['lon'];
                    }

                    if (! empty($item['address'])) {
                        $address = $item['address'];
                        $stateName = $address['state'] ?? '';

                        $candidates = [
                            $address['city'] ?? null,
                            $address['town'] ?? null,
                            $address['village'] ?? null,
                            $address['municipality'] ?? null,
                            $address['county'] ?? null,
                            $address['state_district'] ?? null,
                            $address['city_district'] ?? null,
                        ];

                        foreach ($candidates as $cand) {
                            if (! $cand) {
                                continue;
                            }

                            $cleanCand = trim(str_replace(['District', 'Taluka', 'County', 'City', 'Zone 1', 'Zone 2', 'Zone 3', 'Zone 4', 'Zone 5'], '', $cand));
                            if ($cleanCand === '') {
                                $cleanCand = trim($cand);
                            }

                            $this->setStateAndCityFromPlace($stateName, $cleanCand);
                            if ($this->addressCityId > 0) {
                                break;
                            }
                        }

                        if ($this->addressStateId === 0 && $stateName !== '') {
                            $this->setStateAndCityFromPlace($stateName, '');
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silence API network timeouts gracefully
        }
    }

    public function updatedAddressStateId(): void
    {
        $this->addressCityId = 0;
        $this->addressLat = '';
        $this->addressLng = '';
    }

    public function updatedAddressCityId(): void
    {
        if (! $this->addressCityId) {
            $this->addressLat = '';
            $this->addressLng = '';

            return;
        }

        $city = RefCity::find($this->addressCityId);

        if ($city) {
            $this->addressLat = (string) $city->latitude;
            $this->addressLng = (string) $city->longitude;
        }
    }

    public function setStateAndCityFromPlace(string $stateName, string $cityName): void
    {
        if ($this->selectedCountryId === 0) {
            return;
        }

        $refCountryId = Country::query()
            ->where('id', $this->selectedCountryId)
            ->value('ref_country_id');

        if (! $refCountryId) {
            return;
        }

        $stateId = RefState::query()
            ->where('country_id', $refCountryId)
            ->where('name', 'LIKE', '%'.$stateName.'%')
            ->value('id');

        if (! $stateId) {
            return;
        }

        $this->addressStateId = $stateId;
        $this->addressCityId = 0;

        if ($cityName !== '') {
            $cityId = RefCity::query()
                ->where('state_id', $stateId)
                ->where('name', 'LIKE', '%'.$cityName.'%')
                ->value('id');

            if ($cityId) {
                $this->addressCityId = $cityId;
            }
        }
    }

    public function complete(): void
    {
        if ($this->totalSteps === 5) {
            $this->validateRegFieldsStep();
        } else {
            $this->validateAddressStep();
        }

        /** @var User $user */
        $user = Auth::user();

        /** @var Partner $partner */
        $partner = $user->partner;

        DB::transaction(function () use ($user, $partner): void {
            $isEmailAuth = $user->auth_provider === 'email';

            $userUpdate = [
                'first_name' => $this->profileFirstName,
                'last_name' => $this->profileLastName,
                'email' => $isEmailAuth ? $user->email : $this->profileEmail,
                'phone' => $isEmailAuth ? ($this->profilePhone ?: $user->phone) : $user->phone,
                'date_of_birth' => $this->profileDob ?: null,
                'gender' => $this->profileGender ?: null,
            ];

            if ($this->profileAvatar) {
                $userUpdate['avatar'] = $this->profileAvatar->store('partners/avatars', 'public');
            }

            $user->update($userUpdate);

            $selectedCountry = Country::find($this->selectedCountryId);
            $refCity = $this->addressCityId ? RefCity::find($this->addressCityId) : null;
            $refState = $this->addressStateId ? RefState::find($this->addressStateId) : null;

            $partner->countries()->syncWithoutDetaching([
                $this->selectedCountryId => ['is_active' => true],
            ]);

            $partner->update([
                'property_type_id' => $this->selectedPropertyTypeId,
                'setup_step' => 0,
                'setup_draft' => null,
                'address' => $this->addressLine1,
                'address_line2' => $this->addressLine2 ?: null,
                'zip_code' => $this->addressZipCode,
                'city' => $refCity?->name,
                'state_province' => $refState?->name,
                'country' => $selectedCountry?->name,
                'ref_country_id' => $selectedCountry?->ref_country_id,
                'ref_city_id' => $this->addressCityId ?: null,
                'ref_state_id' => $this->addressStateId ?: null,
                'latitude' => $this->addressLat ?: null,
                'longitude' => $this->addressLng ?: null,
            ]);

            $fields = $this->loadRegistrationFields();

            foreach ($fields as $field) {
                $key = 'field_'.$field->id;

                if ($field->field_type === RegistrationFieldType::FileUpload) {
                    $fileKey = 'file_'.$field->id;
                    if (isset($this->regFiles[$fileKey]) && $this->regFiles[$fileKey]) {
                        $path = $this->regFiles[$fileKey]->store('partners/registration', 'public');
                        PartnerRegistrationValue::updateOrCreate(
                            ['partner_id' => $partner->id, 'registration_field_id' => $field->id],
                            ['value' => [$path]],
                        );
                    }
                } elseif (array_key_exists($key, $this->regValues)) {
                    $value = $this->regValues[$key];
                    PartnerRegistrationValue::updateOrCreate(
                        ['partner_id' => $partner->id, 'registration_field_id' => $field->id],
                        ['value' => is_array($value) ? $value : [$value]],
                    );
                }
            }
        });

        // resubmitIfNeeded() flips Rejected/CorrectionRequested back to Resubmission before
        // checking auto-approve; for a first-time completion (still Pending) it's a no-op
        // and falls straight through to the same maybeAutoApprove() check as before.
        app(PartnerVerificationService::class)->resubmitIfNeeded($partner->fresh());

        $this->redirect('/partner');
    }

    private function validateCurrentStep(): void
    {
        match ($this->currentStep) {
            1 => $this->validateStep1(),
            2 => $this->validateStep2(),
            3 => $this->validateProfileStep(),
            4 => $this->validateAddressStep(),
            default => null,
        };
    }

    private function validateStep1(): void
    {
        $this->validate([
            'selectedCountryId' => ['required', 'integer', 'min:1'],
        ], [
            'selectedCountryId.min' => __('admin.please_select_a_country'),
        ]);
    }

    private function validateStep2(): void
    {
        $this->validate([
            'selectedPropertyTypeId' => ['required', 'integer', 'min:1'],
        ], [
            'selectedPropertyTypeId.min' => __('admin.please_select_a_property_type'),
        ]);
    }

    private function validateProfileStep(): void
    {
        $userId = Auth::id();

        $this->validate([
            'profileFirstName' => ['required', 'string', 'max:191'],
            'profileLastName' => ['required', 'string', 'max:191'],
            'profileEmail' => ['required', 'email', 'max:191', "unique:users,email,{$userId}"],
            // profilePhone defaults to '' (not null) when the partner has no phone yet,
            // so the regex must allow empty string alongside 7-15 digits, or 'nullable'
            // won't actually skip it (Laravel only skips on true null, not '').
            'profilePhone' => ['nullable', 'string', 'regex:/^([0-9]{7,15})?$/'],
            'profileDob' => ['nullable', 'date'],
            'profileGender' => ['nullable', 'string'],
            'profileAvatar' => ['nullable', 'image', 'max:5120', 'mimes:jpeg,jpg,png'],
        ], [
            'profileFirstName.required' => __('admin.first_name_required'),
            'profileLastName.required' => __('admin.last_name_required'),
            'profileEmail.required' => __('admin.email_address_required'),
            'profileEmail.email' => __('admin.valid_email_required'),
            'profileEmail.unique' => __('admin.email_already_registered'),
            'profilePhone.regex' => __('admin.validation_phone_format'),
            'profileAvatar.max' => __('admin.profile_avatar_max_size'),
        ]);
    }

    private function validateAddressStep(): void
    {
        $this->validate([
            'addressLine1' => ['required', 'string', 'max:500'],
            'addressZipCode' => ['required', 'string', 'max:20'],
            'addressCityId' => ['required', 'integer', 'min:1'],
            'addressStateId' => ['required', 'integer', 'min:1'],
        ], [
            'addressLine1.required' => 'Street address is required.',
            'addressZipCode.required' => 'ZIP / PIN code is required.',
            'addressCityId.min' => 'Please select a city.',
            'addressStateId.min' => 'Please select a state.',
        ]);
    }

    private function validateRegFieldsStep(): void
    {
        $rules = [];
        $messages = [];

        foreach ($this->loadRegistrationFields() as $field) {
            $isFile = $field->field_type === RegistrationFieldType::FileUpload;
            $key = $isFile ? 'regFiles.file_'.$field->id : 'regValues.field_'.$field->id;
            $hasExistingFile = $isFile && isset($this->existingRegFileLabels['file_'.$field->id]);

            if ($field->is_mandatory && ! $hasExistingFile) {
                $rules[$key][] = 'required';
                $messages[$key.'.required'] = $field->name.' '.__('admin.is_required');
            }

            // min_number/max_number on a NumberInput field mean digit COUNT
            // (e.g. 12 for Aadhar, 15 for GST), not a numeric value range —
            // matches how PartnerProfileManage validates the same fields via
            // TextInput::numeric()->minLength()->maxLength().
            if (! $isFile && $field->field_type === RegistrationFieldType::NumberInput && ($field->min_number || $field->max_number)) {
                $min = $field->min_number ?: 1;
                $max = $field->max_number ?: 32;

                $rules[$key][] = $min === $max ? "digits:{$min}" : "digits_between:{$min},{$max}";
                $messages[$key.'.digits'] = "{$field->name} must be exactly {$min} digits.";
                $messages[$key.'.digits_between'] = "{$field->name} must be between {$min} and {$max} digits.";
            }
        }

        if (! empty($rules)) {
            $this->validate($rules, $messages);
        }
    }

    /** @return Collection<int, RegistrationField> */
    private function loadRegistrationFields(): Collection
    {
        if ($this->selectedCountryId === 0) {
            return collect();
        }

        return RegistrationField::query()
            ->forScope(RegistrationFieldScope::Partner)
            ->forCountry($this->selectedCountryId)
            ->where('status', Status::Active)
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, Country> */
    public function getCountriesProperty(): Collection
    {
        return Country::query()
            ->with('refCountry')
            ->where('is_active', true)
            ->when(
                filled($this->countrySearch),
                fn ($q) => $q->where('name', 'like', '%'.$this->countrySearch.'%')
            )
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, PropertyType> */
    public function getPropertyTypesProperty(): Collection
    {
        return PropertyType::query()
            ->where('is_active', true)
            ->when(
                $this->selectedCountryId > 0,
                fn ($q) => $q->whereHas('countries', fn ($q) => $q->where('countries.id', $this->selectedCountryId)->where('country_property_types.is_enabled', true)),
                fn ($q) => $q->whereHas('countries'),
            )
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, RegistrationField> */
    public function getRegistrationFieldsProperty(): Collection
    {
        return $this->loadRegistrationFields();
    }

    /** @return Collection<int, RefState> */
    public function getStatesProperty(): Collection
    {
        if ($this->selectedCountryId === 0) {
            return collect();
        }

        $refCountryId = Country::find($this->selectedCountryId)?->ref_country_id;

        if (! $refCountryId) {
            return collect();
        }

        return RefState::query()
            ->where('country_id', $refCountryId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return Collection<int, RefCity> */
    public function getCitiesProperty(): Collection
    {
        if ($this->addressStateId === 0) {
            return collect();
        }

        return RefCity::query()
            ->where('state_id', $this->addressStateId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getTotalStepsProperty(): int
    {
        return $this->loadRegistrationFields()->isNotEmpty() ? 5 : 4;
    }

    public function render(): View
    {
        $authUser = Auth::user();

        return view('livewire.partner-setup-wizard', [
            'countries' => $this->countries,
            'propertyTypes' => $this->propertyTypes,
            'registrationFields' => $this->registrationFields,
            'states' => $this->states,
            'cities' => $this->cities,
            'totalSteps' => $this->totalSteps,
            'authUser' => $authUser,
            'isEmailLocked' => $authUser?->auth_provider === 'email',
            'mapProvider' => MapProvider::current(),
            'googleMapsApiKey' => Setting::get('google_maps_api_key'),
            'storageUrl' => fn (string $path): string => Storage::url($path),
        ]);
    }
}
