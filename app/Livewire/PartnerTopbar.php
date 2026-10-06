<?php

namespace App\Livewire;

use App\Filament\Contracts\DeclaresTopbarControls;
use App\Models\Country;
use App\Models\Language;
use App\Models\Property;
use App\Models\User;
use App\Services\NotificationService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Livewire\Concerns\HasUserMenu;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PartnerTopbar extends Component implements HasActions, HasSchemas
{
    use HasUserMenu;
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * Which context switchers to render. Partner pages may opt out via DeclaresTopbarControls.
     *
     * @var array{country: bool, property: bool}
     */
    public array $topbarControls = ['country' => true, 'property' => true];

    public function mount(): void
    {
        $controls = $this->resolveTopbarControls(request()->route()?->getAction('controller'));

        if ($controls !== null) {
            $this->topbarControls = array_merge($this->topbarControls, $controls);
        }
    }

    private function resolveTopbarControls(mixed $pageClass): ?array
    {
        if (! is_string($pageClass)) {
            return null;
        }

        if (is_subclass_of($pageClass, DeclaresTopbarControls::class)) {
            return $pageClass::topbarControls();
        }

        if (method_exists($pageClass, 'getResource')) {
            $resource = $pageClass::getResource();

            if (is_string($resource) && is_subclass_of($resource, DeclaresTopbarControls::class)) {
                return $resource::topbarControls();
            }
        }

        return null;
    }

    public function switchCountry(int $countryId): void
    {
        session([
            'partner_current_country_id' => $countryId,
            'partner_current_branch_id' => null,
        ]);
        $this->js('window.location.reload()');
    }

    public function switchProperty(?int $propertyId): void
    {
        session(['partner_current_branch_id' => $propertyId]);
        $this->js('window.location.reload()');
    }

    public function getPartnerCountriesProperty(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->partner?->countries ?? collect();
    }

    public function getCurrentCountryProperty(): ?Country
    {
        $countries = $this->partnerCountries;

        if ($countries->isEmpty()) {
            return null;
        }

        $countryId = session('partner_current_country_id');

        if ($countryId) {
            return $countries->firstWhere('id', (int) $countryId) ?? $countries->first();
        }

        return $countries->first();
    }

    public function getPropertiesProperty(): Collection
    {
        /** @var User $user */
        $user = Auth::user();
        $partner = $user->partner;
        $currentCountry = $this->currentCountry;

        if (! $partner || ! $currentCountry) {
            return collect();
        }

        return Property::query()
            ->where('partner_id', $partner->id)
            ->where('country_id', $currentCountry->id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getCurrentPropertyProperty(): ?Property
    {
        $properties = $this->properties;

        if ($properties->isEmpty()) {
            return null;
        }

        $propertyId = session('partner_current_branch_id');

        if ($propertyId) {
            return $properties->firstWhere('id', (int) $propertyId) ?? $properties->first();
        }

        return $properties->first();
    }

    public function getLanguagesProperty(): Collection
    {
        return Language::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();
    }

    public function getCurrentLanguageProperty(): ?Language
    {
        $locale = session('locale', App::getLocale());

        return Language::query()->where('code', $locale)->first()
            ?? Language::query()->where('is_default', true)->first();
    }

    public function switchLanguage(string $code): void
    {
        session(['locale' => $code]);
        App::setLocale($code);
        $this->js('window.location.reload()');
    }

    public function getUnreadCountProperty(): int
    {
        /** @var User $user */
        $user = Auth::user();

        return $user?->unreadNotifications()->count() ?? 0;
    }

    public function getLatestNotificationsProperty(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return $user?->unreadNotifications()->latest()->limit(3)->get() ?? collect();
    }

    public function markAsRead(string $notificationId): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user) {
            app(NotificationService::class)->markAsRead($notificationId, $user->id);
        }
    }

    public function markAllAsRead(): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user) {
            app(NotificationService::class)->markAllAsRead($user->id);
        }
    }

    public function render(): View
    {
        return view('livewire.partner-topbar');
    }
}
