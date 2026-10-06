<?php

namespace App\Filament\Pages;

use App\Enums\SetupTask;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Country;
use App\Models\CountrySetupTask;
use App\Models\PropertyType;
use App\Models\RefCountry;
use App\Models\Setting;
use App\Models\User;
use App\Services\BannerService;
use App\Services\CurrencyService;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.setup')]
class SetupWizard extends Component
{
    use WithFileUploads;

    /**
     * component classs = logic + memory
     * view = presentation
     *
     * Current wizard step.
     * Step 1 = Admin Account, Step 2+ = configuration steps.
     */
    public int $currentStep = 1;

    /**
     * Total steps. Increment this as new config steps are added.
     * Currently: 1 (admin account) + 1 (system mode) = 2.
     */
    public int $totalSteps = 4;

    // ── Step 1: Admin Account ────────────────────────────────────────────────

    public string $name = '';

    public string $email = '';

    public ?string $dialCode = '+91';

    public ?string $countryCode = 'IN';

    public ?string $phone = null;

    public string $password = '';

    public string $passwordConfirmation = '';

    // ── Step 2: System Mode ──────────────────────────────────────────────────

    public string $systemMode = 'single';

    // ── Step 3: Countries ─────────────────────────────────────────────────────

    /** @var array<int> Selected ref_countries IDs */
    public array $selectedCountries = [];

    public string $countrySearch = '';

    // ── Step 4: Property Type ─────────────────────────────────────────────────

    public string $selectedPropertyType = '';

    /** @var array<int> Selected property type IDs (multi mode only) */
    public array $selectedPropertyTypes = [];

    public bool $showAddPropertyTypeModal = false;

    public string $newPropertyTypeName = '';

    public string $newPropertyTypeDescription = '';

    public $newPropertyTypeIcon = null;

    // ────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        if (! $this->isDatabaseConfigured()) {
            $this->redirect(route('install.purchase-code'));

