<?php

namespace App\Filament\Pages\Auth;

use App\Enums\LoginFailureReason;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoginLogService;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response) {
            /** @var User|null $user */
            $user = Filament::auth()->user();

            if ($user?->role === UserRole::Staff && $user->status !== UserStatus::Active) {
                Filament::auth()->logout();

                // Auth::attempt() already succeeded and got logged as a Success login — this
                // rejection only happens afterward, so correct that row rather than leaving a
                // blocked sign-in showing as successful in the Login Report.
                app(LoginLogService::class)->correctToFailed($user, LoginFailureReason::AccountInactive);

                throw ValidationException::withMessages([
                    'data.email' => __('admin.account_blocked_login_error'),
                ]);
            }

            $user?->update(['last_login_at' => now()]);
        }

        return $response;
    }

    public function getHeading(): string|Htmlable|null
    {
        return Setting::get('app_name', config('app.name')).' Admin';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        return __('admin.sign_in_to_access_the_admin_panel');
    }

    public function getFormContentComponent(): Component
    {
        return parent::getFormContentComponent()
            ->extraAttributes(['@keydown.enter.prevent' => '$wire.authenticate()']);
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->prefixIcon('heroicon-m-envelope')
            ->placeholder(__('admin.eg_jackwilliams11gmailcom'));
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->prefixIcon('heroicon-m-lock-closed');
    }
}
