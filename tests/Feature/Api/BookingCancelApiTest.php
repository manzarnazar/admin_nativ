<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyRule;
use App\Models\CommissionRate;
use App\Models\Country;
use App\Models\Partner;
use App\Models\PaymentGatewaySetting;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyType;
use App\Models\PropertyWallet;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Wiring-only coverage of the customer-facing cancellation endpoint
 * (POST /api/bookings/{bookingNumber}/cancel). The refund/wallet formula itself
 * is already exhaustively proven in BookingFinancialEndToEndTest and
 * BookingCancellationWalletCreditTest and doesn't change based on entry point —
 * this file proves ownership scoping, auth, idempotency, and that the endpoint
 * actually delegates to the real service correctly, using one concrete scenario.
 */
class BookingCancelApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Creates a real, paid, confirmed booking (via lock -> create-with-payment ->
     * webhook, same as the online-payment API flow) with a 50%-refund cancellation
     * policy in effect, ready to be cancelled through the real HTTP endpoint.
     *
     * @return array{booking: Booking, customer: User, otherCustomer: User, wallet: PropertyWallet}
     */
    private function makeCancellableBooking(): array
    {
        Setting::set('system_mode', 'multi');

        $country = Country::factory()->create(['currency_code' => 'USD', 'currency_symbol' => '$']);
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
        ]);

        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0, 'currency_code' => 'USD']);

        CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => null, 'rate' => 10]);

        $policy = CancellationPolicy::create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => null,
            'cancellation_cutoff_time' => '23:59:59',
            'is_active' => true,
        ]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 3, 'refund_percentage' => 50]);
        CancellationPolicyRule::create(['cancellation_policy_id' => $policy->id, 'days_before_checkin' => 0, 'refund_percentage' => 0]);

        $room = PropertyRoom::factory()->create(['property_id' => $property->id, 'base_price_per_night' => 1000]);
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();

        PaymentGatewaySetting::create([
            'gateway_type' => 'stripe',
            'country_id' => $country->id,
            'api_key' => 'key_test',
            'api_secret' => 'secret_test',
            'webhook_secret' => 'whsec_test_secret',
            'is_active' => true,
            'mode' => 'test',
        ]);

        Sanctum::actingAs($customer);
        $checkIn = now()->addDays(5);
        $checkOut = $checkIn->clone()->addDays(2);
        $lockId = $this->postJson('/api/bookings/lock', [
            'property_room_id' => $room->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'rooms' => 1,
        ])->assertStatus(200)->json('data.lock_id');

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_cancel', 'url' => 'https://checkout.stripe.com/pay/cs_test_cancel'], 200),
            'api.stripe.com/v1/refunds' => Http::response(['id' => 're_test_cancel', 'status' => 'succeeded'], 200),
        ]);

        $createResponse = $this->postJson('/api/bookings/create-with-payment', [
            'lock_id' => $lockId,
            'gateway_type' => 'stripe',
            'payment_type' => 'full',
            'adults' => 1,
            'children' => 0,
            'has_pets' => false,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '9998887777',
        ])->assertStatus(201);

        $bookingNumber = $createResponse->json('data.booking.booking_number');
        $paymentAmount = (float) $createResponse->json('data.payment.amount');

        $webhookPayload = [
            'id' => 'evt_cancel',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_cancel',
                'payment_intent' => 'pi_cancel',
                'amount_total' => (int) round($paymentAmount * 100),
                'currency' => 'usd',
            ]],
        ];
        $body = json_encode($webhookPayload);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts.'.'.$body, 'whsec_test_secret');
        $this->postJson('/api/payments/webhook/stripe', $webhookPayload, ['stripe-signature' => "t={$ts},v1={$sig}"])
            ->assertStatus(200);

        $booking = Booking::where('booking_number', $bookingNumber)->firstOrFail();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);

        return compact('booking', 'customer', 'otherCustomer', 'wallet');
    }

    public function test_authenticated_owner_can_cancel_their_own_booking_and_receives_the_refund_amount(): void
    {
        ['booking' => $booking, 'customer' => $customer, 'wallet' => $wallet] = $this->makeCancellableBooking();

        Sanctum::actingAs($customer);
        $response = $this->postJson("/api/bookings/{$booking->booking_number}/cancel");

        $response->assertStatus(200);
        $this->assertSame('cancelled', $response->json('data.booking.status'));
        $this->assertSame(1000.0, (float) $response->json('data.refund.amount'));
        $this->assertSame(50, $response->json('data.refund.percentage'));

        // Base 2000, 50% refund → 1000 retained, 10% commission → partner wallet gets 900.
        $this->assertSame(900.0, (float) $wallet->fresh()->balance);
    }

    public function test_cancelling_someone_elses_booking_returns_not_found(): void
    {
        ['booking' => $booking, 'otherCustomer' => $otherCustomer] = $this->makeCancellableBooking();

        Sanctum::actingAs($otherCustomer);
        $response = $this->postJson("/api/bookings/{$booking->booking_number}/cancel");

        $response->assertStatus(404)->assertJson(['error' => true]);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_cancel_requires_authentication(): void
    {
        ['booking' => $booking] = $this->makeCancellableBooking();

        // makeCancellableBooking() authenticates as the customer via Sanctum::actingAs()
        // to drive the lock/create-with-payment calls; clear that before this request.
        $this->app['auth']->forgetGuards();

        $response = $this->postJson("/api/bookings/{$booking->booking_number}/cancel");

        $response->assertStatus(401);
    }

    public function test_cancelling_an_already_cancelled_booking_is_idempotent(): void
    {
        ['booking' => $booking, 'customer' => $customer] = $this->makeCancellableBooking();
        Sanctum::actingAs($customer);

        $first = $this->postJson("/api/bookings/{$booking->booking_number}/cancel");
        $first->assertStatus(200);

        $second = $this->postJson("/api/bookings/{$booking->booking_number}/cancel");
        $second->assertStatus(200);
        $this->assertSame('cancelled', $second->json('data.booking.status'));
        $this->assertSame($first->json('data.refund.amount'), $second->json('data.refund.amount'));
    }

    public function test_cancel_preview_matches_the_percentage_actually_applied_on_cancel(): void
    {
        ['booking' => $booking, 'customer' => $customer] = $this->makeCancellableBooking();
        Sanctum::actingAs($customer);

        $preview = $this->getJson("/api/bookings/{$booking->booking_number}/cancel-preview");
        $preview->assertStatus(200);
        $this->assertTrue($preview->json('data.can_cancel'));
        $previewPercentage = $preview->json('data.refund_percentage');
        $previewAmount = (float) $preview->json('data.refund_amount');

        $cancel = $this->postJson("/api/bookings/{$booking->booking_number}/cancel");
        $cancel->assertStatus(200);

        $this->assertSame($previewPercentage, $cancel->json('data.refund.percentage'));
        $this->assertSame($previewAmount, (float) $cancel->json('data.refund.amount'));
    }
}
