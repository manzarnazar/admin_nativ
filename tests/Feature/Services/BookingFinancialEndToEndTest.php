<?php

namespace Tests\Feature\Services;

use App\Enums\BookingStatus;
use App\Enums\CancellationInitiator;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PropertyCancellationPolicySource;
use App\Enums\TaxStatus;
use App\Enums\TaxType;
use App\Enums\WalletTransactionReferenceType;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyRule;
use App\Models\CommissionPartnerOverride;
use App\Models\CommissionRate;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyType;
use App\Models\PropertyWallet;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end coverage of the booking financial engine: commission resolution,
 * booking creation, and cancellation/refund/wallet-credit, exercised together
 * through the real BookingService rather than each formula in isolation.
 * Complements (does not duplicate) CommissionServiceTest, CancellationPolicyServiceTest,
 * BookingCommissionSnapshotTest, and BookingCancellationWalletCreditTest.
 */
class BookingFinancialEndToEndTest extends TestCase
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
     * Full scaffold used by Groups 2, 6, and 7: creates a real booking through
     * BookingService::createBooking() (admin/cash, immediately confirmed + paid)
     * against a configurable commission tier, tax rate, and cancellation policy.
     *
     * @return array{country: Country, propertyType: PropertyType, partner: ?Partner, property: Property, wallet: PropertyWallet, booking: Booking, payment: ?Payment}
     */
    private function scaffold(array $opts = []): array
    {
        $opts = array_merge([
            'basePricePerNight' => 1000.0,
            'nights' => 2,
            'bookedRooms' => 1,
            'checkInDays' => 5,
            'taxRatePercent' => null,
            'commissionOverrideRate' => null,
            'countryDefaultRate' => null,
            'propertyTypeRate' => null,
            'policyRules' => [[3, 50], [0, 0]],
            'withPartner' => true,
            'multiMode' => true,
        ], $opts);

        if ($opts['multiMode']) {
            $this->enableMultiMode();
        }

        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = $opts['withPartner'] ? Partner::factory()->create() : null;

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner?->id,
            'cancellation_policy_source' => PropertyCancellationPolicySource::AdminDefault,
        ]);

        $wallet = PropertyWallet::factory()->create([
            'property_id' => $property->id,
            'balance' => 0,
            'currency_code' => 'USD',
        ]);

        if ($opts['taxRatePercent'] !== null) {
            $tax = Tax::create([
                'country_id' => $country->id,
                'name' => 'VAT',
                'type' => TaxType::Percentage,
                'value' => $opts['taxRatePercent'],
                'status' => TaxStatus::Active,
            ]);
            $tax->propertyTypes()->attach($propertyType->id);
        }

        if ($opts['countryDefaultRate'] !== null) {
            CommissionRate::factory()->create([
                'country_id' => $country->id,
                'property_type_id' => null,
                'rate' => $opts['countryDefaultRate'],
            ]);
        }

        if ($opts['propertyTypeRate'] !== null) {
            CommissionRate::factory()->create([
                'country_id' => $country->id,
                'property_type_id' => $propertyType->id,
                'rate' => $opts['propertyTypeRate'],
            ]);
        }

        if ($opts['commissionOverrideRate'] !== null && $partner) {
            CommissionPartnerOverride::factory()->create([
                'country_id' => $country->id,
                'partner_id' => $partner->id,
                'property_type_id' => $propertyType->id,
                'rate' => $opts['commissionOverrideRate'],
            ]);
        }

        $policy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);
        foreach ($opts['policyRules'] as [$days, $pct]) {
            CancellationPolicyRule::create([
                'cancellation_policy_id' => $policy->id,
                'days_before_checkin' => $days,
                'refund_percentage' => $pct,
            ]);
        }

        $room = PropertyRoom::factory()->create([
            'property_id' => $property->id,
            'base_price_per_night' => $opts['basePricePerNight'],
        ]);

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $checkIn = now()->addDays($opts['checkInDays']);
        $checkOut = $checkIn->clone()->addDays($opts['nights']);

        $booking = $this->service->createBooking($property, [
            'property_room_id' => $room->id,
            'user_id' => $customer->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'adults' => 1,
            'children' => 0,
            'booked_rooms' => $opts['bookedRooms'],
            'payment_method' => PaymentMethod::Cash->value,
            'payment_status' => PaymentStatus::Paid->value,
        ], $admin);

        // createBooking()'s admin/cash-payment path always records a Manual-gateway
        // Payment (money the property holds directly, per CommissionService::
        // resolveOnlineCollectedAmount()) — but multi-mode bookings are only ever
        // created by a guest paying online, so simulate that here instead.
        Payment::where('booking_id', $booking->id)->update(['gateway_type' => PaymentGateway::Razorpay]);
        $payment = Payment::where('booking_id', $booking->id)->first();

        return compact('country', 'propertyType', 'partner', 'property', 'wallet', 'booking', 'payment');
    }

    // ── Group 1: Commission hierarchy resolved live at booking creation ────

    public function test_commission_hierarchy_uses_country_default_when_nothing_else_configured(): void
    {
        $this->enableMultiMode();

        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();
        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
        ]);
        $room = PropertyRoom::factory()->create(['property_id' => $property->id, 'base_price_per_night' => 1000]);
        $customer = User::factory()->create();

        CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => null, 'rate' => 8]);

        $booking = $this->service->createBooking($property, [
            'property_room_id' => $room->id,
            'user_id' => $customer->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'booked_rooms' => 1,
        ]);

        $this->assertSame(2000.0, (float) $booking->base_amount);
        $this->assertSame(8.0, (float) $booking->commission_rate);
        $this->assertSame('country_default', $booking->commission_source);
        $this->assertSame(160.0, (float) $booking->commission_amount);
    }

    public function test_commission_hierarchy_prefers_property_type_rate_over_country_default(): void
    {
        $this->enableMultiMode();

        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();
        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
        ]);
        $room = PropertyRoom::factory()->create(['property_id' => $property->id, 'base_price_per_night' => 1000]);
        $customer = User::factory()->create();

        CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => null, 'rate' => 8]);
        CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => $propertyType->id, 'rate' => 12]);

        $booking = $this->service->createBooking($property, [
            'property_room_id' => $room->id,
            'user_id' => $customer->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'booked_rooms' => 1,
        ]);

        $this->assertSame(12.0, (float) $booking->commission_rate);
        $this->assertSame('country_type_override', $booking->commission_source);
        $this->assertSame(240.0, (float) $booking->commission_amount);
    }

    public function test_commission_hierarchy_prefers_partner_override_over_everything(): void
    {
        $this->enableMultiMode();

        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();
        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
        ]);
        $room = PropertyRoom::factory()->create(['property_id' => $property->id, 'base_price_per_night' => 1000]);
        $customer = User::factory()->create();

        CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => null, 'rate' => 8]);
        CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => $propertyType->id, 'rate' => 12]);
        CommissionPartnerOverride::factory()->create([
            'country_id' => $country->id,
            'partner_id' => $partner->id,
            'property_type_id' => $propertyType->id,
            'rate' => 20,
        ]);

        $booking = $this->service->createBooking($property, [
            'property_room_id' => $room->id,
            'user_id' => $customer->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'booked_rooms' => 1,
        ]);

        $this->assertSame(20.0, (float) $booking->commission_rate);
        $this->assertSame('partner_override', $booking->commission_source);
        $this->assertSame(400.0, (float) $booking->commission_amount);
    }

    // ── Group 2: Full-payment lifecycle across refund windows ──────────────

    private function group2Scaffold(int $checkInDays): array
    {
        return $this->scaffold([
            'basePricePerNight' => 1000.0,
            'taxRatePercent' => 10,
            'commissionOverrideRate' => 20,
            'policyRules' => [[7, 100], [3, 50], [0, 0]],
            'checkInDays' => $checkInDays,
        ]);
    }

    public function test_full_payment_customer_cancels_in_100_percent_refund_window(): void
    {
        ['booking' => $booking, 'wallet' => $wallet, 'payment' => $payment] = $this->group2Scaffold(checkInDays: 10);

        $this->service->cancelBookingWithRefund($booking, 'changed plans', CancellationInitiator::Customer);

        $refund = Refund::where('payment_id', $payment->id)->latest()->first();
        $this->assertSame(2200.0, (float) $refund->amount);
        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
        $this->assertNull($booking->fresh()->formula_version);
        $this->assertDatabaseMissing('property_wallet_transactions', ['reference_id' => $booking->id]);
    }

    public function test_full_payment_customer_cancels_in_partial_refund_window(): void
    {
        ['booking' => $booking, 'wallet' => $wallet, 'payment' => $payment] = $this->group2Scaffold(checkInDays: 5);

        $this->service->cancelBookingWithRefund($booking, 'change of plans', CancellationInitiator::Customer);

        $refund = Refund::where('payment_id', $payment->id)->latest()->first();
        $fresh = $booking->fresh();

        $this->assertSame(1100.0, (float) $refund->amount);
        $this->assertSame(1000.0, (float) $fresh->refund_inputs['retained_room']);
        $this->assertSame(100.0, (float) $fresh->refund_inputs['retained_tax']);
        $this->assertSame(200.0, (float) $fresh->refund_inputs['commission']);
        $this->assertSame(800.0, (float) $fresh->refund_inputs['wallet_credit']);
        $this->assertSame(800.0, (float) $wallet->fresh()->balance);

        // Money-conservation invariant: nothing collected from the guest disappears.
        $this->assertSame(2200.0, $refund->amount + $fresh->refund_inputs['retained_tax'] + $fresh->refund_inputs['commission'] + $fresh->refund_inputs['wallet_credit']);
    }

    public function test_full_payment_customer_cancels_in_no_refund_window(): void
    {
        ['booking' => $booking, 'wallet' => $wallet, 'payment' => $payment] = $this->group2Scaffold(checkInDays: 1);

        $this->service->cancelBookingWithRefund($booking, 'no-show risk', CancellationInitiator::Customer);

        $this->assertNull(Refund::where('payment_id', $payment->id)->first());
        $fresh = $booking->fresh();
        $this->assertSame(2000.0, (float) $fresh->refund_inputs['retained_room']);
        $this->assertSame(200.0, (float) $fresh->refund_inputs['retained_tax']);
        $this->assertSame(400.0, (float) $fresh->refund_inputs['commission']);
        $this->assertSame(1600.0, (float) $fresh->refund_inputs['wallet_credit']);
        $this->assertSame(1600.0, (float) $wallet->fresh()->balance);
    }

    public function test_partner_cancellation_still_gives_100_percent_refund_in_no_refund_window(): void
    {
        ['booking' => $booking, 'wallet' => $wallet, 'payment' => $payment] = $this->group2Scaffold(checkInDays: 1);

        $this->service->cancelBookingWithRefund($booking, 'property unavailable', CancellationInitiator::Partner);

        $refund = Refund::where('payment_id', $payment->id)->latest()->first();
        $this->assertSame(2200.0, (float) $refund->amount);
        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
        $this->assertDatabaseMissing('property_wallet_transactions', ['reference_id' => $booking->id]);
    }

    public function test_admin_cancellation_follows_the_same_policy_math_as_customer(): void
    {
        ['booking' => $booking, 'wallet' => $wallet, 'payment' => $payment] = $this->group2Scaffold(checkInDays: 5);

        $this->service->cancelBookingWithRefund($booking, 'admin cancelled on guest request', CancellationInitiator::Admin);

        $refund = Refund::where('payment_id', $payment->id)->latest()->first();
        $this->assertSame(1100.0, (float) $refund->amount);
        $this->assertSame(800.0, (float) $wallet->fresh()->balance);
    }

    // ── Group 3: Discount interacting with tax and with a deposit ──────────

    public function test_discount_is_ignored_by_retained_room_even_with_tax_present(): void
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
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0, 'currency_code' => 'USD']);

        $policy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 5, 'refund_percentage' => 40]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        $room = PropertyRoom::factory()->create(['property_id' => $property->id]);
        $user = User::factory()->create();

        // R=10,000, promo discount=2,000, tax=10% of (R-D)=800 → paid in full = 8,800.
        // Retained Room must still use the undiscounted R: 10,000 × (1-0.40) = 6,000.
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'property_room_id' => $room->id,
            'check_in' => now()->addDays(7)->toDateString(),
            'check_out' => now()->addDays(9)->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Cash,
            'base_amount' => 10000,
            'discount_amount' => 2000,
            'tax_amount' => 800,
            'total_amount' => 8800,
            'commission_rate' => 15.0,
            'user_id' => null,
        ]);
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'gateway_type' => PaymentGateway::Razorpay,
            'amount' => 8800,
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Success,
        ]);

        $this->service->cancelBookingWithRefund($booking, 'test', CancellationInitiator::Customer);

        $fresh = $booking->fresh();
        $this->assertSame(6000.0, (float) $fresh->refund_inputs['retained_room']);
        $this->assertSame(600.0, (float) $fresh->refund_inputs['retained_tax']);
        $this->assertSame(900.0, (float) $fresh->refund_inputs['commission']);
        $this->assertSame(5100.0, (float) $fresh->refund_inputs['wallet_credit']);
        $this->assertSame(2200.0, (float) Refund::where('payment_id', $payment->id)->first()->amount);
        $this->assertSame(5100.0, (float) $wallet->fresh()->balance);
    }

    public function test_discount_is_ignored_by_retained_room_when_only_a_deposit_was_collected(): void
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
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0, 'currency_code' => 'USD']);

        $policy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 5, 'refund_percentage' => 40]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        $room = PropertyRoom::factory()->create(['property_id' => $property->id]);
        $user = User::factory()->create();

        // Same R/discount/tax as above, but only a 3,000 deposit was ever collected —
        // far less than the 6,600 theoretical retained room+tax, so it gets capped.
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'property_room_id' => $room->id,
            'check_in' => now()->addDays(7)->toDateString(),
            'check_out' => now()->addDays(9)->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Cash,
            'base_amount' => 10000,
            'discount_amount' => 2000,
            'tax_amount' => 800,
            'total_amount' => 8800,
            'commission_rate' => 15.0,
            'user_id' => null,
        ]);
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'gateway_type' => PaymentGateway::Razorpay,
            'amount' => 3000,
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Success,
        ]);

        $this->service->cancelBookingWithRefund($booking, 'test', CancellationInitiator::Customer);

        $fresh = $booking->fresh();
        $this->assertNull(Refund::where('payment_id', $payment->id)->first());
        $this->assertSame(2727.27, (float) $fresh->refund_inputs['retained_room']);
        $this->assertSame(272.73, (float) $fresh->refund_inputs['retained_tax']);
        $this->assertSame(409.09, (float) $fresh->refund_inputs['commission']);
        $this->assertSame(2318.18, (float) $fresh->refund_inputs['wallet_credit']);
        $this->assertSame(2318.18, (float) $wallet->fresh()->balance);
    }

    // ── Group 4: Partial / deposit payment ──────────────────────────────────

    private function makeDepositScenario(float $paymentAmount): array
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
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0, 'currency_code' => 'USD']);

        $policy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 5, 'refund_percentage' => 30]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        $room = PropertyRoom::factory()->create(['property_id' => $property->id]);
        $user = User::factory()->create();

        // R=5,000, tax=10% (=500), commission=10%, 30%-refund policy (70% retained).
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'property_room_id' => $room->id,
            'check_in' => now()->addDays(7)->toDateString(),
            'check_out' => now()->addDays(9)->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Cash,
            'base_amount' => 5000,
            'tax_amount' => 500,
            'total_amount' => 5500,
            'commission_rate' => 10.0,
            'user_id' => null,
        ]);
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'gateway_type' => PaymentGateway::Razorpay,
            'amount' => $paymentAmount,
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Success,
        ]);

        return compact('booking', 'wallet', 'payment');
    }

    public function test_deposit_below_theoretical_retained_amount_is_capped(): void
    {
        ['booking' => $booking, 'wallet' => $wallet, 'payment' => $payment] = $this->makeDepositScenario(paymentAmount: 1200);

        $this->service->cancelBookingWithRefund($booking, 'test', CancellationInitiator::Customer);

        $fresh = $booking->fresh();
        $this->assertNull(Refund::where('payment_id', $payment->id)->first());
        $this->assertSame(1090.91, (float) $fresh->refund_inputs['retained_room']);
        $this->assertSame(109.09, (float) $fresh->refund_inputs['retained_tax']);
        $this->assertSame(109.09, (float) $fresh->refund_inputs['commission']);
        $this->assertSame(981.82, (float) $fresh->refund_inputs['wallet_credit']);
        $this->assertSame(981.82, (float) $wallet->fresh()->balance);
    }

    public function test_deposit_above_theoretical_retained_amount_is_not_capped(): void
    {
        ['booking' => $booking, 'wallet' => $wallet, 'payment' => $payment] = $this->makeDepositScenario(paymentAmount: 4000);

        $this->service->cancelBookingWithRefund($booking, 'test', CancellationInitiator::Customer);

        $refund = Refund::where('payment_id', $payment->id)->latest()->first();
        $fresh = $booking->fresh();
        $this->assertSame(150.0, (float) $refund->amount);
        $this->assertSame(3500.0, (float) $fresh->refund_inputs['retained_room']);
        $this->assertSame(350.0, (float) $fresh->refund_inputs['retained_tax']);
        $this->assertSame(350.0, (float) $fresh->refund_inputs['commission']);
        $this->assertSame(3150.0, (float) $fresh->refund_inputs['wallet_credit']);
        $this->assertSame(3150.0, (float) $wallet->fresh()->balance);
    }

    public function test_partner_cancellation_of_a_deposit_booking_refunds_the_deposit_not_the_total(): void
    {
        ['booking' => $booking, 'wallet' => $wallet, 'payment' => $payment] = $this->makeDepositScenario(paymentAmount: 4000);

        $this->service->cancelBookingWithRefund($booking, 'partner cancelled', CancellationInitiator::Partner);

        $refund = Refund::where('payment_id', $payment->id)->latest()->first();
        $this->assertSame(4000.0, (float) $refund->amount);
        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
        $this->assertDatabaseMissing('property_wallet_transactions', ['reference_id' => $booking->id]);
    }

    // ── Group 5: Wallet-credit trigger paths + cross-path idempotency ──────

    private function makeSimpleWalletBooking(string $checkIn, string $checkOut, BookingStatus $status): array
    {
        $this->enableMultiMode();

        $property = Property::factory()->create();
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0, 'currency_code' => 'USD']);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => $status,
            'payment_status' => PaymentStatus::Paid,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'user_id' => null,
        ]);

        return compact('booking', 'wallet');
    }

    public function test_manual_check_in_credits_wallet_and_stamps_wallet_credited_at(): void
    {
        ['booking' => $booking, 'wallet' => $wallet] = $this->makeSimpleWalletBooking(
            now()->toDateString(),
            now()->addDay()->toDateString(),
            BookingStatus::Confirmed,
        );

        $this->service->checkIn($booking->fresh());

        $this->assertSame(900.0, (float) $wallet->fresh()->balance);
        $this->assertNotNull($booking->fresh()->wallet_credited_at);
    }

    public function test_cron_credits_wallet_for_a_passed_checkin_that_was_never_manually_checked_in(): void
    {
        ['booking' => $booking, 'wallet' => $wallet] = $this->makeSimpleWalletBooking(
            now()->subDay()->toDateString(),
            now()->addDay()->toDateString(),
            BookingStatus::Confirmed,
        );

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        $this->assertSame(900.0, (float) $wallet->fresh()->balance);
        $this->assertDatabaseHas('property_wallet_transactions', [
            'property_wallet_id' => $wallet->id,
            'reference_type' => WalletTransactionReferenceType::BookingRevenue->value,
            'reference_id' => $booking->id,
            'amount' => 900.00,
        ]);
    }

    public function test_cron_does_not_double_credit_a_booking_already_credited_via_manual_check_in(): void
    {
        ['booking' => $booking, 'wallet' => $wallet] = $this->makeSimpleWalletBooking(
            now()->toDateString(),
            now()->addDay()->toDateString(),
            BookingStatus::Confirmed,
        );

        $this->service->checkIn($booking->fresh());
        $this->assertSame(900.0, (float) $wallet->fresh()->balance);

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        $this->assertSame(900.0, (float) $wallet->fresh()->balance);
        $this->assertDatabaseCount('property_wallet_transactions', 1);
    }

    public function test_cron_does_not_re_credit_a_cancelled_booking_that_already_received_a_cancellation_wallet_credit(): void
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
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0, 'currency_code' => 'USD']);

        $policy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 3, 'refund_percentage' => 50]);
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
            'total_amount' => 10000,
            'commission_rate' => 10.0,
            'user_id' => null,
        ]);
        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'gateway_type' => PaymentGateway::Razorpay,
            'amount' => 10000,
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Success,
        ]);

        $this->service->cancelBookingWithRefund($booking, 'test', CancellationInitiator::Customer);
        $this->assertSame(4500.0, (float) $wallet->fresh()->balance);

        // Cancelled status alone excludes it from the cron's query — belt-and-suspenders
        // alongside wallet_credited_at. Run it anyway to prove the balance never moves.
        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        $this->assertSame(4500.0, (float) $wallet->fresh()->balance);
        $this->assertDatabaseCount('property_wallet_transactions', 1);
    }

    // ── Group 5b: Check-in credit correctly nets against what was actually
    // collected online — not the full base_amount — for partial/Pay-at-Property ──

    public function test_check_in_credits_only_the_online_collected_share_for_a_partial_payment_booking(): void
    {
        $this->enableMultiMode();

        $property = Property::factory()->create();
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0, 'currency_code' => 'USD']);
        $user = User::factory()->create();

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Partial,
            'base_amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'commission_amount' => 100,
            'user_id' => null,
        ]);
        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'gateway_type' => PaymentGateway::Razorpay,
            'amount' => 50,
            'currency' => 'USD',
            'payment_type' => 'partial',
            'status' => PaymentTransactionStatus::Success,
        ]);

        $this->service->checkIn($booking->fresh());

        // 5% (50) collected via a real gateway, 10% (100) commission owed — the platform
        // is short 50, so the partner owes it instead of receiving a credit.
        $this->assertSame(-50.0, (float) $wallet->fresh()->balance);
        $this->assertDatabaseHas('property_wallet_transactions', [
            'property_wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => 50.00,
            'reference_id' => $booking->id,
        ]);
    }

    public function test_check_in_debits_full_commission_for_a_pay_at_property_booking_with_nothing_collected_online(): void
    {
        $this->enableMultiMode();

        $property = Property::factory()->create();
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0, 'currency_code' => 'USD']);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'base_amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'commission_amount' => 100,
            'user_id' => null,
            // No Payment row at all — pure Pay-at-Property.
        ]);

        $this->service->checkIn($booking->fresh());

        $this->assertSame(-100.0, (float) $wallet->fresh()->balance);
        $this->assertNotNull($booking->fresh()->wallet_credited_at);
    }

    public function test_a_later_fully_paid_booking_absorbs_an_earlier_pay_at_property_shortfall_on_the_same_wallet(): void
    {
        $this->enableMultiMode();

        $property = Property::factory()->create();
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0, 'currency_code' => 'USD']);

        $shortfallBooking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'base_amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'commission_amount' => 100,
            'user_id' => null,
        ]);
        $this->service->checkIn($shortfallBooking->fresh());
        $this->assertSame(-100.0, (float) $wallet->fresh()->balance);

        $fullyPaidBooking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'base_amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'commission_amount' => 100,
            'user_id' => null,
        ]);
        $this->service->checkIn($fullyPaidBooking->fresh());

        // -100 + 900 = 800 — the shortfall from the first booking is fully absorbed by
        // the second, since it's all one running wallet balance.
        $this->assertSame(800.0, (float) $wallet->fresh()->balance);
    }

    // ── Group 6: Zero-config / edge cases ───────────────────────────────────

    public function test_no_commission_configured_sends_the_entire_retained_amount_to_the_wallet(): void
    {
        ['booking' => $booking, 'wallet' => $wallet, 'payment' => $payment] = $this->scaffold([
            'basePricePerNight' => 500.0,
            'policyRules' => [[3, 50], [0, 0]],
            'checkInDays' => 5,
        ]);

        $this->assertSame(0.0, (float) $booking->commission_rate);
        $this->assertSame('none', $booking->commission_source);

        $this->service->cancelBookingWithRefund($booking, 'test', CancellationInitiator::Customer);

        $refund = Refund::where('payment_id', $payment->id)->latest()->first();
        $this->assertSame(500.0, (float) $refund->amount);
        $this->assertSame(500.0, (float) $wallet->fresh()->balance);
        $this->assertSame(0.0, (float) $booking->fresh()->refund_inputs['commission']);
    }

    public function test_property_with_no_partner_refunds_the_customer_but_never_credits_a_wallet(): void
    {
        ['booking' => $booking, 'wallet' => $wallet, 'payment' => $payment] = $this->scaffold([
            'basePricePerNight' => 500.0,
            'countryDefaultRate' => 10,
            'policyRules' => [[3, 50], [0, 0]],
            'checkInDays' => 5,
            'withPartner' => false,
        ]);

        $this->service->cancelBookingWithRefund($booking, 'test', CancellationInitiator::Customer);

        $refund = Refund::where('payment_id', $payment->id)->latest()->first();
        $this->assertSame(500.0, (float) $refund->amount);
        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
        $this->assertDatabaseMissing('property_wallet_transactions', ['reference_id' => $booking->id]);
        $this->assertNull($booking->fresh()->formula_version);
    }

    public function test_single_mode_booking_refunds_normally_but_never_touches_commission_or_wallet(): void
    {
        ['booking' => $booking, 'wallet' => $wallet, 'payment' => $payment] = $this->scaffold([
            'basePricePerNight' => 500.0,
            'countryDefaultRate' => 8,
            'policyRules' => [[3, 50], [0, 0]],
            'checkInDays' => 5,
            'multiMode' => false,
        ]);

        $this->assertNull($booking->commission_rate);
        $this->assertNull($booking->commission_amount);
        $this->assertNull($booking->commission_source);

        $this->service->cancelBookingWithRefund($booking, 'test', CancellationInitiator::Customer);

        $refund = Refund::where('payment_id', $payment->id)->latest()->first();
        $this->assertSame(500.0, (float) $refund->amount);
        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
        $this->assertDatabaseMissing('property_wallet_transactions', ['reference_id' => $booking->id]);
        $this->assertNull($booking->fresh()->formula_version);
    }

    // ── Group 7: Explicit money-conservation invariant ──────────────────────
    // Directly proves: what the customer doesn't get refunded splits exactly
    // between the platform's commission and the partner's wallet credit.

    public function test_partial_refund_conserves_every_rupee_between_refund_tax_commission_and_wallet(): void
    {
        ['booking' => $booking, 'wallet' => $wallet, 'payment' => $payment] = $this->scaffold([
            'basePricePerNight' => 2000.0,
            'taxRatePercent' => 10,
            'commissionOverrideRate' => 18,
            'policyRules' => [[5, 45], [0, 0]],
            'checkInDays' => 7,
        ]);

        $this->assertSame(4400.0, (float) $payment->amount);

        $this->service->cancelBookingWithRefund($booking, 'test', CancellationInitiator::Customer);

        $refund = Refund::where('payment_id', $payment->id)->latest()->first();
        $fresh = $booking->fresh();
        $inputs = $fresh->refund_inputs;

        $this->assertSame(1980.0, (float) $refund->amount);
        $this->assertSame(2200.0, (float) $inputs['retained_room']);
        $this->assertSame(220.0, (float) $inputs['retained_tax']);
        $this->assertSame(396.0, (float) $inputs['commission']);
        $this->assertSame(1804.0, (float) $inputs['wallet_credit']);
        $this->assertSame(1804.0, (float) $wallet->fresh()->balance);

        // Every rupee the guest paid is accounted for across the four buckets.
        $this->assertSame(4400.0, $refund->amount + $inputs['retained_tax'] + $inputs['commission'] + $inputs['wallet_credit']);
        // What the platform kept splits exactly into admin's commission + partner's wallet share.
        $this->assertSame((float) $inputs['retained_room'], (float) $inputs['commission'] + (float) $inputs['wallet_credit']);
    }

    public function test_capped_deposit_cancellation_conserves_every_rupee_actually_collected(): void
    {
        ['booking' => $booking, 'wallet' => $wallet, 'payment' => $payment] = $this->scaffold([
            'basePricePerNight' => 3000.0,
            'taxRatePercent' => 10,
            'commissionOverrideRate' => 25,
            'policyRules' => [[5, 20], [0, 0]],
            'checkInDays' => 7,
        ]);

        // Only 2,500 was actually collected/settled, far below the theoretical retained amount.
        Payment::where('booking_id', $booking->id)->update(['amount' => 2500]);

        $this->service->cancelBookingWithRefund($booking, 'test', CancellationInitiator::Customer);

        $this->assertNull(Refund::where('payment_id', $payment->id)->first());

        $fresh = $booking->fresh();
        $inputs = $fresh->refund_inputs;

        $this->assertSame(2272.73, (float) $inputs['retained_room']);
        $this->assertSame(227.27, (float) $inputs['retained_tax']);
        $this->assertSame(568.18, (float) $inputs['commission']);
        $this->assertSame(1704.55, (float) $inputs['wallet_credit']);
        $this->assertSame(1704.55, (float) $wallet->fresh()->balance);

        $this->assertSame(2500.0, 0.0 + $inputs['retained_tax'] + $inputs['commission'] + $inputs['wallet_credit']);
        $this->assertSame((float) $inputs['retained_room'], (float) $inputs['commission'] + (float) $inputs['wallet_credit']);
    }
}
