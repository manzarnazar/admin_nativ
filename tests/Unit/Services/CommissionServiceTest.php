<?php

namespace Tests\Unit\Services;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Booking;
use App\Models\CommissionPartnerOverride;
use App\Models\CommissionRate;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PropertyType;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CommissionService::class);
    }

    public function test_resolve_rate_returns_zero_when_nothing_configured(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();

        $rate = $this->service->resolveRate($country->id, null, $propertyType->id);

        $this->assertSame(0.0, $rate);
    }

    public function test_resolve_rate_falls_back_to_country_default_when_no_type_rate(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();

        CommissionRate::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => null,
            'rate' => 12.5,
        ]);

        $rate = $this->service->resolveRate($country->id, null, $propertyType->id);

        $this->assertSame(12.5, $rate);
    }

    public function test_resolve_rate_prefers_country_type_rate_over_country_default(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();

        CommissionRate::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => null,
            'rate' => 12.5,
        ]);

        CommissionRate::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'rate' => 8.0,
        ]);

        $rate = $this->service->resolveRate($country->id, null, $propertyType->id);

        $this->assertSame(8.0, $rate);
    }

    public function test_resolve_rate_prefers_partner_override_over_everything(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();
        $partner = Partner::factory()->create();

        CommissionRate::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => null,
            'rate' => 12.5,
        ]);

        CommissionRate::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'rate' => 8.0,
        ]);

        CommissionPartnerOverride::factory()->create([
            'country_id' => $country->id,
            'partner_id' => $partner->id,
            'property_type_id' => $propertyType->id,
            'rate' => 3.0,
        ]);

        $rate = $this->service->resolveRate($country->id, $partner->id, $propertyType->id);

        $this->assertSame(3.0, $rate);
    }

    public function test_resolve_rate_ignores_override_for_a_different_property_type(): void
    {
        $country = Country::factory()->create();
        $propertyTypeA = PropertyType::factory()->create();
        $propertyTypeB = PropertyType::factory()->create();
        $partner = Partner::factory()->create();

        CommissionRate::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => null,
            'rate' => 12.5,
        ]);

        CommissionPartnerOverride::factory()->create([
            'country_id' => $country->id,
            'partner_id' => $partner->id,
            'property_type_id' => $propertyTypeA->id,
            'rate' => 3.0,
        ]);

        $rate = $this->service->resolveRate($country->id, $partner->id, $propertyTypeB->id);

        $this->assertSame(12.5, $rate);
    }

    public function test_resolve_rate_ignores_override_for_a_different_country(): void
    {
        $countryA = Country::factory()->create();
        $countryB = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();
        $partner = Partner::factory()->create();

        CommissionRate::factory()->create([
            'country_id' => $countryB->id,
            'property_type_id' => null,
            'rate' => 20.0,
        ]);

        CommissionPartnerOverride::factory()->create([
            'country_id' => $countryA->id,
            'partner_id' => $partner->id,
            'property_type_id' => $propertyType->id,
            'rate' => 3.0,
        ]);

        $rate = $this->service->resolveRate($countryB->id, $partner->id, $propertyType->id);

        $this->assertSame(20.0, $rate);
    }

    public function test_resolve_rate_skips_override_lookup_when_partner_id_is_null(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();
        $partner = Partner::factory()->create();

        // Override exists for *some* partner, but we're resolving with no partner context.
        CommissionPartnerOverride::factory()->create([
            'country_id' => $country->id,
            'partner_id' => $partner->id,
            'property_type_id' => $propertyType->id,
            'rate' => 3.0,
        ]);

        CommissionRate::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => null,
            'rate' => 15.0,
        ]);

        $rate = $this->service->resolveRate($country->id, null, $propertyType->id);

        $this->assertSame(15.0, $rate);
    }

    public function test_calculate_commission_computes_percentage_of_total(): void
    {
        $this->assertSame(100.0, $this->service->calculateCommission(1000, 10));
    }

    public function test_calculate_commission_rounds_to_two_decimals(): void
    {
        $this->assertSame(33.33, $this->service->calculateCommission(100, 33.333));
    }

    public function test_calculate_commission_is_zero_at_zero_rate(): void
    {
        $this->assertSame(0.0, $this->service->calculateCommission(500, 0));
    }

    public function test_calculate_partner_credit_is_base_amount_minus_commission(): void
    {
        $this->assertSame(880.0, $this->service->calculatePartnerCredit(1000, 120));
    }

    public function test_calculate_partner_credit_is_floored_at_zero(): void
    {
        $this->assertSame(0.0, $this->service->calculatePartnerCredit(100, 150));
    }

    // ── calculateCheckInPartnerCredit ───────────────────────────────────────

    public function test_calculate_check_in_partner_credit_matches_the_old_formula_when_fully_paid(): void
    {
        $booking = Booking::factory()->create([
            'payment_status' => PaymentStatus::Paid,
            'base_amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'commission_amount' => 100,
        ]);

        $this->assertSame(900.0, $this->service->calculateCheckInPartnerCredit($booking));
    }

    public function test_calculate_check_in_partner_credit_excludes_tax_when_fully_paid(): void
    {
        // total_amount (1100) includes 100 of tax on top of the 1000 room subtotal — the
        // partner's credit must still come out to exactly base_amount - commission, tax
        // playing no part in it (commission/partner credit are always tax-exclusive).
        $booking = Booking::factory()->create([
            'payment_status' => PaymentStatus::Paid,
            'base_amount' => 1000,
            'tax_amount' => 100,
            'total_amount' => 1100,
            'commission_amount' => 100,
        ]);

        $this->assertSame(900.0, $this->service->calculateCheckInPartnerCredit($booking));
    }

    public function test_calculate_check_in_partner_credit_nets_against_what_was_actually_collected_for_a_partial_payment(): void
    {
        // The exact scenario worked through by hand: 1000 room, 10% commission = 100,
        // 5% advance collected online = 50 → partner owes the platform 50, not the other
        // way around.
        $booking = Booking::factory()->create([
            'payment_status' => PaymentStatus::Partial,
            'base_amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'commission_amount' => 100,
        ]);
        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => User::factory()->create()->id,
            'gateway_type' => 'stripe',
            'amount' => 50,
            'currency' => 'USD',
            'payment_type' => 'partial',
            'status' => PaymentTransactionStatus::Success,
        ]);

        $this->assertSame(-50.0, $this->service->calculateCheckInPartnerCredit($booking->fresh()));
    }

    public function test_calculate_check_in_partner_credit_is_negative_for_a_pay_at_property_booking(): void
    {
        // No Payment row at all — nothing was ever collected online.
        $booking = Booking::factory()->create([
            'payment_status' => PaymentStatus::Unpaid,
            'base_amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'commission_amount' => 100,
        ]);

        $this->assertSame(-100.0, $this->service->calculateCheckInPartnerCredit($booking));
    }

    public function test_calculate_check_in_partner_credit_ignores_a_failed_payment_attempt(): void
    {
        $booking = Booking::factory()->create([
            'payment_status' => PaymentStatus::Partial,
            'base_amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'commission_amount' => 100,
        ]);
        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => User::factory()->create()->id,
            'gateway_type' => 'stripe',
            'amount' => 300,
            'currency' => 'USD',
            'payment_type' => 'partial',
            'status' => PaymentTransactionStatus::Failed,
        ]);

        $this->assertSame(-100.0, $this->service->calculateCheckInPartnerCredit($booking->fresh()));
    }

    public function test_calculate_check_in_partner_credit_excludes_a_manual_payment_recorded_solely_from_payment_status_paid(): void
    {
        // A booking whose only Payment row is Manual (cash/UPI collected directly by the
        // property, e.g. via TodaysCheckins' "record payment" action or an admin/partner
        // manually confirming a booking) — payment_status is Paid, but the property holds
        // this money directly, not the platform. Must debit the full commission, not credit.
        $booking = Booking::factory()->create([
            'payment_status' => PaymentStatus::Paid,
            'base_amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'commission_amount' => 100,
        ]);
        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => User::factory()->create()->id,
            'gateway_type' => PaymentGateway::Manual,
            'amount' => 1000,
            'currency' => 'USD',
            'payment_type' => 'full',
            'status' => PaymentTransactionStatus::Success,
            'gateway_response' => ['collected_at_property' => true],
        ]);

        $this->assertSame(-100.0, $this->service->calculateCheckInPartnerCredit($booking->fresh()));
    }

    public function test_calculate_check_in_partner_credit_excludes_a_manual_top_up_on_top_of_a_real_partial_payment(): void
    {
        // 5% (50) collected online via a real gateway, then the remaining 95% (950)
        // recorded as collected in cash at check-in (Manual) — payment_status flips to
        // Paid, but only the 50 ever reached the platform. Must net exactly like a plain
        // partial payment, ignoring the Manual top-up entirely.
        $booking = Booking::factory()->create([
            'payment_status' => PaymentStatus::Partial,
            'base_amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'commission_amount' => 100,
        ]);
        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => User::factory()->create()->id,
            'gateway_type' => 'stripe',
            'amount' => 50,
            'currency' => 'USD',
            'payment_type' => 'partial',
            'status' => PaymentTransactionStatus::Success,
        ]);
        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => User::factory()->create()->id,
            'gateway_type' => PaymentGateway::Manual,
            'amount' => 950,
            'currency' => 'USD',
            'payment_type' => 'full',
            'status' => PaymentTransactionStatus::Success,
            'gateway_response' => ['collected_at_property' => true],
        ]);
        $booking->update(['payment_status' => PaymentStatus::Paid]);

        $this->assertSame(-50.0, $this->service->calculateCheckInPartnerCredit($booking->fresh()));
    }

    public function test_set_country_rate_creates_then_updates_in_place(): void
    {
        $country = Country::factory()->create();

        $this->service->setCountryRate($country->id, 10);
        $this->assertDatabaseHas('commission_rates', [
            'country_id' => $country->id,
            'property_type_id' => null,
            'rate' => 10,
        ]);

        $this->service->setCountryRate($country->id, 15);

        $this->assertSame(1, CommissionRate::query()->where('country_id', $country->id)->whereNull('property_type_id')->count());
        $this->assertDatabaseHas('commission_rates', [
            'country_id' => $country->id,
            'property_type_id' => null,
            'rate' => 15,
        ]);
    }

    public function test_set_property_type_rate_creates_then_updates_in_place(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();

        $this->service->setPropertyTypeRate($country->id, $propertyType->id, 7);
        $this->service->setPropertyTypeRate($country->id, $propertyType->id, 9);

        $this->assertSame(
            1,
            CommissionRate::query()->where('country_id', $country->id)->where('property_type_id', $propertyType->id)->count()
        );
        $this->assertDatabaseHas('commission_rates', [
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'rate' => 9,
        ]);
    }

    public function test_delete_rate_removes_the_row(): void
    {
        $rate = CommissionRate::factory()->create();

        $this->service->deleteRate($rate);

        $this->assertDatabaseMissing('commission_rates', ['id' => $rate->id]);
    }

    public function test_upsert_partner_override_creates_then_updates_in_place(): void
    {
        $country = Country::factory()->create();
        $partner = Partner::factory()->create();
        $propertyType = PropertyType::factory()->create();

        $data = [
            'country_id' => $country->id,
            'partner_id' => $partner->id,
            'property_type_id' => $propertyType->id,
            'rate' => 5,
        ];

        $this->service->upsertPartnerOverride($data);
        $this->service->upsertPartnerOverride([...$data, 'rate' => 6, 'description' => 'Renegotiated']);

        $this->assertSame(1, CommissionPartnerOverride::query()->count());
        $this->assertDatabaseHas('commission_partner_overrides', [
            'country_id' => $country->id,
            'partner_id' => $partner->id,
            'property_type_id' => $propertyType->id,
            'rate' => 6,
            'description' => 'Renegotiated',
        ]);
    }

    public function test_delete_partner_override_removes_the_row(): void
    {
        $override = CommissionPartnerOverride::factory()->create();

        $this->service->deletePartnerOverride($override);

        $this->assertDatabaseMissing('commission_partner_overrides', ['id' => $override->id]);
    }

    // ── resolveRateWithDetails ─────────────────────────────────────────────

    public function test_resolve_rate_with_details_returns_none_when_nothing_configured(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();

        $details = $this->service->resolveRateWithDetails($country->id, null, $propertyType->id);

        $this->assertSame(0.0, $details['rate']);
        $this->assertNull($details['rate_id']);
        $this->assertSame('none', $details['source']);
    }

    public function test_resolve_rate_with_details_returns_country_default_source(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();

        $rate = CommissionRate::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => null,
            'rate' => 12.0,
        ]);

        $details = $this->service->resolveRateWithDetails($country->id, null, $propertyType->id);

        $this->assertSame(12.0, $details['rate']);
        $this->assertSame($rate->id, $details['rate_id']);
        $this->assertSame('country_default', $details['source']);
    }

    public function test_resolve_rate_with_details_returns_country_type_override_source(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();

        CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => null, 'rate' => 5.0]);
        $typeRate = CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => $propertyType->id, 'rate' => 18.0]);

        $details = $this->service->resolveRateWithDetails($country->id, null, $propertyType->id);

        $this->assertSame(18.0, $details['rate']);
        $this->assertSame($typeRate->id, $details['rate_id']);
        $this->assertSame('country_type_override', $details['source']);
    }

    public function test_resolve_rate_with_details_returns_partner_override_source_with_null_rate_id(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();
        $partner = Partner::factory()->create();

        CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => null, 'rate' => 10.0]);
        CommissionPartnerOverride::factory()->create([
            'country_id' => $country->id,
            'partner_id' => $partner->id,
            'property_type_id' => $propertyType->id,
            'rate' => 3.5,
        ]);

        $details = $this->service->resolveRateWithDetails($country->id, $partner->id, $propertyType->id);

        $this->assertSame(3.5, $details['rate']);
        $this->assertNull($details['rate_id']);
        $this->assertSame('partner_override', $details['source']);
    }

    // ── Rate validation guard ────────────────────────────────────────────

    public function test_set_country_rate_rejects_a_negative_rate(): void
    {
        $country = Country::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->service->setCountryRate($country->id, -1);
    }

    public function test_set_country_rate_rejects_a_rate_above_100(): void
    {
        $country = Country::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->service->setCountryRate($country->id, 101);
    }

    public function test_set_country_rate_accepts_the_boundary_values_0_and_100(): void
    {
        $country = Country::factory()->create();

        $this->service->setCountryRate($country->id, 0);
        $this->assertDatabaseHas('commission_rates', ['country_id' => $country->id, 'property_type_id' => null, 'rate' => 0]);

        $this->service->setCountryRate($country->id, 100);
        $this->assertDatabaseHas('commission_rates', ['country_id' => $country->id, 'property_type_id' => null, 'rate' => 100]);
    }

    public function test_set_property_type_rate_rejects_a_negative_rate(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->service->setPropertyTypeRate($country->id, $propertyType->id, -5);
    }

    public function test_set_property_type_rate_rejects_a_rate_above_100(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->service->setPropertyTypeRate($country->id, $propertyType->id, 150);
    }

    public function test_set_property_type_rate_accepts_the_boundary_values_0_and_100(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create();

        $this->service->setPropertyTypeRate($country->id, $propertyType->id, 0);
        $this->assertDatabaseHas('commission_rates', ['country_id' => $country->id, 'property_type_id' => $propertyType->id, 'rate' => 0]);

        $this->service->setPropertyTypeRate($country->id, $propertyType->id, 100);
        $this->assertDatabaseHas('commission_rates', ['country_id' => $country->id, 'property_type_id' => $propertyType->id, 'rate' => 100]);
    }

    public function test_upsert_partner_override_rejects_a_negative_rate(): void
    {
        $country = Country::factory()->create();
        $partner = Partner::factory()->create();
        $propertyType = PropertyType::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->service->upsertPartnerOverride([
            'country_id' => $country->id,
            'partner_id' => $partner->id,
            'property_type_id' => $propertyType->id,
            'rate' => -2,
        ]);
    }

    public function test_upsert_partner_override_rejects_a_rate_above_100(): void
    {
        $country = Country::factory()->create();
        $partner = Partner::factory()->create();
        $propertyType = PropertyType::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->service->upsertPartnerOverride([
            'country_id' => $country->id,
            'partner_id' => $partner->id,
            'property_type_id' => $propertyType->id,
            'rate' => 200,
        ]);
    }

    public function test_upsert_partner_override_accepts_the_boundary_values_0_and_100(): void
    {
        $country = Country::factory()->create();
        $partner = Partner::factory()->create();
        $propertyType = PropertyType::factory()->create();

        $this->service->upsertPartnerOverride([
            'country_id' => $country->id,
            'partner_id' => $partner->id,
            'property_type_id' => $propertyType->id,
            'rate' => 0,
        ]);
        $this->assertDatabaseHas('commission_partner_overrides', ['country_id' => $country->id, 'partner_id' => $partner->id, 'property_type_id' => $propertyType->id, 'rate' => 0]);

        $this->service->upsertPartnerOverride([
            'country_id' => $country->id,
            'partner_id' => $partner->id,
            'property_type_id' => $propertyType->id,
            'rate' => 100,
        ]);
        $this->assertDatabaseHas('commission_partner_overrides', ['country_id' => $country->id, 'partner_id' => $partner->id, 'property_type_id' => $propertyType->id, 'rate' => 100]);
    }
}
