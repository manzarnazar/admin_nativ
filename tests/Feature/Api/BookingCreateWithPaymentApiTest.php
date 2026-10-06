<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Booking;
use App\Models\CommissionPartnerOverride;
use App\Models\CommissionRate;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PaymentGatewaySetting;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Exercises the real customer-facing pay-online flow — POST /api/bookings/lock →
 * POST /api/bookings/create-with-payment → gateway checkout → gateway webhook →
 * booking confirmation — through actual HTTP requests, with gateway calls faked via
 * Http::fake(). Stripe carries the full commission/payment-amount matrix (single
 * gateway call); Razorpay and Flutterwave get focused signature + confirmation
 * smoke tests, since the commission/refund formulas don't change per gateway.
 */
class BookingCreateWithPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private function enableMultiMode(): void
    {
        Setting::set('system_mode', 'multi');
    }

    /**
     * @return array{country: Country, propertyType: PropertyType, partner: ?Partner, property: Property, room: PropertyRoom, customer: User}
     */
    private function makeGatewayScenario(array $opts = []): array
    {
        $opts = array_merge([
            'basePricePerNight' => 1000.0,
            'countryDefaultRate' => null,
            'commissionOverrideRate' => null,
            'withPartner' => true,
            'multiMode' => true,
            'advancePercentage' => 30.0,
        ], $opts);

        if ($opts['multiMode']) {
            $this->enableMultiMode();
        }

        $country = Country::factory()->create(['currency_code' => 'USD', 'currency_symbol' => '$']);
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = $opts['withPartner'] ? Partner::factory()->create() : null;

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner?->id,
            'advance_percentage' => $opts['advancePercentage'],
        ]);

        if ($opts['countryDefaultRate'] !== null) {
            CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => null, 'rate' => $opts['countryDefaultRate']]);
        }
        if ($opts['commissionOverrideRate'] !== null && $partner) {
            CommissionPartnerOverride::factory()->create([
                'country_id' => $country->id,
                'partner_id' => $partner->id,
                'property_type_id' => $propertyType->id,
                'rate' => $opts['commissionOverrideRate'],
            ]);
        }

        $room = PropertyRoom::factory()->create([
            'property_id' => $property->id,
            'base_price_per_night' => $opts['basePricePerNight'],
        ]);

        $customer = User::factory()->create();

        return compact('country', 'propertyType', 'partner', 'property', 'room', 'customer');
    }

    private function seedGatewaySettings(Country $country, string $gateway, string $webhookSecret = 'whsec_test_secret'): PaymentGatewaySetting
    {
        return PaymentGatewaySetting::create([
            'gateway_type' => $gateway,
            'country_id' => $country->id,
            'api_key' => 'key_test',
            'api_secret' => 'secret_test',
            'webhook_secret' => $webhookSecret,
            'is_active' => true,
            'mode' => 'test',
        ]);
    }

    private function lockRoom(User $customer, PropertyRoom $room, int $checkInDays = 5, int $nights = 2, int $rooms = 1)
    {
        Sanctum::actingAs($customer);

        $checkIn = now()->addDays($checkInDays);
        $checkOut = $checkIn->clone()->addDays($nights);

        $response = $this->postJson('/api/bookings/lock', [
            'property_room_id' => $room->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'rooms' => $rooms,
        ]);

        $response->assertStatus(200);

        return $response;
    }

    private function createWithPaymentPayload(int $lockId, string $gateway, string $paymentType, array $overrides = []): array
    {
        return array_merge([
            'lock_id' => $lockId,
            'gateway_type' => $gateway,
            'payment_type' => $paymentType,
            'adults' => 1,
            'children' => 0,
            'has_pets' => false,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '9998887777',
        ], $overrides);
    }

    private function stripeSignatureHeader(array $payload, string $secret): array
    {
        $body = json_encode($payload);
        $ts = (string) time();

        return ['stripe-signature' => "t={$ts},v1=".hash_hmac('sha256', $ts.'.'.$body, $secret)];
    }

    private function razorpaySignatureHeader(array $payload, string $secret): array
    {
        return ['x-razorpay-signature' => hash_hmac('sha256', json_encode($payload), $secret)];
    }

    private function flutterwaveSignatureHeader(string $secret): array
    {
        return ['verif-hash' => $secret];
    }

    // ── Stripe: full commission/payment-amount matrix ───────────────────────

    public function test_full_online_payment_via_stripe_confirms_with_country_default_commission(): void
    {
        $scenario = $this->makeGatewayScenario(['countryDefaultRate' => 8]);
        $this->seedGatewaySettings($scenario['country'], 'stripe');
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/pay/cs_test_1'], 200)]);

        $response = $this->postJson('/api/bookings/create-with-payment', $this->createWithPaymentPayload($lockId, 'stripe', 'full'));
        $response->assertStatus(201);
        $this->assertSame('cs_test_1', $response->json('data.payment.gateway_order_id'));
        $paymentAmount = (float) $response->json('data.payment.amount');
        $this->assertSame(2000.0, $paymentAmount);

        $webhookPayload = [
            'id' => 'evt_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_1',
                'payment_intent' => 'pi_1',
                'amount_total' => (int) round($paymentAmount * 100),
                'currency' => 'usd',
            ]],
        ];
        $this->postJson('/api/payments/webhook/stripe', $webhookPayload, $this->stripeSignatureHeader($webhookPayload, 'whsec_test_secret'))
            ->assertStatus(200);

        $booking = Booking::where('booking_number', $response->json('data.booking.booking_number'))->firstOrFail();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Paid, $booking->payment_status);
        $this->assertSame(8.0, (float) $booking->commission_rate);
        $this->assertSame('country_default', $booking->commission_source);
        $this->assertSame(PaymentTransactionStatus::Success, Payment::where('booking_id', $booking->id)->firstOrFail()->status);
    }

    public function test_full_online_payment_via_stripe_confirms_with_partner_override_commission(): void
    {
        $scenario = $this->makeGatewayScenario(['countryDefaultRate' => 8, 'commissionOverrideRate' => 20]);
        $this->seedGatewaySettings($scenario['country'], 'stripe');
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_2', 'url' => 'https://checkout.stripe.com/pay/cs_test_2'], 200)]);

        $response = $this->postJson('/api/bookings/create-with-payment', $this->createWithPaymentPayload($lockId, 'stripe', 'full'));
        $response->assertStatus(201);
        $paymentAmount = (float) $response->json('data.payment.amount');

        $webhookPayload = [
            'id' => 'evt_2',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_2',
                'payment_intent' => 'pi_2',
                'amount_total' => (int) round($paymentAmount * 100),
                'currency' => 'usd',
            ]],
        ];
        $this->postJson('/api/payments/webhook/stripe', $webhookPayload, $this->stripeSignatureHeader($webhookPayload, 'whsec_test_secret'))
            ->assertStatus(200);

        $booking = Booking::where('booking_number', $response->json('data.booking.booking_number'))->firstOrFail();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(20.0, (float) $booking->commission_rate);
        $this->assertSame('partner_override', $booking->commission_source);
    }

    public function test_partial_deposit_payment_via_stripe_charges_only_the_advance_percentage(): void
    {
        $scenario = $this->makeGatewayScenario(['advancePercentage' => 30]);
        $this->seedGatewaySettings($scenario['country'], 'stripe');
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_3', 'url' => 'https://checkout.stripe.com/pay/cs_test_3'], 200)]);

        $response = $this->postJson('/api/bookings/create-with-payment', $this->createWithPaymentPayload($lockId, 'stripe', 'partial'));

        $response->assertStatus(201);
        $this->assertSame(600.0, (float) $response->json('data.payment.amount'));
        $this->assertSame(1400.0, (float) $response->json('data.payment.remaining_amount'));
    }

    public function test_partial_deposit_payment_confirms_via_webhook_with_payment_status_partial(): void
    {
        $scenario = $this->makeGatewayScenario(['advancePercentage' => 30]);
        $this->seedGatewaySettings($scenario['country'], 'stripe');
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_4', 'url' => 'https://checkout.stripe.com/pay/cs_test_4'], 200)]);

        $response = $this->postJson('/api/bookings/create-with-payment', $this->createWithPaymentPayload($lockId, 'stripe', 'partial'));
        $response->assertStatus(201);
        $paymentAmount = (float) $response->json('data.payment.amount');

        $webhookPayload = [
            'id' => 'evt_4',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_4',
                'payment_intent' => 'pi_4',
                'amount_total' => (int) round($paymentAmount * 100),
                'currency' => 'usd',
            ]],
        ];
        $this->postJson('/api/payments/webhook/stripe', $webhookPayload, $this->stripeSignatureHeader($webhookPayload, 'whsec_test_secret'))
            ->assertStatus(200);

        $booking = Booking::where('booking_number', $response->json('data.booking.booking_number'))->firstOrFail();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Partial, $booking->payment_status);
    }

    public function test_invalid_webhook_signature_does_not_confirm_the_booking(): void
    {
        $scenario = $this->makeGatewayScenario(['countryDefaultRate' => 8]);
        $this->seedGatewaySettings($scenario['country'], 'stripe');
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_5', 'url' => 'https://checkout.stripe.com/pay/cs_test_5'], 200)]);

        $response = $this->postJson('/api/bookings/create-with-payment', $this->createWithPaymentPayload($lockId, 'stripe', 'full'));
        $paymentAmount = (float) $response->json('data.payment.amount');

        $webhookPayload = [
            'id' => 'evt_5',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_5',
                'payment_intent' => 'pi_5',
                'amount_total' => (int) round($paymentAmount * 100),
                'currency' => 'usd',
            ]],
        ];
        $webhookResponse = $this->postJson('/api/payments/webhook/stripe', $webhookPayload, $this->stripeSignatureHeader($webhookPayload, 'wrong_secret_entirely'));

        $webhookResponse->assertStatus(200)->assertJson(['status' => 'signature_invalid']);

        $booking = Booking::where('booking_number', $response->json('data.booking.booking_number'))->firstOrFail();
        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertSame(PaymentTransactionStatus::Pending, Payment::where('booking_id', $booking->id)->firstOrFail()->status);
    }

    public function test_webhook_amount_mismatch_flags_the_payment_instead_of_confirming(): void
    {
        $scenario = $this->makeGatewayScenario(['countryDefaultRate' => 8]);
        $this->seedGatewaySettings($scenario['country'], 'stripe');
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_6', 'url' => 'https://checkout.stripe.com/pay/cs_test_6'], 200)]);

        $response = $this->postJson('/api/bookings/create-with-payment', $this->createWithPaymentPayload($lockId, 'stripe', 'full'));
        $paymentAmount = (float) $response->json('data.payment.amount');

        // Gateway reports a different amount than what was actually charged.
        $webhookPayload = [
            'id' => 'evt_6',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_6',
                'payment_intent' => 'pi_6',
                'amount_total' => (int) round(($paymentAmount + 500) * 100),
                'currency' => 'usd',
            ]],
        ];
        $this->postJson('/api/payments/webhook/stripe', $webhookPayload, $this->stripeSignatureHeader($webhookPayload, 'whsec_test_secret'))
            ->assertStatus(200);

        $booking = Booking::where('booking_number', $response->json('data.booking.booking_number'))->firstOrFail();
        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertSame(PaymentTransactionStatus::Flagged, Payment::where('booking_id', $booking->id)->firstOrFail()->status);
    }

    public function test_gateway_order_creation_failure_returns_422_but_leaves_booking_as_pending_payment(): void
    {
        $scenario = $this->makeGatewayScenario(['countryDefaultRate' => 8]);
        $this->seedGatewaySettings($scenario['country'], 'stripe');
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'card_declined']], 402)]);

        $response = $this->postJson('/api/bookings/create-with-payment', $this->createWithPaymentPayload($lockId, 'stripe', 'full'));

        $response->assertStatus(422);
        $this->assertNotNull($response->json('data.gateway_error'));

        $booking = Booking::where('booking_number', $response->json('data.booking.booking_number'))->firstOrFail();
        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
    }

    public function test_create_with_payment_requires_authentication(): void
    {
        $response = $this->postJson('/api/bookings/create-with-payment', $this->createWithPaymentPayload(1, 'stripe', 'full'));

        $response->assertStatus(401);
    }

    public function test_create_with_payment_does_not_duplicate_a_booking_when_the_same_lock_is_reused(): void
    {
        $scenario = $this->makeGatewayScenario(['countryDefaultRate' => 8]);
        $this->seedGatewaySettings($scenario['country'], 'stripe');
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_7', 'url' => 'https://checkout.stripe.com/pay/cs_test_7'], 200)]);

        $first = $this->postJson('/api/bookings/create-with-payment', $this->createWithPaymentPayload($lockId, 'stripe', 'full'));
        $first->assertStatus(201);

        $second = $this->postJson('/api/bookings/create-with-payment', $this->createWithPaymentPayload($lockId, 'stripe', 'full'));
        $second->assertStatus(201);

        $this->assertSame(1, Booking::where('property_room_id', $scenario['room']->id)->count());
    }

    // ── Razorpay + Flutterwave: signature + confirmation smoke tests ────────

    public function test_razorpay_webhook_with_valid_signature_confirms_the_booking(): void
    {
        $scenario = $this->makeGatewayScenario(['countryDefaultRate' => 8]);
        $this->seedGatewaySettings($scenario['country'], 'razorpay');
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_test_1'], 200),
            'api.razorpay.com/v1/payment_links' => Http::response(['id' => 'plink_test_1', 'short_url' => 'https://rzp.io/l/test1'], 200),
        ]);

        $response = $this->postJson('/api/bookings/create-with-payment', $this->createWithPaymentPayload($lockId, 'razorpay', 'full'));
        $response->assertStatus(201);
        $this->assertSame('order_test_1', $response->json('data.payment.gateway_order_id'));
        $paymentAmount = (float) $response->json('data.payment.amount');

        $webhookPayload = [
            'event' => 'payment_link.paid',
            'payload' => ['payment_link' => ['entity' => [
                'id' => 'plink_test_1',
                'reference_id' => 'order_test_1',
                'amount' => (int) round($paymentAmount * 100),
                'currency' => 'USD',
                'status' => 'paid',
            ]]],
        ];
        $this->postJson('/api/payments/webhook/razorpay', $webhookPayload, $this->razorpaySignatureHeader($webhookPayload, 'whsec_test_secret'))
            ->assertStatus(200);

        $booking = Booking::where('booking_number', $response->json('data.booking.booking_number'))->firstOrFail();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Paid, $booking->payment_status);
    }

    public function test_flutterwave_webhook_with_valid_signature_confirms_the_booking(): void
    {
        $scenario = $this->makeGatewayScenario(['countryDefaultRate' => 8]);
        $this->seedGatewaySettings($scenario['country'], 'flutterwave');
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        Http::fake(['api.flutterwave.com/*' => Http::response(['data' => ['link' => 'https://flw.com/pay/test', 'tx_ref' => 'tx_test_1']], 200)]);

        $response = $this->postJson('/api/bookings/create-with-payment', $this->createWithPaymentPayload($lockId, 'flutterwave', 'full'));
        $response->assertStatus(201);
        $this->assertSame('tx_test_1', $response->json('data.payment.gateway_order_id'));
        $paymentAmount = (float) $response->json('data.payment.amount');

        $webhookPayload = [
            'event' => 'charge.completed',
            'data' => [
                'id' => 987654,
                'tx_ref' => 'tx_test_1',
                'status' => 'successful',
                'amount' => $paymentAmount,
                'currency' => 'USD',
            ],
        ];
        $this->postJson('/api/payments/webhook/flutterwave', $webhookPayload, $this->flutterwaveSignatureHeader('whsec_test_secret'))
            ->assertStatus(200);

        $booking = Booking::where('booking_number', $response->json('data.booking.booking_number'))->firstOrFail();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Paid, $booking->payment_status);
    }
}
