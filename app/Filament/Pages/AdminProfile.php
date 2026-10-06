<?php

namespace App\Filament\Pages;

use App\Filament\Contracts\DeclaresTopbarControls;
use App\Models\RefCountry;
use App\Models\User;
use App\Services\OtpService;
use App\Support\DemoMode;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AdminProfile extends Page implements DeclaresTopbarControls
{
    protected static string $routePath = '/admin-profile';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $title = 'admin.my_profile';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.my_profile');
    }

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.admin-profile';

    /** @var array<string, mixed>|null */
    public ?array $passwordData = [];

    public function mount(): void
    {
        $this->passwordForm->fill();
    }

    public function getSubheading(): ?string
    {
        return __('admin.subheading_profile');
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.my_profile');
    }

    public function editProfileAction(): Action
    {
        /** @var User $user */
        $user = auth()->user();

        return Action::make('editProfile')
            ->label(__('admin.edit_details'))
            ->modalHeading(__('admin.edit_profile'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('xl')
            ->modalSubmitActionLabel(__('admin.save_details'))
            ->modalSubmitAction(function (Action $action) use ($user): Action {
                $isDemo = DemoMode::isActive($user);

                return $action->disabled($isDemo)->extraAttributes($isDemo ? ['disabled' => true] : []);
            })
            ->modalFooterActionsAlignment(Alignment::End)
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

                if ($user->isDemoAccount()) {
                    Notification::make()
                        ->title(__('admin.demo_account_action_not_allowed'))
                        ->danger()
                        ->send();

                    return;
                }

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

                // Preserve URL-based avatars (social login) when no new file is uploaded
                if ($newAvatar === null && $user->avatar && filter_var($user->avatar, FILTER_VALIDATE_URL)) {
                    $newAvatar = $user->avatar;
                }

                // Delete old disk avatar when explicitly cleared or replaced
                if ($user->avatar && ! filter_var($user->avatar, FILTER_VALIDATE_URL) && $newAvatar !== $user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }

                $user->update([
                    'name' => $data['name'],
                    'dial_code' => $data['dial_code'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'avatar' => $newAvatar,
                ]);

                Notification::make()
                    ->title(__('admin.profile_updated'))
                    ->success()
                    ->send();
            });
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

    /**
     * Send email-change verification codes to both the current and the proposed new email.
     * Returns false when the new email is invalid, unchanged, or already taken.
     */
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
        /** @var User $user */
        $user = auth()->user();

        if (DemoMode::isActive($user)) {
            activity('security')
                ->causedBy($user)
                ->event('demo_password_change_blocked')
                ->withProperties([
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ])
                ->log('Demo account password change attempt blocked.');

            Notification::make()
                ->title(__('admin.demo_account_action_not_allowed'))
                ->danger()
                ->send();

            return;
        }

        $data = $this->passwordForm->getState();

        $user->update([
            'password' => Hash::make($data['new_password']),
        ]);

        activity('security')
            ->causedBy($user)
            ->event('password_changed')
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Admin panel password changed successfully.');

        $this->passwordForm->fill();

        Notification::make()
            ->title(__('admin.password_updated'))
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();

        return [
            'user' => $user,
        ];
    }
}
