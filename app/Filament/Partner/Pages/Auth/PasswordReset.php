<?php

namespace App\Filament\Partner\Pages\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\OtpService;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordReset extends SimplePage implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.partner.auth.password-reset';

    public string $phase = 'email';

    public string $email = '';

    public string $otp = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return match ($this->phase) {
            'otp' => __('admin.enter_code'),
            'password' => __('admin.create_new_password'),
            default => __('admin.forgot_password'),
        };
    }

    public function getSubheading(): string|Htmlable|null
    {
        return match ($this->phase) {
            'otp' => new HtmlString(__('admin.otp_sent_to', ['email' => $this->maskEmail($this->email)])),
            'password' => __('admin.new_password_different'),
            default => __('admin.enter_email_to_reset'),
        };
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) < 2) {
            return $email;
        }
        [$local, $domain] = $parts;
        $masked = substr($local, 0, 2).str_repeat('*', max(0, strlen($local) - 2));

        return '<strong>'.$masked.'@'.$domain.'</strong>';
    }

    public function submitEmail(): void
    {
        $this->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::where('email', $this->email)->where('role', UserRole::Partner)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => __('admin.email_not_found'),
            ]);
        }

        app(OtpService::class)->send($this->email, 'partner_password_reset');

        $this->phase = 'otp';
    }

    public function verifyOtp(): void
    {
        $this->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if (! app(OtpService::class)->verify($this->email, $this->otp, 'partner_password_reset')) {
            throw ValidationException::withMessages([
                'otp' => __('admin.invalid_or_expired_otp'),
            ]);
        }

        $this->phase = 'password';
    }

    public function resendOtp(): void
    {
        $cooldown = app(OtpService::class)->secondsUntilResend($this->email, 'partner_password_reset');

        if ($cooldown > 0) {
            return;
        }

        app(OtpService::class)->send($this->email, 'partner_password_reset');

        Notification::make()
            ->title(__('admin.otp_resent'))
            ->success()
            ->send();
    }

    public function submitPassword(): void
    {
        $this->validate([
            'password' => ['required', 'min:8', Password::defaults()],
            'passwordConfirmation' => ['required', 'same:password'],
        ]);

        $user = User::where('email', $this->email)->where('role', UserRole::Partner)->first();

        if ($user) {
            $user->password = Hash::make($this->password);
            $user->save();

            Filament::auth()->login($user);
            session()->regenerate();
        }

        Notification::make()
            ->title(__('admin.password_reset_successfully'))
            ->success()
            ->send();

        $this->redirect(Filament::getPanel('partner')->getUrl());
    }
}
