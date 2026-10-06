<?php

namespace App\Filament\Partner\Pages\Auth;

use App\Actions\CreatePartnerAction;
use App\Models\Country;
use App\Models\PropertyType;
use App\Services\OtpService;
use App\Services\PartnerVerificationService;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

class Register extends BaseRegister
{
    protected string $view = 'filament.partner.auth.register';

    public string $phase = 'account';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public string $otp = '';

    public string $propertyTypeId = '';

    public string $countryId = '';

    public string $countrySearch = '';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getHeading(): string|Htmlable|null
    {
        return match ($this->phase) {
            'otp' => __('admin.verify_your_email'),
            default => config('app.name').' Partner',
        };
    }

    public function getSubheading(): string|Htmlable|null
    {
        return match ($this->phase) {
            'account' => __('admin.create_your_partner_account'),
            'otp' => new HtmlString(__('admin.otp_sent_to', ['email' => $this->maskEmail($this->email)])),
            default => null,
        };
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $masked = substr($local, 0, 2).str_repeat('*', max(0, strlen($local) - 2));

        return '<strong>'.$masked.'@'.$domain.'</strong>';
    }

    public function submitAccount(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'min:8', Password::defaults()],
            'passwordConfirmation' => ['required', 'same:password'],
        ]);

        app(OtpService::class)->send($this->email, 'partner_registration');

        $this->phase = 'otp';
    }

    public function verifyOtp(): void
    {
        $this->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if (! app(OtpService::class)->verify($this->email, $this->otp, 'partner_registration')) {
            throw ValidationException::withMessages([
                'otp' => __('admin.invalid_or_expired_otp'),
            ]);
        }

        $this->phase = 'country';
    }

    public function resendOtp(): void
    {
        $cooldown = app(OtpService::class)->secondsUntilResend($this->email, 'partner_registration');

        if ($cooldown > 0) {
            return;
        }

        app(OtpService::class)->send($this->email, 'partner_registration');

        Notification::make()
            ->title(__('admin.otp_resent'))
            ->success()
            ->send();
    }

    public function selectCountry(int $id): void
    {
        $this->countryId = (string) $id;
    }

    public function submitCountry(): void
    {
        $this->validate([
            'countryId' => ['required', 'exists:countries,id'],
        ]);

        $this->propertyTypeId = '';
        $this->phase = 'property_type';
    }

    public function selectPropertyType(int $id): void
    {
        $this->propertyTypeId = (string) $id;
    }

    public function submitPropertyType(): void
    {
        $this->validate([
            'propertyTypeId' => ['required', 'exists:property_types,id'],
        ]);

        $partner = app(CreatePartnerAction::class)->handle([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'property_type_id' => (int) $this->propertyTypeId,
            'country_id' => (int) $this->countryId,
        ]);

        app(PartnerVerificationService::class)->maybeAutoApprove($partner);

        Notification::make()
            ->title(__('admin.registration_submitted_title'))
            ->body(__('admin.registration_submitted_body'))
            ->success()
            ->persistent()
            ->send();

        $this->redirect(Filament::getPanel('partner')->getLoginUrl());
    }

    #[Computed]
    public function countries()
    {
        return Country::query()
            ->where('is_active', true)
            ->when(
                $this->countrySearch,
                fn ($q) => $q->where('name', 'like', '%'.$this->countrySearch.'%')
            )
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function propertyTypes()
    {
        $countryId = (int) $this->countryId;

        return PropertyType::query()
            ->where('is_active', true)
            ->where(function ($q) use ($countryId) {
                $q->whereHas(
                    'countries',
                    fn ($sub) => $sub
                        ->where('country_property_types.country_id', $countryId)
                        ->where('country_property_types.is_enabled', true)
                )->orWhereDoesntHave('countries');
            })
            ->orderBy('name')
            ->get();
    }
}
