<?php

namespace Tests\Feature\Services;

use App\Models\CommissionRate;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Setting;
use App\Services\PropertyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PropertyService::savePaymentConfig()'s advance_percentage-vs-commission-rate guard —
 * the server-side backstop behind PartnerPropertyCreate.php's form validation. Commission
 * is charged on the full base_amount regardless of how much is collected online, so an
 * advance_percentage lower than the effective commission rate would leave the amount
 * collected online short of covering commission owed on the booking. Single-mode and
 * no-partner properties have no wallet-crediting concept at all (checkIn()'s own crediting
 * block is gated the exact same way), so the guard is a no-op for both.
 */
class PropertyPaymentConfigCommissionGuardTest extends TestCase
{
    use RefreshDatabase;

    private PropertyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PropertyService::class);
    }

    private function enableMultiMode(): void
    {
        Setting::set('system_mode', 'multi');
    }

    public function test_advance_percentage_below_commission_rate_is_rejected_for_a_multi_mode_partner_property(): void
    {
        $this->enableMultiMode();
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();
        CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => null, 'rate' => 20]);

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->savePaymentConfig($property, [
            'pay_at_property' => true,
            'advance_percentage' => 10,
        ]);
    }

    public function test_advance_percentage_at_or_above_commission_rate_is_accepted(): void
    {
        $this->enableMultiMode();
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();
        CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => null, 'rate' => 20]);

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
        ]);

        $this->service->savePaymentConfig($property, [
            'pay_at_property' => true,
            'advance_percentage' => 20,
        ]);

        $this->assertSame('20.00', $property->fresh()->advance_percentage);
    }

    public function test_advance_percentage_below_commission_rate_is_allowed_in_single_mode(): void
    {
        // No enableMultiMode() — SystemMode::isMulti() defaults to false.
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => null, 'rate' => 20]);

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => null,
        ]);

        $this->service->savePaymentConfig($property, [
            'pay_at_property' => true,
            'advance_percentage' => 5,
        ]);

        $this->assertSame('5.00', $property->fresh()->advance_percentage);
    }

    public function test_advance_percentage_below_commission_rate_is_allowed_when_the_property_has_no_partner(): void
    {
        $this->enableMultiMode();
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => null, 'rate' => 20]);

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => null,
        ]);

        $this->service->savePaymentConfig($property, [
            'pay_at_property' => true,
            'advance_percentage' => 5,
        ]);

        $this->assertSame('5.00', $property->fresh()->advance_percentage);
    }
}
