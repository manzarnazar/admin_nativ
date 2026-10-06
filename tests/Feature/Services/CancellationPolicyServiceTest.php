<?php

namespace Tests\Feature\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PropertyCancellationPolicySource;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyRule;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyType;
use App\Models\Setting;
use App\Models\User;
use App\Scopes\PartnerScope;
use App\Services\CancellationPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancellationPolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    private CancellationPolicyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CancellationPolicyService::class);
    }

    /**
     * Build a scenario: country, active property type, partner, property (with given source),
     * a matching room, a booking N days out, and both an admin and a partner cancellation policy.
     *
     * @return array{booking: Booking, adminPolicy: CancellationPolicy, partnerPolicy: CancellationPolicy, property: Property}
     */
    private function makeScenario(PropertyCancellationPolicySource $source, int $daysUntilCheckin = 5): array
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'partner_id' => $partner->id,
            'cancellation_policy_source' => $source,
        ]);

        $room = PropertyRoom::factory()->create(['property_id' => $property->id]);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'property_room_id' => $room->id,
            'check_in' => now()->addDays($daysUntilCheckin)->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
        ]);

        $adminPolicy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);

        $partnerPolicy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => null,
            'partner_id' => $partner->id,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);

        return compact('booking', 'adminPolicy', 'partnerPolicy', 'property');
    }

    public function test_uses_admin_default_policy_when_source_is_admin_default(): void
    {
        [
            'booking' => $booking,
            'adminPolicy' => $adminPolicy,
            'partnerPolicy' => $partnerPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault);

        // Admin policy gives 50% refund from 3+ days before check-in
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 3, 'refund_percentage' => 50]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        // Partner policy gives 100% — must NOT be used when source is admin_default
        CancellationPolicyRule::create(['cancellation_policy_id' => $partnerPolicy->id, 'days_before_checkin' => 3, 'refund_percentage' => 100]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $partnerPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        // Booking is 5 days out → matches the 3-day rule → 50% (admin)
        $this->assertSame(50, $this->service->calculateRefundPercentage($booking));
    }

    public function test_uses_partner_custom_policy_when_source_is_custom(): void
    {
        [
            'booking' => $booking,
            'adminPolicy' => $adminPolicy,
            'partnerPolicy' => $partnerPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::Custom);

        // Admin policy: 50% refund
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 3, 'refund_percentage' => 50]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        // Partner policy: 100% refund — must win when source is custom
        CancellationPolicyRule::create(['cancellation_policy_id' => $partnerPolicy->id, 'days_before_checkin' => 3, 'refund_percentage' => 100]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $partnerPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        $this->assertSame(100, $this->service->calculateRefundPercentage($booking));
    }

    public function test_falls_back_to_admin_policy_when_source_is_custom_but_partner_has_no_policy(): void
    {
        [
            'booking' => $booking,
            'adminPolicy' => $adminPolicy,
            'partnerPolicy' => $partnerPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::Custom);

        // Partner has not set up a policy yet
        $partnerPolicy->forceDelete();

        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 3, 'refund_percentage' => 75]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        $this->assertSame(75, $this->service->calculateRefundPercentage($booking));
    }

    public function test_returns_zero_when_no_policy_exists_at_all(): void
    {
        [
            'booking' => $booking,
            'adminPolicy' => $adminPolicy,
            'partnerPolicy' => $partnerPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault);

        $adminPolicy->forceDelete();
        $partnerPolicy->forceDelete();

        $this->assertSame(0, $this->service->calculateRefundPercentage($booking));
    }

    public function test_returns_zero_when_booking_is_past_checkin(): void
    {
        [
            'booking' => $booking,
            'adminPolicy' => $adminPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault, daysUntilCheckin: -2);

        // Even with a permissive rule, past check-in → 0
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 100]);

        $this->assertSame(0, $this->service->calculateRefundPercentage($booking));
    }

    public function test_picks_the_highest_matching_days_rule(): void
    {
        [
            'booking' => $booking,
            'adminPolicy' => $adminPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault, daysUntilCheckin: 7);

        // Three rules: 14-day (80%), 7-day (60%), 0-day fallback (0%)
        // Booking is exactly 7 days out → should match 7-day (60%), not 14-day (80%)
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 14, 'refund_percentage' => 80]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 7, 'refund_percentage' => 60]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        $this->assertSame(60, $this->service->calculateRefundPercentage($booking));
    }

    /**
     * Regression test: the admin-default policy lookup used to ignore the
     * property's own property_type_id and instead grab whichever active
     * property type happened to have the lowest ID — so a Villa property
     * could silently use the Hotel's cancellation policy. With two active
     * types configured differently, the property's own type must win.
     */
    public function test_admin_default_policy_uses_the_propertys_own_type_not_the_first_active_type(): void
    {
        $country = Country::factory()->create();

        // Created first, so it has the lower ID — the old bug would always pick this one.
        $hotelType = PropertyType::factory()->create(['is_active' => true]);
        $villaType = PropertyType::factory()->create(['is_active' => true]);

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $villaType->id,
            'cancellation_policy_source' => PropertyCancellationPolicySource::AdminDefault,
        ]);

        $room = PropertyRoom::factory()->create(['property_id' => $property->id]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'property_room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
        ]);

        $hotelPolicy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => $hotelType->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $hotelPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 80]);

        $villaPolicy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => $villaType->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $villaPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 20]);

        // Must resolve the Villa policy (20%), never the Hotel policy (80%).
        $this->assertSame(20, $this->service->calculateRefundPercentage($booking));
    }

    /**
     * R=1,000, no tax/discount, full payment of 1,000, 60% refund policy.
     * Retained Room = 1,000 × (1 − 0.60) = 400. Refund = 1,000 − 400 = 600.
     */
    public function test_calculate_refund_amount_reflects_retained_room_at_the_given_percentage(): void
    {
        $booking = Booking::factory()->create(['user_id' => null, 'base_amount' => 1000, 'tax_amount' => 0, 'discount_amount' => 0]);
        $user = User::factory()->create();

        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'gateway_type' => PaymentGateway::Razorpay,
            'amount' => 1000,
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Success,
        ]);

        $this->assertSame(600.0, $this->service->calculateRefundAmount($booking->fresh(), 60));
    }

    public function test_calculate_refund_amount_is_zero_when_percentage_is_zero(): void
    {
        $booking = Booking::factory()->create(['user_id' => null, 'base_amount' => 1000, 'tax_amount' => 0, 'discount_amount' => 0]);
        $user = User::factory()->create();

        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'gateway_type' => PaymentGateway::Razorpay,
            'amount' => 1000,
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Success,
        ]);

        $this->assertSame(0.0, $this->service->calculateRefundAmount($booking->fresh(), 0));
    }

    public function test_calculate_refund_amount_is_zero_when_no_successful_payment_exists(): void
    {
        $booking = Booking::factory()->create(['user_id' => null]);

        $this->assertSame(0.0, $this->service->calculateRefundAmount($booking, 60));
    }

    /**
     * A promo discount must never reduce Retained Room — it's always computed
     * from the original base_amount. discount_amount is non-zero here to prove
     * it's genuinely ignored.
     */
    public function test_calculate_refund_amount_ignores_discount_amount(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => null,
            'base_amount' => 1000,
            'discount_amount' => 300,
            'tax_amount' => 0,
        ]);
        $user = User::factory()->create();

        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'gateway_type' => PaymentGateway::Razorpay,
            'amount' => 700,
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Success,
        ]);

        // Retained Room = 1,000 × 0.40 = 400 (from the original base_amount, not 700).
        // Refund = 700 − 400 = 300.
        $this->assertSame(300.0, $this->service->calculateRefundAmount($booking->fresh(), 60));
    }

    /**
     * A strict policy retaining more than what was actually collected (a small
     * deposit) must not produce a negative refund — it floors at 0, and the
     * retained room for wallet-credit purposes caps at what was paid.
     */
    public function test_calculate_cancellation_breakdown_caps_retained_room_at_amount_paid(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => null,
            'base_amount' => 10000,
            'discount_amount' => 0,
            'tax_amount' => 0,
        ]);
        $user = User::factory()->create();

        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'gateway_type' => PaymentGateway::Razorpay,
            'amount' => 2000,
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Success,
        ]);

        // Theoretical Retained Room = 10,000 × 0.80 = 8,000 — more than the 2,000 collected.
        $breakdown = $this->service->calculateCancellationBreakdown($booking->fresh(), 20);

        $this->assertSame(0.0, $breakdown['refund_amount']);
        $this->assertSame(2000.0, $breakdown['retained_room']);
    }

    // --- buildSnapshot tests ---

    public function test_build_snapshot_structure_contains_raw_rules_and_display(): void
    {
        [
            'property' => $property,
            'adminPolicy' => $adminPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault);

        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 7, 'refund_percentage' => 100]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        $snapshot = $this->service->buildSnapshot($property, now()->addDays(10)->toDateString());

        $this->assertArrayHasKey('cancellation_cutoff_time', $snapshot);
        $this->assertArrayHasKey('rules', $snapshot);
        $this->assertArrayHasKey('display', $snapshot);

        // Raw rules carry days_before_checkin and refund_percentage (for calculation)
        $this->assertCount(2, $snapshot['rules']);
        $this->assertArrayHasKey('days_before_checkin', $snapshot['rules'][0]);
        $this->assertArrayHasKey('refund_percentage', $snapshot['rules'][0]);

        // Display carries the human-readable format (for API responses)
        $this->assertArrayHasKey('free_cancellation_until', $snapshot['display']);
        $this->assertArrayHasKey('cancellation_cutoff_time', $snapshot['display']);
        $this->assertArrayHasKey('rules', $snapshot['display']);
    }

    public function test_build_snapshot_uses_admin_policy_for_admin_default_source(): void
    {
        [
            'property' => $property,
            'adminPolicy' => $adminPolicy,
            'partnerPolicy' => $partnerPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault);

        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 50]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $partnerPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 100]);

        $snapshot = $this->service->buildSnapshot($property, now()->addDays(5)->toDateString());

        // Raw rules must reflect admin's 50%, not the partner's 100%
        $this->assertSame(50, $snapshot['rules'][0]['refund_percentage']);
    }

    public function test_build_snapshot_uses_custom_policy_for_custom_source(): void
    {
        [
            'property' => $property,
            'adminPolicy' => $adminPolicy,
            'partnerPolicy' => $partnerPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::Custom);

        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 50]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $partnerPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 100]);

        $snapshot = $this->service->buildSnapshot($property, now()->addDays(5)->toDateString());

        // Raw rules must reflect the partner's 100%
        $this->assertSame(100, $snapshot['rules'][0]['refund_percentage']);
    }

    public function test_build_snapshot_returns_empty_structure_when_no_policy_exists(): void
    {
        [
            'property' => $property,
            'adminPolicy' => $adminPolicy,
            'partnerPolicy' => $partnerPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault);

        $adminPolicy->forceDelete();
        $partnerPolicy->forceDelete();

        $snapshot = $this->service->buildSnapshot($property, now()->addDays(5)->toDateString());

        $this->assertNull($snapshot['cancellation_cutoff_time']);
        $this->assertEmpty($snapshot['rules']);
        $this->assertEmpty($snapshot['display']['rules']);
    }

    // --- Snapshot integrity: refund calculation ---

    public function test_calculates_refund_percentage_from_snapshot_when_present(): void
    {
        [
            'booking' => $booking,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault, daysUntilCheckin: 5);

        // Snapshot says 75% for 3+ days — store it directly on the booking
        $booking->update([
            'cancellation_policy_snapshot' => [
                'cancellation_cutoff_time' => '23:59:59',
                'rules' => [
                    ['days_before_checkin' => 3, 'refund_percentage' => 75],
                    ['days_before_checkin' => 0, 'refund_percentage' => 0],
                ],
                'display' => [],
            ],
        ]);

        $this->assertSame(75, $this->service->calculateRefundPercentage($booking->fresh()));
    }

    /**
     * Core guarantee: admin changes rules after booking → customer still gets the refund
     * that was promised at booking time (from the snapshot), not the new live rule.
     */
    public function test_snapshot_locks_in_refund_rules_even_after_admin_changes_them(): void
    {
        [
            'booking' => $booking,
            'adminPolicy' => $adminPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault, daysUntilCheckin: 5);

        $rule = CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 3, 'refund_percentage' => 75]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        // Snapshot captured 75% at booking time
        $booking->update([
            'cancellation_policy_snapshot' => [
                'cancellation_cutoff_time' => '23:59:59',
                'rules' => [
                    ['days_before_checkin' => 3, 'refund_percentage' => 75],
                    ['days_before_checkin' => 0, 'refund_percentage' => 0],
                ],
                'display' => [],
            ],
        ]);

        // Admin reduces refund to 10% after booking
        $rule->update(['refund_percentage' => 10]);

        // Must still return 75% from the snapshot
        $this->assertSame(75, $this->service->calculateRefundPercentage($booking->fresh()));
    }

    /**
     * Old bookings without a snapshot fall back to the live policy (backwards-compatible path).
     */
    public function test_falls_back_to_live_policy_when_booking_has_no_snapshot(): void
    {
        [
            'booking' => $booking,
            'adminPolicy' => $adminPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault, daysUntilCheckin: 5);

        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 3, 'refund_percentage' => 60]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        // Factory-created booking has no snapshot
        $this->assertNull($booking->cancellation_policy_snapshot);

        $this->assertSame(60, $this->service->calculateRefundPercentage($booking->fresh()));
    }

    // --- getSummary ---

    public function test_get_summary_returns_display_formatted_rules_for_a_property(): void
    {
        [
            'property' => $property,
            'adminPolicy' => $adminPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault);

        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 7, 'refund_percentage' => 100]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        $summary = $this->service->getSummary($property, now()->addDays(10)->toDateString());

        $this->assertArrayHasKey('free_cancellation_until', $summary);
        $this->assertArrayHasKey('cancellation_cutoff_time', $summary);
        $this->assertArrayHasKey('rules', $summary);
        // 7-day 100% rule is within the 10-day window → free_cancellation_until is set
        $this->assertNotNull($summary['free_cancellation_until']);
        $this->assertNotEmpty($summary['rules']);
    }

    public function test_get_summary_returns_empty_when_no_policy_configured(): void
    {
        [
            'property' => $property,
            'adminPolicy' => $adminPolicy,
            'partnerPolicy' => $partnerPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault);

        $adminPolicy->forceDelete();
        $partnerPolicy->forceDelete();

        $summary = $this->service->getSummary($property, now()->addDays(5)->toDateString());

        $this->assertNull($summary['free_cancellation_until']);
        $this->assertNull($summary['cancellation_cutoff_time']);
        $this->assertEmpty($summary['rules']);
    }

    // --- Cutoff time timezone awareness ---

    /**
     * When the current time in the property's timezone is BEFORE the cutoff,
     * rules for today's calendar date are still applicable in the display.
     * The 0-day rule (days_before_checkin=0) deadline is today → should appear.
     */
    public function test_display_includes_rules_still_applicable_before_cutoff(): void
    {
        [
            'property' => $property,
            'adminPolicy' => $adminPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault);

        // Cutoff is 23:59 so it's never past cutoff during this test
        $adminPolicy->update(['cancellation_cutoff_time' => '23:59:00']);

        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 3, 'refund_percentage' => 50]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        // Check-in is today — only the 0-day rule deadline (today) is still >= today
        $snapshot = $this->service->buildSnapshot($property, now()->toDateString());

        // The 0-day fallback rule must appear (its deadline = today = today → applicable)
        $displayRules = $snapshot['display']['rules'];
        $this->assertNotEmpty($displayRules);
    }

    /**
     * When the current time is PAST the cutoff, the display shifts forward by one day —
     * matching calculateRefundPercentage()'s effective-date logic.
     * A rule whose deadline is today must NOT appear (today < effectiveToday = tomorrow).
     */
    public function test_display_shifts_forward_when_past_cutoff(): void
    {
        [
            'property' => $property,
            'adminPolicy' => $adminPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault);

        // Cutoff is 00:01 — always past cutoff by the time the test runs
        $adminPolicy->update(['cancellation_cutoff_time' => '00:01:00']);

        // Only a 0-day rule — its deadline is today's date.
        // After cutoff shift, effectiveToday = tomorrow → deadline < effectiveToday → not applicable.
        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        // Check-in is today — after cutoff shift there are no applicable rules
        $snapshot = $this->service->buildSnapshot($property, now()->toDateString());

        // No applicable rules → display shows the "Non Refundable / window passed" fallback
        $displayRules = $snapshot['display']['rules'];
        $this->assertCount(1, $displayRules);
        $this->assertSame(0, $displayRules[0]['refund_percentage']);
        $this->assertSame('Non Refundable', $displayRules[0]['label']);
    }

    // ── saveRule() — refund_percentage validation guard ─────────────────────

    public function test_save_rule_rejects_a_negative_refund_percentage(): void
    {
        $policy = CancellationPolicy::create([
            'country_id' => Country::factory()->create()->id,
            'property_type_id' => PropertyType::factory()->create()->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '14:00:00',
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->saveRule($policy, ['days_before_checkin' => 3, 'refund_percentage' => -10]);
    }

    public function test_save_rule_rejects_a_refund_percentage_above_100(): void
    {
        $policy = CancellationPolicy::create([
            'country_id' => Country::factory()->create()->id,
            'property_type_id' => PropertyType::factory()->create()->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '14:00:00',
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->saveRule($policy, ['days_before_checkin' => 3, 'refund_percentage' => 150]);
    }

    public function test_save_rule_accepts_a_valid_refund_percentage(): void
    {
        $policy = CancellationPolicy::create([
            'country_id' => Country::factory()->create()->id,
            'property_type_id' => PropertyType::factory()->create()->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '14:00:00',
            'is_active' => true,
        ]);

        $saved = $this->service->saveRule($policy, ['days_before_checkin' => 3, 'refund_percentage' => 50]);

        $this->assertTrue($saved);
        $this->assertDatabaseHas('cancellation_policy_rules', [
            'cancellation_policy_id' => $policy->id,
            'days_before_checkin' => 3,
            'refund_percentage' => 50,
        ]);
    }

    // ── PartnerScope regression ──────────────────────────────────────────────
    // CancellationPolicy has a global PartnerScope that silently adds
    // `partner_id = <the authenticated partner>` to every query while a partner
    // is logged in. None of the tests above catch this: without actingAs(), the
    // scope never activates, so a query for `partner_id IS NULL` (the
    // admin-default policy) would look correct in every test above yet still be
    // completely broken for a real logged-in partner — exactly what happened live.

    public function test_admin_default_policy_resolves_when_authenticated_as_the_propertys_own_partner(): void
    {
        [
            'property' => $property,
            'adminPolicy' => $adminPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::AdminDefault);

        CancellationPolicyRule::create(['cancellation_policy_id' => $adminPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 65]);

        // PartnerScope only activates in multi-mode — matches how the live bug actually occurred.
        Setting::set('system_mode', 'multi');
        $this->actingAs($property->partner->user);

        $resolved = $this->service->resolvePolicyForRefund($property->fresh());

        $this->assertNotNull($resolved, 'PartnerScope must not hide the admin-default policy (partner_id IS NULL) from a logged-in partner.');
        $this->assertSame($adminPolicy->id, $resolved->id);
    }

    public function test_get_admin_default_policy_does_not_create_a_duplicate_when_authenticated_as_a_partner(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();

        $existing = CancellationPolicy::create([
            'country_id' => $country->id,
            'partner_id' => null,
            'property_type_id' => $propertyType->id,
            'cancellation_cutoff_time' => '14:00:00',
            'is_active' => true,
        ]);

        Setting::set('system_mode', 'multi');
        $this->actingAs($partner->user);

        // This exact call (via getAdminDefaultCancellationPolicy()) is what ran on
        // every page load of the partner property wizard's cancellation step —
        // it used to create a brand new duplicate policy every single time.
        $resolved = $this->service->getAdminDefaultPolicy($country->id, $propertyType->id);

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame(1, CancellationPolicy::withoutGlobalScope(PartnerScope::class)
            ->where('country_id', $country->id)
            ->whereNull('partner_id')
            ->where('property_type_id', $propertyType->id)
            ->count());
    }

    public function test_partner_custom_policy_still_resolves_correctly_when_authenticated_as_that_partner(): void
    {
        [
            'property' => $property,
            'partnerPolicy' => $partnerPolicy,
        ] = $this->makeScenario(PropertyCancellationPolicySource::Custom);

        CancellationPolicyRule::create(['cancellation_policy_id' => $partnerPolicy->id, 'days_before_checkin' => 0, 'refund_percentage' => 90]);

        Setting::set('system_mode', 'multi');
        $this->actingAs($property->partner->user);

        $resolved = $this->service->resolvePolicyForRefund($property->fresh());

        $this->assertNotNull($resolved);
        $this->assertSame($partnerPolicy->id, $resolved->id);
    }
}
