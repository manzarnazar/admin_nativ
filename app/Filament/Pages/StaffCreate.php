<?php

namespace App\Filament\Pages;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Models\Country;
use App\Models\Property;
use App\Models\User;
use App\Support\SystemMode;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class StaffCreate extends Page implements DeclaresTopbarControls
{
    use HasPagePermission, WithFileUploads;

    public static function topbarControls(): array
    {
        if (SystemMode::isMulti()) {
            return ['property' => false];
        }

        return [];
    }

    protected static ?string $slug = 'staff-management/create';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $permissionSlug = 'staff-management';

    protected string $view = 'filament.pages.staff-create';

    public ?int $record = null;

    public string $firstName = '';

    public string $lastName = '';

    public string $gender = '';

    public string $dateOfBirth = '';

    /** @var array<string, mixed>|null */
    public ?array $phoneData = [];

    public string $stateProvince = '';

    public string $zipCode = '';

    public string $address = '';

    public string $email = '';

    public string $password = '';

    public mixed $avatarPath = null;

    public mixed $documentImagePath = null;

    public string $assignedRole = '';

    public ?int $selectedBranchId = null;

    public function getTitle(): string|Htmlable
    {
        return $this->record
            ? __('admin.edit_staff_member')
            : __('admin.add_new_staff_member');
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        $recordId = request()->query('record');

        if ($recordId) {
            abort_unless(static::canEdit(), 403);
        } else {
            abort_unless(static::canCreate(), 403);
        }

        if ($recordId) {
            $user = User::query()->with('roles')->find($recordId);

            if ($user) {
                $this->record = $user->id;
                $this->firstName = $user->first_name ?? '';
                $this->lastName = $user->last_name ?? '';
                $this->gender = $user->gender?->value ?? '';
                $this->dateOfBirth = $user->date_of_birth?->format('Y-m-d') ?? '';
                $this->stateProvince = $user->state_province ?? '';
                $this->zipCode = $user->zip_code ?? '';
                $this->address = $user->address ?? '';
                $this->email = $user->email ?? '';
                $this->avatarPath = $user->avatar;
                $this->documentImagePath = $user->document_image;
                $this->assignedRole = $user->roles->first()?->name ?? '';
                $this->selectedBranchId = $user->branch_id;

                $this->phoneForm->fill([
                    'dial_code' => $this->dialCodeToSelectValue($user->dial_code),
                    'primary_phone' => $user->phone ?? '',
                    'secondary_phone' => $user->secondary_phone ?? '',
                ]);
            }
        } else {
            $countryPhoneCode = Auth::user()?->currentCountry?->phone_code;
            $dialCode = $countryPhoneCode ? '+'.ltrim($countryPhoneCode, '+') : null;

            $this->phoneForm->fill([
                'dial_code' => $this->dialCodeToSelectValue($dialCode),
            ]);
        }
    }

    private function dialCodeToSelectValue(?string $dialCode): ?string
    {
        if (! $dialCode) {
            return null;
        }

        $code = ltrim($dialCode, '+');
        $country = Country::where('phone_code', $code)->where('is_active', true)->orderBy('name')->first();

        return $country ? "+{$country->phone_code}_{$country->id}" : null;
    }

    public function getGenderOptions(): array
    {
        return collect(Gender::cases())
            ->mapWithKeys(fn (Gender $g) => [$g->value => $g->label()])
            ->toArray();
    }

    public function phoneForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 2 equal columns — matches the State/Zip grid below it, so the two
                // rows' edges line up rather than the phone row using a different split.
                Grid::make(2)
                    ->schema([
                        FusedGroup::make([
                            Select::make('dial_code')
                                ->hiddenLabel()
                                ->placeholder('')
                                ->options(
                                    Country::query()
                                        ->where('is_active', true)
                                        ->with('refCountry:id,emoji')
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn (Country $c): array => [
                                            "+{$c->phone_code}_{$c->id}" => "{$c->refCountry?->emoji} <span class='dial-country-code'>+{$c->phone_code}</span> <span class='dial-country-name'>{$c->name}</span>",
                                        ])
                                )
                                ->allowHtml()
                                ->searchable()
                                ->selectablePlaceholder(false)
                                ->required()
                                ->live()
                                ->dehydrateStateUsing(fn (?string $state): ?string => $state ? explode('_', $state, 2)[0] : null)
                                ->extraAttributes([
                                    'style' => '[&_.fi-dropdown-panel]:!max-w-none [&_.fi-dropdown-panel]:!w-[220px]',
                                    'class' => 'phone-dial-code',
                                ]),

                            TextInput::make('primary_phone')
                                ->hiddenLabel()
                                ->tel()
                                ->required()
                                ->maxLength(15)
                                ->rules(['regex:/^[0-9]{7,15}$/'])
                                ->validationMessages(['regex' => __('admin.validation_phone_format')])
                                ->placeholder(__('admin.enter_phone_number'))
                                ->prefix(fn (Get $get) => $get('dial_code') ? explode('_', $get('dial_code'))[0] : null)
                                ->inlinePrefix(true),
                        ])
                            ->label(__('admin.primary_phone'))
                            ->columns(2)
                            ->extraAttributes(['class' => 'phone-fused-group'])
                            ->columnSpan(1),

                        TextInput::make('secondary_phone')
                            ->label(__('admin.secondary_phone'))
                            ->tel()
                            ->nullable()
                            ->maxLength(15)
                            ->rules(['regex:/^[0-9]{7,15}$/'])
                            ->validationMessages(['regex' => __('admin.validation_phone_format')])
                            ->placeholder(__('admin.enter_phone_number'))
                            // Same dial code as the primary phone — shown as a static
                            // (non-editable) badge rather than a second dropdown, since
                            // there's only one dial_code column to share between both.
                            ->prefix(fn (Get $get) => $get('dial_code') ? explode('_', $get('dial_code'))[0] : null)
                            ->inlinePrefix(true)
                            ->columnSpan(1),
                    ]),
            ])
            ->statePath('phoneData');
    }

    public function getAssignedRoleDisplay(): string
    {
        if ($this->record) {
            $user = User::query()->with('roles')->find($this->record);

            return $user?->roles->first()?->name ?? __('admin.no_role_assigned');
        }

        return __('admin.no_role_assigned');
    }

    /** @return array<int, string> */
    public function getPropertyOptions(): array
    {
        $countryId = auth()->user()?->current_country_id;

        if (! $countryId) {
            return [];
        }

        return Property::query()
            ->where('country_id', $countryId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function getAvatarUrl(): string
    {
        if (is_string($this->avatarPath) && str_starts_with($this->avatarPath, 'avatars/')) {
            return asset('storage/'.$this->avatarPath);
        }

        return asset('avatars/defaultUser.svg');
    }

    public function updatedAvatarPath(): void
    {
        $this->validate(['avatarPath' => 'nullable|image|max:5120|mimes:png,jpg,jpeg,svg']);

        if ($this->avatarPath instanceof TemporaryUploadedFile) {
            $this->avatarPath = $this->avatarPath->store('avatars', 'public');
        }
    }

    public function updatedDocumentImagePath(): void
    {
        $this->validate(['documentImagePath' => 'nullable|image|max:5120|mimes:png,jpg,jpeg']);

        if ($this->documentImagePath instanceof TemporaryUploadedFile) {
            $this->documentImagePath = $this->documentImagePath->store('documents', 'public');
        }
    }

    public function saveStaff(): void
    {
        // Validated first so a bad phone/dial-code fails before touching the rest —
        // the phone fields live in their own Filament schema, not the plain $rules below.
        $phoneState = $this->phoneForm->getState();

        $rules = [
            'firstName' => ['required', 'string', 'max:100', 'regex:/^[\pL\s\-\']+$/u'],
            'lastName' => ['required', 'string', 'max:100', 'regex:/^[\pL\s\-\']+$/u'],
            'gender' => ['nullable', Rule::in(array_column(Gender::cases(), 'value'))],
            'dateOfBirth' => ['nullable', 'date', 'before:-18 years'],
            'stateProvince' => ['nullable', 'string', 'max:100'],
            'zipCode' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\s\-]+$/'],
            'address' => ['required', 'string', 'max:500'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($this->record),
            ],
            'password' => $this->record ? 'nullable|min:8' : 'required|min:8',
            'documentImagePath' => $this->record ? ['nullable', 'string'] : ['required', 'string'],
            'selectedBranchId' => SystemMode::isMulti()
                ? ['nullable', 'integer', Rule::exists('properties', 'id')]
                : ['required', 'integer', Rule::exists('properties', 'id')],
        ];

        $this->validate($rules, [
            'firstName.regex' => __('admin.validation_name_format'),
            'lastName.regex' => __('admin.validation_name_format'),
            'dateOfBirth.before' => __('admin.validation_min_age'),
            'zipCode.regex' => __('admin.validation_zip_format'),
            'password.min' => __('admin.validation_password_min'),
            'documentImagePath.required' => __('admin.validation_document_image_required'),
            'selectedBranchId.required' => __('admin.validation_property_required'),
        ]);

        $fullName = trim($this->firstName.' '.$this->lastName);

        $data = [
            'name' => $fullName,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'gender' => $this->gender ?: null,
            'date_of_birth' => $this->dateOfBirth ?: null,
            'dial_code' => $phoneState['dial_code'],
            'phone' => $phoneState['primary_phone'],
            'secondary_phone' => $phoneState['secondary_phone'] ?: null,
            'state_province' => $this->stateProvince ?: null,
            'zip_code' => $this->zipCode ?: null,
            'address' => $this->address,
            'email' => $this->email,
            'role' => UserRole::Staff,
            'status' => UserStatus::Active,
            'auth_provider' => 'admin',
        ];

        if (SystemMode::isSingle()) {
            $data['branch_id'] = $this->selectedBranchId;
            $data['current_branch_id'] = $this->selectedBranchId;
        }

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if (is_string($this->avatarPath) && $this->avatarPath) {
            $data['avatar'] = $this->avatarPath;
        }

        if (is_string($this->documentImagePath) && $this->documentImagePath) {
            $data['document_image'] = $this->documentImagePath;
        }

        if ($this->record) {
            $user = User::query()->findOrFail($this->record);
            $user->update($data);
        } else {
            /** @var User $admin */
            $admin = Auth::user();

            if (! $admin->current_country_id) {
                Notification::make()
                    ->title(__('admin.staff_create_select_property_first'))
                    ->danger()
                    ->send();

                return;
            }

            $data['country_id'] = $admin->current_country_id;
            $data['current_country_id'] = $admin->current_country_id;

            $trashed = User::withTrashed()->where('email', $this->email)->first();
            if ($trashed?->trashed()) {
                $trashed->restore();
                $trashed->update($data);
                $user = $trashed;
            } else {
                $user = User::create($data);
            }
        }

        Notification::make()
            ->title($this->record
                ? __('admin.staff_updated_successfully')
                : __('admin.staff_created_successfully'))
            ->success()
            ->send();

        $this->redirect(StaffManage::getUrl());
    }

    public function cancel(): void
    {
        $this->redirect(StaffManage::getUrl());
    }
}
