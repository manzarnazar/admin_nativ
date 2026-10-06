<?php

namespace App\Livewire;

use App\Enums\UserRole;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Models\Country;
use App\Models\Language;
use App\Models\Property;
use App\Models\User;
use App\Services\NotificationService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Livewire\Concerns\HasUserMenu;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Topbar extends Component implements HasActions, HasSchemas
{
    use HasUserMenu;
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * Which global context switchers to render. Pages may opt out via DeclaresTopbarControls.
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

    /**
     * Resolve a page's topbar declaration — falling back to its resource for resource pages.
     *
     * @return array{country?: bool, property?: bool}|null
     */
    private function resolveTopbarControls(mixed $pageClass): ?array
    {
        if (! is_string($pageClass)) {
            return null;
        }

        if (is_subclass_of($pageClass, DeclaresTopbarControls::class)) {
            return $pageClass::topbarControls();
        }

        // Resource pages (List/Create/Edit/View) share one declaration on their resource.
        if (method_exists($pageClass, 'getResource')) {
            $resource = $pageClass::getResource();

            if (is_string($resource) && is_subclass_of($resource, DeclaresTopbarControls::class)) {
                return $resource::topbarControls();
            }
        }

        return null;
    }

    public function boot(): void
    {
        if (session()->has('permission_denied')) {
            session()->forget('permission_denied');

            Notification::make()
                ->title(__('admin.no_permission_title'))
                ->body(__('admin.no_permission_body'))
                ->danger()
                ->duration(6000)
                ->send();
        }
    }

    #[On('refresh-topbar')]
    public function refresh(): void {}

    public function switchCountry(int $countryId): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->role === UserRole::Staff && $user->branch_id !== null) {
            return;
        }

        $user->switchCountry($countryId);

        $this->js('window.location.reload()');
    }

    public function switchProperty(?int $propertyId): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->role === UserRole::Staff && $user->branch_id !== null) {
            return;
        }

        $user->switchProperty($propertyId);

        $this->js('window.location.reload()');
    }

    public function getPropertiesProperty(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->current_country_id) {
            return collect();
        }

        $query = Property::query()
            ->where('country_id', $user->current_country_id)
            ->orderBy('name');

        if ($user->role === UserRole::Staff && $user->branch_id !== null) {
            $query->where('id', $user->branch_id);
        }

        return $query->get(['id', 'name', 'ref_city_id']);
    }

    public function getCurrentPropertyProperty(): ?Property
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->current_branch_id) {
            return $this->properties->first()
                ? Property::query()->with('refCity')->find($this->properties->first()->id)
                : null;
        }

        return Property::query()
            ->with('refCity')
            ->find($user->current_branch_id);
    }

    public function getOperatingCountriesProperty(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        $query = Country::query()->where('is_active', true)->orderBy('name');

        if ($user->role === UserRole::Staff && $user->branch_id !== null && $user->country_id) {
            $query->where('id', $user->country_id);
        }

        return $query->get();
    }

    public function getCurrentCountryProperty(): ?Country
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user?->current_country_id) {
            return $this->operatingCountries->first();
        }

        return Country::query()->find($user->current_country_id);
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
        return view('livewire.topbar');
    }
}
