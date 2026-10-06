<?php

namespace Tests\Feature\Services;

use App\Enums\BookingStatus;
use App\Enums\CancellationInitiator;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PropertyCancellationPolicySource;
use App\Enums\WalletTransactionReferenceType;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyRule;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyType;
use App\Models\PropertyWallet;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCancellationWalletCreditTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BookingService::class);
    }

    private function enableMultiMode(): void
    {
        Setting::set('system_mode', 'multi');
    }

    /**
     * Build a confirmed, paid booking attached to a partner property, with a wallet,
     * a commission rate, and a cancellation policy that gives $refundPct% refund.
     *
     * @return array{booking: Booking, wallet: PropertyWallet, payment: Payment}
     */
    private function makeScenario(int $refundPct = 60, float $commissionRate = 10.0): array
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
            'cancellation_policy_source' => PropertyCancellationPolicySource::AdminDefault,
        ]);

        $wallet = PropertyWallet::factory()->create([
            'property_id' => $property->id,
            'balance' => 0,
            'currency_code' => 'USD',
        ]);

        // Cancellation policy: admin default, gives $refundPct% from 3+ days before check-in
        $policy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 3, 'refund_percentage' => $refundPct]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        $room = PropertyRoom::factory()->create(['property_id' => $property->id]);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'property_room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Cash,
            'base_amount' => 10000,
            'total_amount' => 10000,
            'commission_rate' => $commissionRate,
            'user_id' => null, // skip push notification
        ]);

        $user = User::factory()->create();

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'gateway_type' => PaymentGateway::Razorpay,
            'amount' => 10000,
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Success,
        ]);

        return compact('booking', 'wallet', 'payment');
    }

    public function test_credits_partner_wallet_with_retained_fee_minus_commission(): void
    {
        $this->enableMultiMode();

        // 60% refund → 40% retained (₹4,000), 10% commission → partner gets ₹3,600
        ['booking' => $booking, 'wallet' => $wallet] = $this->makeScenario(refundPct: 60, commissionRate: 10.0);

        $this->service->cancelBookingWithRefund($booking, 'test cancel', CancellationInitiator::Customer);

        $this->assertSame(3600.0, (float) $wallet->fresh()->balance);

        $this->assertDatabaseHas('property_wallet_transactions', [
            'property_wallet_id' => $wallet->id,
            'reference_type' => WalletTransactionReferenceType::CancellationRevenue->value,
            'reference_id' => $booking->id,
            'amount' => 3600.00,
        ]);
    }

    public function test_does_not_credit_wallet_when_refund_is_100_percent(): void
    {
        $this->enableMultiMode();

        // 100% refund → nothing retained → wallet untouched
        ['booking' => $booking, 'wallet' => $wallet] = $this->makeScenario(refundPct: 100);

        $this->service->cancelBookingWithRefund($booking, 'test cancel', CancellationInitiator::Customer);

        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
        $this->assertDatabaseMissing('property_wallet_transactions', [
            'property_wallet_id' => $wallet->id,
            'reference_type' => WalletTransactionReferenceType::CancellationRevenue->value,
        ]);
    }

    public function test_does_not_credit_wallet_in_single_mode(): void
    {
        // system_mode left at default ('single')

        ['booking' => $booking, 'wallet' => $wallet] = $this->makeScenario(refundPct: 60);

        $this->service->cancelBookingWithRefund($booking, 'test cancel', CancellationInitiator::Customer);

        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
    }

    public function test_does_not_credit_wallet_when_property_has_no_partner(): void
    {
        $this->enableMultiMode();

        ['booking' => $booking, 'wallet' => $wallet] = $this->makeScenario(refundPct: 60);

        // Remove partner from the property
        $booking->property->update(['partner_id' => null]);

        $this->service->cancelBookingWithRefund($booking->fresh(), 'test cancel', CancellationInitiator::Customer);

        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
    }

    public function test_partner_initiated_cancellation_does_not_credit_wallet(): void
    {
        $this->enableMultiMode();

        // Partner-initiated → forced 100% refund → retained = 0 → no credit
        ['booking' => $booking, 'wallet' => $wallet] = $this->makeScenario(refundPct: 60);

        $this->service->cancelBookingWithRefund($booking, 'partner cancelled', CancellationInitiator::Partner);

        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
    }

    public function test_section_6_formula_extracts_tax_before_applying_commission(): void
    {
        $this->enableMultiMode();

        // Setup: R=10,000 base, T=10% tax → total paid=11,000, 60% refund policy
        // Retained Room = 10,000 × (1 − 0.60) = 4,000
        // Retained Tax = 4,000 × 10% = 400
        // Refund = 11,000 − (4,000 + 400) = 6,600
        // Commission = 4,000 × 10% = 400
        // Wallet Credit = 4,000 − 400 = 3,600

        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
            'cancellation_policy_source' => PropertyCancellationPolicySource::AdminDefault,
        ]);

        $wallet = PropertyWallet::factory()->create([
            'property_id' => $property->id,
            'balance' => 0,
            'currency_code' => 'USD',
        ]);

        $policy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 3, 'refund_percentage' => 60]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        $room = PropertyRoom::factory()->create(['property_id' => $property->id]);
        $user = User::factory()->create();

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'property_room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Cash,
            'base_amount' => 10000,
            'tax_amount' => 1000,
            'total_amount' => 11000,
            'commission_rate' => 10.0,
            'user_id' => null,
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'gateway_type' => PaymentGateway::Razorpay,
            'amount' => 11000,
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Success,
        ]);

        $this->service->cancelBookingWithRefund($booking, 'test', CancellationInitiator::Customer);

        // Wallet should receive Room Distributable − commission = 3,600
        $this->assertSame(3600.0, (float) $wallet->fresh()->balance);

        // Audit trail must be written
        $fresh = $booking->fresh();
        $this->assertSame('v2', $fresh->formula_version);
        $this->assertNotNull($fresh->refund_inputs);
        $this->assertSame(400.0, (float) $fresh->refund_inputs['retained_tax']);
        $this->assertSame(4000.0, (float) $fresh->refund_inputs['retained_room']);
        $this->assertSame(400.0, (float) $fresh->refund_inputs['commission']);
        $this->assertSame(3600.0, (float) $fresh->refund_inputs['wallet_credit']);
    }

    /**
     * A promo discount must never reduce what's retained on cancellation — Retained
     * Room is always based on the original base_amount, matching the same rule for
     * normal (non-cancelled) bookings. discount_amount is non-zero here to prove
     * it's genuinely ignored, not just coincidentally zero.
     */
    public function test_retained_room_on_cancellation_ignores_discount_amount(): void
    {
        $this->enableMultiMode();

        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
            'cancellation_policy_source' => PropertyCancellationPolicySource::AdminDefault,
        ]);

        $wallet = PropertyWallet::factory()->create([
            'property_id' => $property->id,
            'balance' => 0,
            'currency_code' => 'USD',
        ]);

        $policy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 3, 'refund_percentage' => 60]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        $room = PropertyRoom::factory()->create(['property_id' => $property->id]);
        $user = User::factory()->create();

        // R=10,000, promo discount=1,000, no tax, 60% refund → customer paid 9,000×0.4... no:
        // Customer Paid = (R - D) = 9,000 (no tax in this scenario). Retained Room ignores D:
        // Retained Room = 10,000 × (1 - 0.60) = 4,000. Commission = 400. Wallet Credit = 3,600.
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'property_room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Cash,
            'base_amount' => 10000,
            'discount_amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 9000,
            'commission_rate' => 10.0,
            'user_id' => null,
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'gateway_type' => PaymentGateway::Razorpay,
            'amount' => 9000,
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Success,
        ]);

        $this->service->cancelBookingWithRefund($booking, 'test', CancellationInitiator::Customer);

        $this->assertSame(3600.0, (float) $wallet->fresh()->balance);
        $this->assertSame(4000.0, (float) $booking->fresh()->refund_inputs['retained_room']);
    }

    /**
     * A strict policy retaining more than a small deposit must not credit the
     * partner more than the platform actually collected — the theoretical
     * retained room is capped at what was actually paid.
     */
    public function test_retained_room_is_capped_at_what_was_actually_collected(): void
    {
        $this->enableMultiMode();

        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
            'cancellation_policy_source' => PropertyCancellationPolicySource::AdminDefault,
        ]);

        $wallet = PropertyWallet::factory()->create([
            'property_id' => $property->id,
            'balance' => 0,
            'currency_code' => 'USD',
        ]);

        // 20% refund → 80% retained — strict policy.
        $policy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 3, 'refund_percentage' => 20]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        $room = PropertyRoom::factory()->create(['property_id' => $property->id]);
        $user = User::factory()->create();

        // R=10,000, no tax/discount, only a 2,000 deposit collected.
        // Theoretical Retained Room = 10,000 × 0.80 = 8,000 — more than was ever paid.
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'property_room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Cash,
            'base_amount' => 10000,
            'tax_amount' => 0,
            'total_amount' => 10000,
            'commission_rate' => 10.0,
            'user_id' => null,
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'gateway_type' => PaymentGateway::Razorpay,
            'amount' => 2000,
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Success,
        ]);

        $this->service->cancelBookingWithRefund($booking, 'test', CancellationInitiator::Customer);

        // Nothing extra to refund — the whole deposit is retained.
        $refund = Refund::where('payment_id', Payment::first()->id)->first();
        $this->assertNull($refund);

        // Retained room is capped at the 2,000 collected — never the theoretical 8,000.
        $this->assertSame(2000.0, (float) $booking->fresh()->refund_inputs['retained_room']);

        // Commission = 2,000 × 10% = 200. Wallet Credit = 2,000 − 200 = 1,800.
        $this->assertSame(1800.0, (float) $wallet->fresh()->balance);
    }
}