            return;
        }

        $this->runDatabaseSetup();

        if (Setting::get('setup_completed') === 'true') {
            $this->redirect('/');
        }
    }

    protected function isDatabaseConfigured(): bool
    {
        return ! empty(config('database.connections.mysql.database'))
            && ! empty(config('database.connections.mysql.username'));
    }

    /**
     * Seed default data if not already present — idempotent, safe to re-run.
     */
    protected function runDatabaseSetup(): void
    {
        try {
            if (Schema::hasTable('property_types') && PropertyType::query()->count() === 0) {
                Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]);
            }

            if (! Schema::hasTable('ref_countries') || RefCountry::query()->count() === 0) {
                Artisan::call('app:import-ref-data', ['--no-interaction' => true]);
            }
        } catch (\Throwable) {
            // Silently continue — setup wizard will show with whatever state is available
        }
    }

    public function nextStep(): void
    {
        $this->validateCurrentStep();

        if ($this->currentStep >= $this->totalSteps) {
            $this->complete();

            return;
        }

        $this->currentStep++;
    }

    public function previousStep(): void
    {
        $this->currentStep = max(1, $this->currentStep - 1);
    }

    public function toggleCountry(int $refCountryId): void
    {
        if (in_array($refCountryId, $this->selectedCountries)) {
            $this->selectedCountries = array_values(
                array_diff($this->selectedCountries, [$refCountryId])
            );
        } else {
            $this->selectedCountries[] = $refCountryId;
        }
    }

    public function togglePropertyType(int $propertyTypeId): void
    {
        if (in_array($propertyTypeId, $this->selectedPropertyTypes)) {
            $this->selectedPropertyTypes = array_values(
                array_diff($this->selectedPropertyTypes, [$propertyTypeId])
            );
        } else {
            $this->selectedPropertyTypes[] = $propertyTypeId;
        }
    }

    public function openAddPropertyTypeModal(): void
    {
        $this->showAddPropertyTypeModal = true;
    }

    public function closeAddPropertyTypeModal(): void
    {
        $this->showAddPropertyTypeModal = false;
        $this->newPropertyTypeName = '';
        $this->newPropertyTypeDescription = '';
        $this->newPropertyTypeIcon = null;
        $this->resetValidation(['newPropertyTypeName', 'newPropertyTypeDescription', 'newPropertyTypeIcon']);
    }

    public function createPropertyType(): void
    {
        $this->validate([
            'newPropertyTypeName' => ['required', 'string', 'max:255', 'unique:property_types,name'],
            'newPropertyTypeDescription' => ['required', 'string', 'max:1000'],
            'newPropertyTypeIcon' => ['required', 'file', 'max:5120', 'mimes:png,svg'],
        ]);

        $filename = $this->newPropertyTypeIcon->store('property-types', 'public');

        PropertyType::query()->create([
            'name' => $this->newPropertyTypeName,
            'description' => $this->newPropertyTypeDescription,
            'icon' => $filename,
            'is_default' => false,
            'is_active' => false,
        ]);

        $this->closeAddPropertyTypeModal();
    }

    protected function validateCurrentStep(): void
    {
        match ($this->currentStep) {
            1 => $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'dialCode' => ['required', 'string', 'max:10'],
                'countryCode' => ['required', 'string', 'max:10'],
                'phone' => ['required', 'string', 'max:20'],
                'password' => ['required', 'string', 'min:8', 'same:passwordConfirmation'],
                'passwordConfirmation' => ['required', 'string'],
            ]),
            2 => $this->validate([
                'systemMode' => ['required', 'in:single,multi'],
            ]),
            3 => $this->validate([
                'selectedCountries' => ['required', 'array', 'min:1'],
                'selectedCountries.*' => ['integer', 'exists:ref_countries,id'],
            ]),
            4 => $this->systemMode === 'multi'
                ? $this->validate([
                    'selectedPropertyTypes' => ['required', 'array', 'min:1'],
                    'selectedPropertyTypes.*' => ['integer', 'exists:property_types,id'],
                ])
                : $this->validate([
                    'selectedPropertyType' => ['required', 'exists:property_types,id'],
                ]),
            default => null,
        };
    }

    protected function complete(): void
    {
        /** @var User $user */
        $user = User::query()->create([
            'name' => $this->name,
            'email' => $this->email,
            'country_code' => $this->countryCode,
            'dial_code' => $this->dialCode,
            'phone' => $this->phone ?: null,
            'password' => $this->password,
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        Setting::set('system_mode', $this->systemMode);

        // Create operational countries from selected ref_countries
        $activeCountryIds = [];
        foreach ($this->selectedCountries as $refCountryId) {
            $refCountry = RefCountry::findOrFail($refCountryId);

            $country = Country::query()->updateOrCreate(
                ['ref_country_id' => $refCountry->id],
                [
                    'name' => $refCountry->name,
                    'iso_code' => strtolower($refCountry->iso2),
                    'phone_code' => $refCountry->phonecode,
                    'currency_symbol' => $refCountry->currency_symbol,
                    'currency_code' => $refCountry->currency,
                    'currency_name' => $refCountry->currency_name,
                    'is_active' => true,
                ],
            );

            $activeCountryIds[] = $country->id;

            // Create default banner for this country
            app(BannerService::class)->createDefaultBanner($country);

            // Auto-add currency if not already exists
            try {
                app(CurrencyService::class)->addFromRefCountry($refCountry->id);
            } catch (\RuntimeException $e) {
                // Currency already exists, silently continue
            }
        }

        Country::query()->whereNotIn('id', $activeCountryIds)->update(['is_active' => false]);

        // First selected country becomes the default country
        Country::query()->where('is_default', true)->update(['is_default' => false]);
        Country::query()->where('id', $activeCountryIds[0])->update(['is_default' => true]);

        // Seed the default currency from the first selected country
        $firstRef = RefCountry::find($this->selectedCountries[0]);
        if ($firstRef && $firstRef->currency) {
            try {
                app(CurrencyService::class)->seedDefault(
                    currencyCode: $firstRef->currency,
                    currencyName: $firstRef->currency_name,
                    currencySymbol: $firstRef->currency_symbol,
                    countryName: $firstRef->name,
                    countryIso2: $firstRef->iso2,
                );
            } catch (\Exception $e) {
                // Non-fatal — currency can be added manually from Currency Management
            }
        }

        PropertyType::query()->update(['is_active' => false]);

        $now = now();

        if ($this->systemMode === 'multi') {
            PropertyType::query()->whereIn('id', $this->selectedPropertyTypes)->update(['is_active' => true]);

            foreach ($activeCountryIds as $countryId) {
                foreach ($this->selectedPropertyTypes as $propertyTypeId) {
                    DB::table('country_property_types')->updateOrInsert(
                        ['country_id' => $countryId, 'property_type_id' => $propertyTypeId],
                        ['is_enabled' => true, 'created_at' => $now, 'updated_at' => $now],
                    );
                }
            }
        } else {
            PropertyType::query()->where('id', $this->selectedPropertyType)->update(['is_active' => true]);

            DB::table('country_property_types')->updateOrInsert(
                ['country_id' => $activeCountryIds[0], 'property_type_id' => (int) $this->selectedPropertyType],
                ['is_enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $user->update(['current_country_id' => $activeCountryIds[0]]);

        CountrySetupTask::seedForCountries($activeCountryIds);

        // Auto-mark tasks that are completed as part of the wizard itself
        CountrySetupTask::markComplete(SetupTask::AdminProfile);
        CountrySetupTask::markComplete(SetupTask::PropertyTypeSetup);

        Setting::set('setup_completed', 'true');

        Filament::setCurrentPanel(Filament::getDefaultPanel());
        Filament::auth()->login($user);

        $this->redirect('/');
    }

    public function render(): View
    {
        $countries = match ($this->currentStep) {
            1, 3 => RefCountry::query()
                ->where('flag', true)
                ->when($this->currentStep === 3 && $this->countrySearch, fn ($q) => $q
                    ->where('name', 'like', "%{$this->countrySearch}%")
                    ->orWhere('currency_name', 'like', "%{$this->countrySearch}%"))
                ->orderBy('name')
                ->get(),
            default => new Collection,
        };

        $propertyTypes = $this->currentStep === 4
            ? PropertyType::query()->orderByDesc('is_default')->orderBy('name')->get()
            : new Collection;

        return view('livewire.setup-wizard', compact('countries', 'propertyTypes'));
    }
}
