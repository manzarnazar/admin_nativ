<?php

namespace Tests\Feature\Filament;

use App\Enums\PropertyVerificationStatus;
use App\Filament\Pages\PropertyVerificationDetail;
use App\Models\CommissionPartnerOverride;
use App\Models\CommissionRate;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PropertyVerificationCommissionGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('system_mode', 'multi');
        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * No commission rule = property cannot be approved (plan §3.2) — the Approve
     * button must be disabled, not just silently allow a 0% commission property live.
     */
    public function test_approve_is_disabled_when_no_commission_rule_exists(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
            'verification_status' => PropertyVerificationStatus::Pending,
        ]);

        // No CommissionRate created for this country at all.

        Livewire::test(PropertyVerificationDetail::class, ['propertyId' => $property->id])
            ->assertActionDisabled(TestAction::make('approve'));
    }

    public function test_approve_is_enabled_when_a_country_default_commission_rate_exists(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
            'verification_status' => PropertyVerificationStatus::Pending,
        ]);

        CommissionRate::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => null,
            'rate' => 10,
        ]);

        Livewire::test(PropertyVerificationDetail::class, ['propertyId' => $property->id])
            ->assertActionEnabled(TestAction::make('approve'));
    }

    public function test_approve_is_enabled_when_only_a_partner_override_exists(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
            'verification_status' => PropertyVerificationStatus::Pending,
        ]);

        CommissionPartnerOverride::create([
            'country_id' => $country->id,
            'partner_id' => $partner->id,
            'property_type_id' => $propertyType->id,
            'rate' => 12,
        ]);

        Livewire::test(PropertyVerificationDetail::class, ['propertyId' => $property->id])
            ->assertActionEnabled(TestAction::make('approve'));
    }
}
