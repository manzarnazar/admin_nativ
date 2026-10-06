<?php

namespace Tests\Feature\Services;

use App\Enums\CustomerSegment;
use App\Enums\PromoDiscountType;
use App\Enums\TaxStatus;
use App\Enums\TaxType;
use App\Models\Country;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\ReferralReward;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Services\Api\ReferralService;
use App\Services\PromoCodeService;
use App\Services\PropertyService;
use App\Services\PropertyTypeService;
use App\Services\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every percentage-shaped input across the app must be a number between 0-100
 * (or, for a percentage-vs-fixed-amount toggle, only bounded when the type is
 * actually "percentage"). This is the consolidated coverage for that sweep —
 * one guard per write path, mirroring the pattern already proven for
 * CommissionService (see CommissionServiceTest's "Rate validation guard" group)
 * and for CancellationPolicyService::saveRule() (see CancellationPolicyServiceTest).
 */
class PercentageValidationGuardsTest extends TestCase
{
    use RefreshDatabase;

    // ── PropertyService::savePaymentConfig — advance_percentage ────────────

    public function test_save_payment_config_rejects_a_negative_advance_percentage(): void
    {
        $property = Property::factory()->create();

        $this->expectException(\RuntimeException::class);
        app(PropertyService::class)->savePaymentConfig($property, ['advance_percentage' => -10]);
    }

    public function test_save_payment_config_rejects_an_advance_percentage_above_100(): void
    {
        $property = Property::factory()->create();

        $this->expectException(\RuntimeException::class);
        app(PropertyService::class)->savePaymentConfig($property, ['advance_percentage' => 150]);
    }

    public function test_save_payment_config_accepts_a_valid_advance_percentage(): void
    {
        $property = Property::factory()->create();

        app(PropertyService::class)->savePaymentConfig($property, ['pay_at_property' => true, 'advance_percentage' => 25]);

        $this->assertSame(25.0, (float) $property->fresh()->advance_percentage);
    }

    public function test_save_payment_config_allows_a_null_advance_percentage(): void
    {
        $property = Property::factory()->create();

        app(PropertyService::class)->savePaymentConfig($property, ['pay_at_property' => true, 'advance_percentage' => null]);

        $this->assertNull($property->fresh()->advance_percentage);
    }

    // ── TaxService — value is only bounded when type is percentage ─────────

    public function test_create_tax_rejects_an_out_of_range_percentage_value(): void
    {
        $country = Country::factory()->create();

        $this->expectException(\RuntimeException::class);
        app(TaxService::class)->createTax([
            'name' => 'VAT', 'type' => TaxType::Percentage->value, 'value' => 150, 'status' => TaxStatus::Active->value,
        ], $country->id);
    }

    public function test_create_tax_allows_any_non_capped_fixed_value(): void
    {
        $country = Country::factory()->create();

        $tax = app(TaxService::class)->createTax([
            'name' => 'Service Fee', 'type' => TaxType::Fixed->value, 'value' => 500, 'status' => TaxStatus::Active->value,
        ], $country->id);

        $this->assertSame(500.0, (float) $tax->value);
    }

    public function test_update_tax_rejects_an_out_of_range_percentage_value_even_when_type_is_not_resent(): void
    {
        $tax = Tax::create(['country_id' => Country::factory()->create()->id, 'name' => 'VAT', 'type' => TaxType::Percentage, 'value' => 10, 'status' => TaxStatus::Active]);

        // Payload only updates value, doesn't re-send type — guard must fall back
        // to the tax's own current type rather than skip validation.
        $this->expectException(\RuntimeException::class);
        app(TaxService::class)->updateTax($tax, ['value' => 200]);
    }

    public function test_create_and_associate_tax_rejects_an_out_of_range_percentage_value(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();

        $this->expectException(\RuntimeException::class);
        app(PropertyTypeService::class)->createAndAssociateTax($propertyType, [
            'name' => 'VAT', 'type' => TaxType::Percentage->value, 'value' => -5, 'status' => TaxStatus::Active->value,
        ], $country->id);
    }

    // ── PromoCodeService — discount_value is only bounded when type is percentage ─

    private function promoPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'TEST'.fake()->unique()->numerify('###'),
            'title' => 'Test Promo',
            'discount_type' => PromoDiscountType::Percentage->value,
            'discount_value' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'usage_limit' => 10,
            'customer_segment' => CustomerSegment::All->value,
        ], $overrides);
    }

    public function test_create_promo_code_rejects_an_out_of_range_percentage_discount(): void
    {
        $country = Country::factory()->create();

        $this->expectException(\RuntimeException::class);
        app(PromoCodeService::class)->create($this->promoPayload(['discount_value' => 250]), $country->id);
    }

    public function test_create_promo_code_allows_a_large_fixed_discount_value(): void
    {
        $country = Country::factory()->create();

        $promo = app(PromoCodeService::class)->create($this->promoPayload([
            'discount_type' => PromoDiscountType::Fixed->value,
            'discount_value' => 5000,
        ]), $country->id);

        $this->assertSame(5000.0, (float) $promo->discount_value);
    }

    public function test_update_promo_code_rejects_an_out_of_range_percentage_discount_even_when_type_is_not_resent(): void
    {
        $country = Country::factory()->create();
        $promo = app(PromoCodeService::class)->create($this->promoPayload(['discount_value' => 10]), $country->id);

        $this->expectException(\RuntimeException::class);
        app(PromoCodeService::class)->update($promo, ['discount_value' => 300]);
    }

    // ── ReferralService — Settings-sourced percentage is clamped, not thrown ─

    public function test_referee_reward_clamps_an_out_of_range_setting_instead_of_breaking_signup(): void
    {
        Setting::set('referral_referee_percentage', '250');

        $referrer = User::factory()->create();
        $newUser = User::factory()->create(['referred_by' => $referrer->id]);

        app(ReferralService::class)->issueRefereeReward($newUser);

        $reward = ReferralReward::where('referee_id', $newUser->id)->firstOrFail();
        $this->assertSame(100.0, (float) $reward->refereeCoupon->value);
    }

    public function test_referee_reward_clamps_a_negative_setting_to_zero(): void
    {
        Setting::set('referral_referee_percentage', '-20');

        $referrer = User::factory()->create();
        $newUser = User::factory()->create(['referred_by' => $referrer->id]);

        app(ReferralService::class)->issueRefereeReward($newUser);

        $reward = ReferralReward::where('referee_id', $newUser->id)->firstOrFail();
        $this->assertSame(0.0, (float) $reward->refereeCoupon->value);
    }
}
