<?php

namespace App\Filament\Partner\Pages;

use App\Enums\RegistrationFieldScope;
use App\Enums\RegistrationFieldType;
use App\Enums\Status;
use App\Enums\UserRole;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Partner\Concerns\HasPartnerDemoGuard;
use App\Models\Partner;
use App\Models\PartnerRegistrationValue;
use App\Models\RefCity;
use App\Models\RefCountry;
use App\Models\RefState;
use App\Models\RegistrationField;
use App\Models\User;
use App\Services\OtpService;
use App\Services\PartnerVerificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\RawJs;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Password;

class PartnerProfileManage extends Page implements DeclaresTopbarControls
{
    use HasPartnerDemoGuard;

    protected static ?string $slug = 'partner-profile';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.partner-profile';

    /** @var array<string, mixed>|null */
    public ?array $passwordData = [];

    /** @var array<string, mixed>|null */
    public ?array $registrationData = [];

    public function mount(): void
    {
        $this->passwordForm->fill();
        $this->fillRegistrationForm();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && $user->role === UserRole::Partner;
    }

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.my_profile');
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.my_profile');
    }

    public function getSubheading(): ?string
    {
        return __('admin.subheading_profile');
    }

    public function editPersonalInfoAction(): Action
    {
        /** @var User $user */
        $user = auth()->user();

        return Action::make('editPersonalInfo')
            ->label(__('admin.edit_details'))
            ->modalHeading(__('admin.edit_profile'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('xl')
            ->modalSubmitActionLabel(__('admin.save_details'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->before($this->enforceEditPermission())
            ->fillForm(function (): array {
                /** @var User $user */
                $user = auth()->user();
                $isUrlAvatar = $user->avatar && filter_var($user->avatar, FILTER_VALIDATE_URL);

                return [
                    'avatar' => $isUrlAvatar ? null : $user->avatar,
                    'name' => $user->name,
                    'dial_code' => $this->dialCodeToSelectValue($user->dial_code),
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'original_email' => $user->email,
                    'codes_sent' => false,
                ];
            })
            ->schema([
                TextInput::make('original_email')->hidden()->dehydrated(),
                TextInput::make('codes_sent')->hidden()->dehydrated(),

                FileUpload::make('avatar')
                    ->label(__('admin.profile_photo'))
                    ->image()
                    ->disk('public')
                    ->directory('avatars')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/png', 'image/svg+xml', 'image/jpeg'])
                    ->openable()
                    ->extraAttributes(['class' => 'avatar-square-upload'])
                    ->helperText(__('admin.maximum_size_5mb_supported_files_pngsvgjpg')),

                TextInput::make('name')
                    ->label(__('admin.name'))
                    ->required()
                    ->maxLength(255),

                Grid::make(12)
                    ->schema([
                        Select::make('dial_code')
                            ->label(__('admin.dial_code'))
                            ->options(RefCountry::query()
                                ->where('flag', true)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (RefCountry $c): array => [
                                    "+{$c->phonecode}_{$c->id}" => "{$c->emoji} +{$c->phonecode} <span class='dial-country-name'>{$c->name}</span>",
                                ]))
                            ->allowHtml()
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? explode('_', $state, 2)[0] : null)
                            ->searchable()
                            ->required()
                            ->columnSpan(4),
                        TextInput::make('phone')
                            ->label(__('admin.phone'))
                            ->tel()
                            ->required()
                            ->maxLength(20)
                            ->mask(RawJs::make('$input.replace(/[^0-9]/g, "").slice(0, 15)'))
                            ->rules(['regex:/^[0-9]{7,15}$/'])
                            ->validationMessages(['regex' => __('admin.validation_phone_format')])
                            ->placeholder(__('admin.eg_1_555_1234567'))
                            ->columnSpan(8),
                    ]),

                TextInput::make('email')
                    ->label(__('admin.email'))
                    ->required()
                    ->email()
                    ->maxLength(255)
                    ->unique('users', 'email', ignorable: $user)
                    ->helperText(__('admin.email_change_requires_verification'))
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $set('otp_current', null);
                        $set('otp_new', null);

                        $sent = $this->sendEmailChangeCodes((string) $state);
                        $set('codes_sent', $sent);

                        if ($sent) {
                            Notification::make()
                                ->title(__('admin.verification_codes_sent'))
                                ->success()
                                ->send();
                        }
                    }),

                Section::make(__('admin.verify_email_change'))
                    ->description(__('admin.verify_email_change_description'))
                    ->icon('heroicon-o-shield-check')
                    ->visible(fn (Get $get): bool => filled($get('email')) && $get('email') !== $get('original_email'))
                    ->schema([
                        Actions::make([
                            Action::make('sendCodes')
                                ->label(fn (Get $get): string => $get('codes_sent')
                                    ? __('admin.resend_verification_codes')
                                    : __('admin.send_verification_codes'))
                                ->icon('heroicon-o-envelope')
                                ->button()
                                ->action(function (Get $get, Set $set): void {
                                    /** @var User $user */
                                    $user = auth()->user();
                                    $newEmail = trim((string) $get('email'));

                                    if (! filter_var($newEmail, FILTER_VALIDATE_EMAIL) || $newEmail === $user->email) {
                                        Notification::make()
                                            ->title(__('admin.enter_valid_new_email_first'))
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    if (User::query()->where('email', $newEmail)->whereKeyNot($user->id)->exists()) {
                                        Notification::make()
                                            ->title(__('admin.new_email_already_taken'))
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    $wait = app(OtpService::class)->secondsUntilResend($user->email, 'email_change');

                                    if ($wait > 0) {
                                        Notification::make()
                                            ->title(__('admin.please_wait_to_resend', ['seconds' => $wait]))
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    if ($this->sendEmailChangeCodes($newEmail)) {
                                        $set('codes_sent', true);

                                        Notification::make()
                                            ->title(__('admin.verification_codes_sent'))
                                            ->success()
                                            ->send();
                                    }
                                }),
                        ]),

                        TextInput::make('otp_current')
                            ->label(__('admin.code_sent_to_current_email'))
                            ->helperText(fn (): string => (string) auth()->user()->email)
                            ->placeholder('______')
                            ->length(6)
                            ->required(),

                        TextInput::make('otp_new')
                            ->label(__('admin.code_sent_to_new_email'))
                            ->helperText(fn (Get $get): ?string => $get('email'))
                            ->placeholder('______')
                            ->length(6)
                            ->required(),
                    ]),
            ])
            ->action(function (array $data): void {
                /** @var User $user */
                $user = auth()->user();

                $oldEmail = $user->email;
                $emailChanged = $data['email'] !== $oldEmail;

                if ($emailChanged) {
                    $otpService = app(OtpService::class);

                    $currentValid = $otpService->verify($oldEmail, (string) ($data['otp_current'] ?? ''), 'email_change');
                    $newValid = $otpService->verify($data['email'], (string) ($data['otp_new'] ?? ''), 'email_change');

                    if (! $currentValid || ! $newValid) {
                        Notification::make()
                            ->title(__('admin.verification_code_invalid'))
                            ->danger()
                            ->send();

                        return;
                    }
                }

                $newAvatar = $data['avatar'] ?? null;

                if ($newAvatar === null && $user->avatar && filter_var($user->avatar, FILTER_VALIDATE_URL)) {
                    $newAvatar = $user->avatar;
                }

                $oldAvatarPath = ($user->avatar && ! filter_var($user->avatar, FILTER_VALIDATE_URL))
                    ? $user->avatar
                    : '—';

                $formatPhone = fn (?string $code, ?string $phone): string => trim(($code ? '+'.ltrim($code, '+').' ' : '').($phone ?? ''));

                $oldProps = [
                    'Name' => $user->name,
                    'Phone' => $formatPhone($user->dial_code, $user->phone),
                    'Email' => $oldEmail,
                    'Avatar' => $oldAvatarPath,
                ];

                $user->update([
                    'name' => $data['name'],
                    'dial_code' => $data['dial_code'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'avatar' => $newAvatar,
                ]);

                /** @var Partner|null $partner */
                $partner = $user->partner;

                if ($partner) {
                    $newAvatarPath = ($newAvatar && ! filter_var($newAvatar, FILTER_VALIDATE_URL))
                        ? $newAvatar
                        : '—';

                    $newProps = [
                        'Name' => $data['name'],
                        'Phone' => $formatPhone($data['dial_code'], $data['phone']),
                        'Email' => $data['email'],
                        'Avatar' => $newAvatarPath,
                    ];

                    $changedOld = [];
                    $changedNew = [];

                    foreach ($oldProps as $k => $oldVal) {
                        if ($oldVal !== $newProps[$k]) {
                            $changedOld[$k] = $oldVal;
                            $changedNew[$k] = $newProps[$k];
                        }
                    }

                    $activityBuilder = activity()
                        ->performedOn($partner)
                        ->causedBy($user)
                        ->event('profile_updated');

                    if (! empty($changedNew)) {
                        $properties = ['old' => $changedOld, 'attributes' => $changedNew];
                        if (array_key_exists('Avatar', $changedNew)) {
                            $properties['image_fields'] = ['Avatar'];
                        }
                        $activityBuilder->withProperties($properties);
                    }

                    $activityBuilder->log('Partner updated personal info');

                    app(PartnerVerificationService::class)->resubmitIfNeeded($partner);
                }

                Notification::make()
                    ->title(__('admin.profile_updated'))
                    ->success()
                    ->send();
            });
    }

    public function editAddressAction(): Action
    {
        return Action::make('editAddress')
            ->label(__('admin.edit_address'))
            ->modalHeading(__('admin.edit_address'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('xl')
            ->modalSubmitActionLabel(__('admin.save_details'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->before($this->enforceEditPermission())
            ->fillForm(function (): array {
                /** @var Partner|null $partner */
                $partner = auth()->user()?->partner;

                return [
                    'address' => $partner?->address,
                    'ref_country_id' => $partner?->ref_country_id,
                    'ref_state_id' => $partner?->ref_state_id,
                    'ref_city_id' => $partner?->ref_city_id,
                    'zip_code' => $partner?->zip_code,
                ];
            })
            ->schema([
                TextInput::make('address')
                    ->label(__('admin.street_address'))
                    ->maxLength(500),

                Select::make('ref_country_id')
                    ->label(__('admin.country'))
                    ->options(fn () => RefCountry::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('ref_state_id', null);
                        $set('ref_city_id', null);
                    }),

                Select::make('ref_state_id')
                    ->label(__('admin.state'))
                    ->options(fn (Get $get) => $get('ref_country_id')
                        ? RefState::query()->where('country_id', $get('ref_country_id'))->orderBy('name')->pluck('name', 'id')
                        : [])
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('ref_city_id', null))
                    ->disabled(fn (Get $get): bool => ! $get('ref_country_id')),

                Select::make('ref_city_id')
                    ->label(__('admin.city'))
                    ->options(fn (Get $get) => $get('ref_state_id')
                        ? RefCity::query()->where('state_id', $get('ref_state_id'))->orderBy('name')->pluck('name', 'id')
                        : [])
                    ->searchable()
                    ->disabled(fn (Get $get): bool => ! $get('ref_state_id')),

                TextInput::make('zip_code')
                    ->label(__('admin.zip_code'))
                    ->maxLength(20),
            ])
            ->action(function (array $data): void {
                /** @var Partner $partner */
                $partner = auth()->user()->partner;

                $oldProps = [
                    'Address' => $partner->address ?? '—',
                    'Country' => $partner->country ?? '—',
                    'State' => $partner->state_province ?? '—',
                    'City' => $partner->city ?? '—',
                    'Zip Code' => $partner->zip_code ?? '—',
                ];

                $newCountry = RefCountry::find($data['ref_country_id'] ?? null)?->name;
                $newState = RefState::find($data['ref_state_id'] ?? null)?->name;
                $newCity = RefCity::find($data['ref_city_id'] ?? null)?->name;

                $partner->update([
                    'address' => $data['address'] ?? null,
                    'ref_country_id' => $data['ref_country_id'] ?? null,
                    'ref_state_id' => $data['ref_state_id'] ?? null,
                    'ref_city_id' => $data['ref_city_id'] ?? null,
                    'zip_code' => $data['zip_code'] ?? null,
                    'country' => $newCountry,
                    'state_province' => $newState,
                    'city' => $newCity,
                ]);

                $newProps = [
                    'Address' => $data['address'] ?? '—',
                    'Country' => $newCountry ?? '—',
                    'State' => $newState ?? '—',
                    'City' => $newCity ?? '—',
                    'Zip Code' => $data['zip_code'] ?? '—',
                ];

                $changedOld = [];
                $changedNew = [];

                foreach ($oldProps as $k => $oldVal) {
                    if ($oldVal !== ($newProps[$k] ?? '—')) {
                        $changedOld[$k] = $oldVal;
                        $changedNew[$k] = $newProps[$k] ?? '—';
                    }
                }

                $activityBuilder = activity()
                    ->performedOn($partner)
                    ->causedBy(auth()->user())
                    ->event('address_updated');

                if (! empty($changedNew)) {
                    $activityBuilder->withProperties(['old' => $changedOld, 'attributes' => $changedNew]);
                }

                $activityBuilder->log('Partner updated their address');

                app(PartnerVerificationService::class)->resubmitIfNeeded($partner);

                Notification::make()
                    ->title(__('admin.address_updated'))
                    ->success()
                    ->send();
            });
    }

    public function passwordForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Form::make([
                    Grid::make(3)->schema([
                        TextInput::make('current_password')
                            ->label(__('admin.current_password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword()
                            ->placeholder('******'),
                        TextInput::make('new_password')
                            ->label(__('admin.new_password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::defaults())
                            ->placeholder('******'),
                        TextInput::make('new_password_confirmation')
                            ->label(__('admin.confirm_password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->same('new_password')
                            ->placeholder('******'),
                    ]),
                ])->livewireSubmitHandler('updatePassword'),
            ])
            ->statePath('passwordData');
    }

    public function updatePassword(): void
    {
        if ($this->blockEditIfDemoPartner()) {
            return;
        }

        $data = $this->passwordForm->getState();

        /** @var User $user */
        $user = auth()->user();

        $user->update([
            'password' => Hash::make($data['new_password']),
        ]);

        $this->passwordForm->fill();

        Notification::make()
            ->title(__('admin.password_updated'))
            ->success()
            ->send();
    }

    public function registrationForm(Schema $schema): Schema
    {
        $fields = $this->getPartnerRegistrationFields();

        return $schema
            ->statePath('registrationData')
            ->schema([
                Form::make(
                    $fields->isEmpty() ? [] : $this->buildRegistrationComponents($fields)
                )->livewireSubmitHandler('updateRegistrationDetails'),
            ]);
    }

    public function updateRegistrationDetails(): void
    {
        if ($this->blockEditIfDemoPartner()) {
            return;
        }

        $data = $this->registrationForm->getState();

        /** @var Partner $partner */
        $partner = auth()->user()->partner;

        $fields = $this->getPartnerRegistrationFields();

        // Snapshot old values before saving
        $existingValues = PartnerRegistrationValue::query()
            ->where('partner_id', $partner->id)
            ->pluck('value', 'registration_field_id');

        foreach ($fields as $field) {
            $key = 'reg_field_'.$field->id;

            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            PartnerRegistrationValue::updateOrCreate(
                ['partner_id' => $partner->id, 'registration_field_id' => $field->id],
                ['value' => is_array($value) ? $value : [$value]],
            );
        }

        app(PartnerVerificationService::class)->resubmitIfNeeded($partner);

        // Build field-level diff for the audit log
        $oldProps = [];
        $newProps = [];
        $fileFields = [];

        // Large numeric identifiers (Aadhar, GST, etc.) come back from JSON storage
        // as PHP floats, causing sprintf default to scientific notation. Force integer format.
        $formatScalar = fn (mixed $v): string => is_float($v) ? sprintf('%.0f', $v) : (string) $v;

        foreach ($fields as $field) {
            $key = 'reg_field_'.$field->id;

            if (! array_key_exists($key, $data)) {
                continue;
            }

            $oldRaw = $existingValues->get($field->id);
            $newRaw = $data[$key];
            $newNormalized = is_array($newRaw) ? $newRaw : [$newRaw];

            if ($oldRaw == $newNormalized) {
                continue;
            }

            $label = ucwords($field->name);

            if ($field->field_type === RegistrationFieldType::FileUpload) {
                // Store actual file paths so admin can click through to view the document.
                // Paths are pipe-separated to avoid colliding with commas in filenames.
                $fileFields[] = $label;
                $oldPaths = array_values(array_filter((array) $oldRaw));
                $newPaths = array_values(array_filter((array) $newRaw));
                $oldProps[$label] = $oldPaths ? implode('|', $oldPaths) : '—';
                $newProps[$label] = $newPaths ? implode('|', $newPaths) : '—';
            } else {
                $oldProps[$label] = implode(', ', array_map($formatScalar, array_filter((array) $oldRaw))) ?: '—';
                $newProps[$label] = implode(', ', array_map($formatScalar, array_filter((array) $newNormalized))) ?: '—';
            }
        }

        $activityBuilder = activity()
            ->performedOn($partner)
            ->causedBy(auth()->user())
            ->event('registration_details_updated');

        if (! empty($newProps)) {
            $props = ['old' => $oldProps, 'attributes' => $newProps];

            if (! empty($fileFields)) {
                $props['file_fields'] = $fileFields;
            }

            $activityBuilder->withProperties($props);
        }

        $activityBuilder->log('Partner updated their registration details');

        Notification::make()
            ->title(__('admin.registration_details_updated'))
            ->success()
            ->send();
    }

    private function fillRegistrationForm(): void
    {
        /** @var Partner|null $partner */
        $partner = auth()->user()?->partner;

        if (! $partner) {
            $this->registrationForm->fill();

            return;
        }

        $fields = $this->getPartnerRegistrationFields();

        if ($fields->isEmpty()) {
            $this->registrationForm->fill();

            return;
        }

        $existingValues = PartnerRegistrationValue::query()
            ->where('partner_id', $partner->id)
            ->pluck('value', 'registration_field_id');

        $formData = [];

        foreach ($fields as $field) {
            $key = 'reg_field_'.$field->id;
            $saved = $existingValues->get($field->id);

            if ($saved !== null) {
                $formData[$key] = $field->field_type === RegistrationFieldType::Checkboxes
                    ? (array) $saved
                    : (is_array($saved) ? ($saved[0] ?? null) : $saved);
            }
        }

        $this->registrationForm->fill($formData);
    }

    /** @return Collection<int, RegistrationField> */
    private function getPartnerRegistrationFields(): Collection
    {
        /** @var Partner|null $partner */
        $partner = auth()->user()?->partner;

        if (! $partner) {
            return collect();
        }

        $countryId = session('partner_current_country_id')
            ?? $partner->countries()->value('countries.id');

        if (! $countryId) {
            return collect();
        }

        $isPartnerCountry = $partner->countries()
            ->where('countries.id', $countryId)
            ->exists();

        if (! $isPartnerCountry) {
            return collect();
        }

        return RegistrationField::query()
            ->forScope(RegistrationFieldScope::Partner)
            ->forCountry($countryId)
            ->where('status', Status::Active)
            ->orderBy('id')
            ->get();
    }

    /** @return array<int, mixed> */
    private function buildRegistrationComponents(Collection $fields): array
    {
        $standardFields = [];
        $fileUploadFields = [];

        foreach ($fields as $field) {
            $fieldName = 'reg_field_'.$field->id;

            $component = match ($field->field_type) {
                RegistrationFieldType::NumberInput => TextInput::make($fieldName)
                    ->label(ucwords($field->name))
                    ->numeric()
                    ->minLength($field->min_number)
                    ->maxLength($field->max_number)
                    ->helperText($this->registrationFieldHelperText($field)),

                RegistrationFieldType::TextField => TextInput::make($fieldName)
                    ->label(ucwords($field->name))
                    ->maxLength($field->max_length)
                    ->helperText($this->registrationFieldHelperText($field)),

                RegistrationFieldType::TextArea => Textarea::make($fieldName)
                    ->label(ucwords($field->name))
                    ->maxLength($field->max_length)
                    ->rows(4)
                    ->columnSpanFull()
                    ->helperText($this->registrationFieldHelperText($field)),

                RegistrationFieldType::Checkboxes => CheckboxList::make($fieldName)
                    ->label(ucwords($field->name))
                    ->options(
                        collect($field->options)
                            ->mapWithKeys(fn (string $opt) => [$opt => $opt])
                            ->toArray()
                    )
                    ->columns(2),

                RegistrationFieldType::Date => DatePicker::make($fieldName)
                    ->label(ucwords($field->name)),

                RegistrationFieldType::Dropdown => Select::make($fieldName)
                    ->label(ucwords($field->name))
                    ->options(
                        collect($field->options)
                            ->mapWithKeys(fn (string $opt) => [$opt => $opt])
                            ->toArray()
                    )
                    ->searchable(),

                RegistrationFieldType::FileUpload => FileUpload::make($fieldName)
                    ->label(ucwords($field->name))
                    ->panelLayout('integrated')
                    ->maxSize($field->max_file_size ? $field->max_file_size * 1024 : 2048)
                    ->directory('partners/registration')
                    ->disk('public')
                    ->openable()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                    ->helperText(__('admin.maximum_size_supported_files', [
                        'size' => ($field->max_file_size ?: 5).'MB',
                        'types' => 'JPG/PNG/PDF',
                    ])),
            };

            if ($field->is_mandatory) {
                $component = $component->required();
            }

            if ($field->field_type === RegistrationFieldType::FileUpload) {
                $fileUploadFields[] = $component;
            } else {
                $standardFields[] = $component;
            }
        }

        $schema = [];

        if (count($standardFields) > 0) {
            $schema[] = Grid::make(4)->schema($standardFields);
        }

        if (count($fileUploadFields) > 0) {
            $schema[] = TextEntry::make('documents_heading')
                ->hiddenLabel()
                ->state(new HtmlString('<div class="mb-2 border-b border-gray-200 pb-2 text-lg font-semibold text-gray-950 dark:border-gray-700 dark:text-white">Required Documents</div>'))
                ->columnSpanFull();

            $schema[] = Grid::make(4)->schema($fileUploadFields);
        }

        return $schema;
    }

    /**
     * min_number/max_number on a NumberInput field mean digit COUNT (e.g. 12
     * for Aadhar, 15 for GST), not a numeric value range.
     */
    private function registrationFieldHelperText(RegistrationField $field): ?string
    {
        return match ($field->field_type) {
            RegistrationFieldType::TextField, RegistrationFieldType::TextArea => $field->max_length
                ? "Max {$field->max_length} characters."
                : null,
            RegistrationFieldType::NumberInput => match (true) {
                (bool) $field->min_number && (bool) $field->max_number && $field->min_number === $field->max_number => "Must be exactly {$field->min_number} digits.",
                (bool) $field->min_number && (bool) $field->max_number => "Must be between {$field->min_number} and {$field->max_number} digits.",
                (bool) $field->min_number => "Minimum {$field->min_number} digits.",
                (bool) $field->max_number => "Maximum {$field->max_number} digits.",
                default => null,
            },
            default => null,
        };
    }

    protected function getViewData(): array
    {
        return [
            'user' => auth()->user(),
            'registrationCountryName' => $this->getRegistrationCountryName(),
        ];
    }

    private function getRegistrationCountryName(): ?string
    {
        /** @var Partner|null $partner */
        $partner = auth()->user()?->partner;

        if (! $partner) {
            return null;
        }

        $countryId = session('partner_current_country_id')
            ?? $partner->countries()->value('countries.id');

        if (! $countryId) {
            return null;
        }

        return $partner->countries()
            ->where('countries.id', $countryId)
            ->value('countries.name');
    }

    private function dialCodeToSelectValue(?string $dialCode): ?string
    {
        if (! $dialCode) {
            return null;
        }

        $code = ltrim($dialCode, '+');
        $country = RefCountry::where('phonecode', $code)->where('flag', true)->orderBy('name')->first();

        return $country ? "+{$country->phonecode}_{$country->id}" : null;
    }

    private function sendEmailChangeCodes(string $newEmail): bool
    {
        /** @var User $user */
        $user = auth()->user();
        $newEmail = trim($newEmail);

        if (! filter_var($newEmail, FILTER_VALIDATE_EMAIL) || $newEmail === $user->email) {
            return false;
        }

        if (User::query()->where('email', $newEmail)->whereKeyNot($user->id)->exists()) {
            return false;
        }

        $otpService = app(OtpService::class);
        $otpService->send($user->email, 'email_change');
        $otpService->send($newEmail, 'email_change');

        return true;
    }
}
