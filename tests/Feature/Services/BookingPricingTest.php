<?php

namespace Tests\Feature\Services;

use App\Enums\TaxStatus;
use App\Enums\TaxType;
use App\Models\Country;
use App\Models\PropertyRoom;
use App\Models\PropertyType;
use App\Models\Tax;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPricingTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BookingService::class);
    }

    /**
     * Regression test: tax used to be calculated on the full, undiscounted room
     * amount, with the discount only subtracted from the tax-inclusive total
     * afterward — overcharging tax on every discounted booking. Tax must be
     * calculated on the room amount net of the discount instead.
     *
     * Numbers match the reference doc's own worked example exactly:
     * R=10,000, D=1,000, T=10% → discounted room=9,000, tax=900, total=9,900.
     */
    public function test_tax_is_calculated_on_the_discounted_room_amount_not_the_original(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);

        $tax = Tax::create([
            'country_id' => $country->id,
            'name' => 'VAT',
            'type' => TaxType::Percentage,
            'value' => 10,
            'status' => TaxStatus::Active,
        ]);
        $tax->propertyTypes()->attach($propertyType->id);

        $room = PropertyRoom::factory()->create(['base_price_per_night' => 10000]);

        $pricing = $this->service->calculatePricing($room, 1, 1, $country->id, $propertyType->id, discountAmount: 1000);

        $this->assertSame(10000.0, $pricing['base_amount'], 'base_amount must stay the original, undiscounted room amount');
        $this->assertSame(900.0, $pricing['tax_amount']);
        $this->assertSame(9900.0, $pricing['total_amount']);
    }

    public function test_tax_is_calculated_on_the_full_room_amount_when_there_is_no_discount(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);

        $tax = Tax::create([
            'country_id' => $country->id,
            'name' => 'VAT',
            'type' => TaxType::Percentage,
            'value' => 10,
            'status' => TaxStatus::Active,
        ]);
        $tax->propertyTypes()->attach($propertyType->id);

        $room = PropertyRoom::factory()->create(['base_price_per_night' => 10000]);

        $pricing = $this->service->calculatePricing($room, 1, 1, $country->id, $propertyType->id);

        $this->assertSame(10000.0, $pricing['base_amount']);
        $this->assertSame(1000.0, $pricing['tax_amount']);
        $this->assertSame(11000.0, $pricing['total_amount']);
    }

    public function test_fixed_amount_tax_is_unaffected_by_a_discount(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);

        $tax = Tax::create([
            'country_id' => $country->id,
            'name' => 'City Fee',
            'type' => TaxType::Fixed,
            'value' => 50,
            'status' => TaxStatus::Active,
        ]);
        $tax->propertyTypes()->attach($propertyType->id);

        $room = PropertyRoom::factory()->create(['base_price_per_night' => 10000]);

        $pricing = $this->service->calculatePricing($room, 1, 1, $country->id, $propertyType->id, discountAmount: 1000);

        // Flat fee — not a percentage of the room amount — stays 50 regardless of discount.
        $this->assertSame(50.0, $pricing['tax_amount']);
        $this->assertSame(9050.0, $pricing['total_amount']);
    }

    public function test_taxable_amount_is_floored_at_zero_when_discount_exceeds_room_amount(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);

        $tax = Tax::create([
            'country_id' => $country->id,
            'name' => 'VAT',
            'type' => TaxType::Percentage,
            'value' => 10,
            'status' => TaxStatus::Active,
        ]);
        $tax->propertyTypes()->attach($propertyType->id);

        $room = PropertyRoom::factory()->create(['base_price_per_night' => 1000]);

        $pricing = $this->service->calculatePricing($room, 1, 1, $country->id, $propertyType->id, discountAmount: 5000);

        $this->assertSame(1000.0, $pricing['base_amount']);
        $this->assertSame(0.0, $pricing['tax_amount']);
        $this->assertSame(0.0, $pricing['total_amount']);
    }
}
