<?php

namespace App\Filament\Partner\Pages\Auth;

use App\Enums\LoginFailureReason;
use App\Enums\LoginStatus;
use App\Enums\PartnerVerificationStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Auth\Login as BaseLogin;
use App\Models\Country;
use App\Models\User;
use App\Services\LoginLogService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public string $loginMode = 'email';

    public function getHeading(): string|Htmlable|null
    {
        return config('app.name').' Partner';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        return __('admin.sign_in_to_access_the_partner_panel');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.partner.auth.login-mode-toggle')
                    ->viewData(['loginMode' => $this->loginMode]),

                $this->getEmailFormComponent()
                    ->visible(fn (): bool => $this->loginMode === 'email')
                    ->required(fn (): bool => $this->loginMode === 'email'),

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
                        ->default(function () {
                            $defaultCountry = Country::query()
                                ->where('is_active', true)
                                ->where('is_default', true)
                                ->first()
                                ?? Country::query()
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->first();

                            return $defaultCountry ? "+{$defaultCountry->phone_code}_{$defaultCountry->id}" : null;
                        })
                        ->dehydrateStateUsing(fn (?string $state): ?string => $state ? explode('_', $state, 2)[0] : null)
                        ->required(fn (): bool => $this->loginMode === 'phone')
                        ->live()
                        ->extraAttributes([
                            'style' => '[&_.fi-dropdown-panel]:!max-w-none [&_.fi-dropdown-panel]:!w-[220px]',
                            'class' => 'phone-dial-code',
                        ]),

                    TextInput::make('phone')
                        ->hiddenLabel()
                        ->tel()
                        ->maxLength(20)
                        ->placeholder(__('admin.enter_phone_number'))
                        ->prefix(fn (Get $get) => $get('dial_code') ? explode('_', $get('dial_code'))[0] : null)
                        ->inlinePrefix(true)
                        ->required(fn (): bool => $this->loginMode === 'phone'),
                ])
                    ->label(__('admin.phone_number'))
                    ->columns(2)
                    ->extraAttributes(['class' => 'phone-fused-group'])
                    ->visible(fn (): bool => $this->loginMode === 'phone'),

                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    public function authenticate(): ?LoginResponse
    {
        if ($this->loginMode === 'phone') {
            return $this->authenticateWithPhone();
        }

        $response = parent::authenticate();

        if ($response) {
            /** @var User|null $user */
            $user = Filament::auth()->user();

            if ($user?->role === UserRole::Partner) {
                $status = $user->partner?->verification_status;

                if ($status === PartnerVerificationStatus::Suspended) {
                    Filament::auth()->logout();

                    // Auth::attempt() already succeeded and got logged as a Success login — this
                    // rejection only happens afterward, so correct that row rather than leaving a
                    // blocked sign-in showing as successful in the Login Report.
                    app(LoginLogService::class)->correctToFailed($user, LoginFailureReason::AccountInactive);

                    throw ValidationException::withMessages([
                        'data.email' => __('admin.partner_account_suspended'),
                    ]);
                }
            }
        }

        return $response;
    }

    private function authenticateWithPhone(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        $phone = $data['phone'] ?? null;
        $dialCode = $data['dial_code'] ?? null;

        $query = User::query()->where('phone', $phone);

        if (filled($dialCode)) {
            $query->where('dial_code', $dialCode);
        }

        $user = $query->first();

        // This flow calls Filament::auth()->login() directly rather than Auth::attempt(), so
        // none of it goes through Laravel's Login/Failed events — every branch below has to log
        // itself explicitly, unlike the email/password path above.
        $identifier = trim(($dialCode ?? '').$phone);

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            app(LoginLogService::class)->record($user, $identifier, LoginStatus::Failed, reason: LoginFailureReason::InvalidCredentials);

            throw ValidationException::withMessages([
                'data.phone' => __('filament-panels::auth/pages/login.messages.failed'),
            ]);
        }

        if (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            app(LoginLogService::class)->record($user, $identifier, LoginStatus::Failed, reason: LoginFailureReason::RoleMismatch);

            throw ValidationException::withMessages([
                'data.phone' => __('filament-panels::auth/pages/login.messages.failed'),
            ]);
        }

        if ($user->role === UserRole::Partner) {
            $status = $user->partner?->verification_status;

            if ($status === PartnerVerificationStatus::Suspended) {
                app(LoginLogService::class)->record($user, $identifier, LoginStatus::Failed, reason: LoginFailureReason::AccountInactive);

                throw ValidationException::withMessages([
                    'data.phone' => __('admin.partner_account_suspended'),
                ]);
            }
        }

        app(LoginLogService::class)->record($user, $identifier, LoginStatus::Success);

        Filament::auth()->login($user, $data['remember'] ?? false);
        session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return app(LoginResponse::class);
    }
}
