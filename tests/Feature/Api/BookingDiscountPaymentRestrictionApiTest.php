<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentTransactionStatus;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PaymentGatewaySetting;
use App\Models\PromoCode;
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
 * Commission is always calculated on the full undiscounted base_amount, but Pay at
 * Property / partial payment only ever collect a fraction of the (possibly discounted)
 * total online. For a multi-mode, partner-owned property, combining a discount with
 * either of those payment shapes can leave the platform holding less online than the
 * commission it owes — see BookingService::confirmBookingFromLock()/
 * createBookingWithPayment()/retryBookingPayment() and generateQuote() for the guards
 * this exercises. Each guard is proven both to fire (multi-mode + partner) and to stay
 * a no-op for single-mode and no-partner properties, since those have no wallet-crediting
 * concept at all (checkIn()'s own crediting block is gated the exact same way).
 */
class BookingDiscountPaymentRestrictionApiTest extends TestCase
{
    use RefreshDatabase;

    private function enableMultiMode(): void
    {
        Setting::set('system_mode', 'multi');
    }

    /**
     * @return array{country: Country, propertyType: PropertyType, partner: ?Partner, property: Property, room: PropertyRoom, customer: User}
     */
    private function makeBookableProperty(array $opts = []): array
    {
        $opts = array_merge([
            'basePricePerNight' => 1000.0,
            'withPartner' => true,
            'multiMode' => true,
            'payAtProperty' => true,
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
            'pay_at_property' => $opts['payAtProperty'],
            'advance_percentage' => $opts['advancePercentage'],
        ]);

        $room = PropertyRoom::factory()->create([
            'property_id' => $property->id,
            'base_price_per_night' => $opts['basePricePerNight'],
        ]);

        $customer = User::factory()->create();

        return compact('country', 'propertyType', 'partner', 'property', 'room', 'customer');
    }

    private function makePromoCode(Country $country, string $code = 'TESTPROMO'): PromoCode
    {
        return PromoCode::create([
            'code' => $code,
            'title' => 'Test Promo',
            'is_active' => true,
            'is_auto_apply' => false,
            'discount_type' => 'fixed',
            'discount_value' => 50,
            'is_first_booking_only' => false,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'usage_limit' => 100,
            'used_count' => 0,
            'customer_segment' => 'all',
            'country_id' => $country->id,
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

    // ── confirmBookingFromLock(): Pay at Property + discount ────────────────

    public function test_pay_at_property_with_discount_is_rejected_for_a_multi_mode_partner_property(): void
    {
        $scenario = $this->makeBookableProperty();
        $this->makePromoCode($scenario['country']);
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        $response = $this->postJson('/api/bookings/confirm', [
            'lock_id' => $lockId,
            'adults' => 1,
            'children' => 0,
            'has_pets' => false,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '9998887777',
            'payment_method' => 'pay_at_property',
            'coupon_code' => 'TESTPROMO',
        ]);

        $response->assertStatus(422)->assertJson(['error' => true]);
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_pay_at_property_with_discount_is_allowed_when_the_config_flag_is_disabled(): void
    {
        config(['app.require_full_payment_for_discounted_bookings' => false]);

        $scenario = $this->makeBookableProperty();
        $this->makePromoCode($scenario['country']);
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        $response = $this->postJson('/api/bookings/confirm', [
            'lock_id' => $lockId,
            'adults' => 1,
            'children' => 0,
            'has_pets' => false,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '9998887777',
            'payment_method' => 'pay_at_property',
            'coupon_code' => 'TESTPROMO',
        ]);

        $response->assertStatus(201);
    }

    public function test_pay_at_property_with_discount_is_allowed_in_single_mode(): void
    {
        $scenario = $this->makeBookableProperty(['multiMode' => false]);
        $this->makePromoCode($scenario['country']);
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        $response = $this->postJson('/api/bookings/confirm', [
            'lock_id' => $lockId,
            'adults' => 1,
            'children' => 0,
            'has_pets' => false,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '9998887777',
            'payment_method' => 'pay_at_property',
            'coupon_code' => 'TESTPROMO',
        ]);

        $response->assertStatus(201);
    }

    public function test_pay_at_property_with_discount_is_allowed_for_a_property_with_no_partner(): void
    {
        $scenario = $this->makeBookableProperty(['withPartner' => false]);
        $this->makePromoCode($scenario['country']);
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        $response = $this->postJson('/api/bookings/confirm', [
            'lock_id' => $lockId,
            'adults' => 1,
            'children' => 0,
            'has_pets' => false,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '9998887777',
            'payment_method' => 'pay_at_property',
            'coupon_code' => 'TESTPROMO',
        ]);

        $response->assertStatus(201);
    }

    public function test_pay_at_property_without_a_discount_is_still_allowed(): void
    {
        $scenario = $this->makeBookableProperty();
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        $response = $this->postJson('/api/bookings/confirm', [
            'lock_id' => $lockId,
            'adults' => 1,
            'children' => 0,
            'has_pets' => false,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '9998887777',
            'payment_method' => 'pay_at_property',
        ]);

        $response->assertStatus(201);
    }

    // ── createBookingWithPayment(): partial payment + discount ─────────────

    public function test_partial_payment_with_discount_is_rejected_for_a_multi_mode_partner_property(): void
    {
        $scenario = $this->makeBookableProperty();
        $this->makePromoCode($scenario['country']);
        PaymentGatewaySetting::create([
            'gateway_type' => 'stripe',
            'country_id' => $scenario['country']->id,
            'api_key' => 'key_test',
            'api_secret' => 'secret_test',
            'webhook_secret' => 'whsec_test_secret',
            'is_active' => true,
            'mode' => 'test',
        ]);
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        $response = $this->postJson('/api/bookings/create-with-payment', [
            'lock_id' => $lockId,
            'gateway_type' => 'stripe',
            'payment_type' => 'partial',
            'adults' => 1,
            'children' => 0,
            'has_pets' => false,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '9998887777',
            'coupon_code' => 'TESTPROMO',
        ]);

        $response->assertStatus(422)->assertJson(['error' => true]);
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_partial_payment_with_discount_is_allowed_in_single_mode(): void
    {
        $scenario = $this->makeBookableProperty(['multiMode' => false]);
        $this->makePromoCode($scenario['country']);
        PaymentGatewaySetting::create([
            'gateway_type' => 'stripe',
            'country_id' => $scenario['country']->id,
            'api_key' => 'key_test',
            'api_secret' => 'secret_test',
            'webhook_secret' => 'whsec_test_secret',
            'is_active' => true,
            'mode' => 'test',
        ]);
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_single_mode', 'url' => 'https://checkout.stripe.com/pay/cs_single_mode'], 200)]);

        $response = $this->postJson('/api/bookings/create-with-payment', [
            'lock_id' => $lockId,
            'gateway_type' => 'stripe',
            'payment_type' => 'partial',
            'adults' => 1,
            'children' => 0,
            'has_pets' => false,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '9998887777',
            'coupon_code' => 'TESTPROMO',
        ]);

        $response->assertStatus(201);
    }

    // ── retryBookingPayment(): switching an already-created booking to partial ──

    public function test_retrying_to_partial_payment_is_rejected_when_the_booking_has_a_discount(): void
    {
        $scenario = $this->makeBookableProperty();
        $this->makePromoCode($scenario['country']);
        PaymentGatewaySetting::create([
            'gateway_type' => 'stripe',
            'country_id' => $scenario['country']->id,
            'api_key' => 'key_test',
            'api_secret' => 'secret_test',
            'webhook_secret' => 'whsec_test_secret',
            'is_active' => true,
            'mode' => 'test',
        ]);
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_retry_1', 'url' => 'https://checkout.stripe.com/pay/cs_retry_1'], 200)]);

        // Full payment + discount is allowed at creation — the gap only opens once a
        // discounted booking tries to move to partial.
        $created = $this->postJson('/api/bookings/create-with-payment', [
            'lock_id' => $lockId,
            'gateway_type' => 'stripe',
            'payment_type' => 'full',
            'adults' => 1,
            'children' => 0,
            'has_pets' => false,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '9998887777',
            'coupon_code' => 'TESTPROMO',
        ]);
        $created->assertStatus(201);
        $bookingNumber = $created->json('data.booking.booking_number');

        // Simulate the initial gateway attempt having failed, so a retry is eligible.
        Payment::where('booking_id', Booking::where('booking_number', $bookingNumber)->value('id'))
            ->update(['status' => PaymentTransactionStatus::Failed]);

        $response = $this->postJson("/api/bookings/{$bookingNumber}/retry-payment", [
            'gateway_type' => 'stripe',
            'payment_type' => 'partial',
        ]);

        $response->assertStatus(422)->assertJson(['error' => true]);
    }

    public function test_retrying_to_partial_payment_is_allowed_in_single_mode_even_with_a_discount(): void
    {
        $scenario = $this->makeBookableProperty(['multiMode' => false]);
        $this->makePromoCode($scenario['country']);
        PaymentGatewaySetting::create([
            'gateway_type' => 'stripe',
            'country_id' => $scenario['country']->id,
            'api_key' => 'key_test',
            'api_secret' => 'secret_test',
            'webhook_secret' => 'whsec_test_secret',
            'is_active' => true,
            'mode' => 'test',
        ]);
        $lockId = $this->lockRoom($scenario['customer'], $scenario['room'])->json('data.lock_id');

        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_retry_2', 'url' => 'https://checkout.stripe.com/pay/cs_retry_2'], 200)]);

        $created = $this->postJson('/api/bookings/create-with-payment', [
            'lock_id' => $lockId,
            'gateway_type' => 'stripe',
            'payment_type' => 'full',
            'adults' => 1,
            'children' => 0,
            'has_pets' => false,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '9998887777',
            'coupon_code' => 'TESTPROMO',
        ]);
        $created->assertStatus(201);
        $bookingNumber = $created->json('data.booking.booking_number');

        Payment::where('booking_id', Booking::where('booking_number', $bookingNumber)->value('id'))
            ->update(['status' => PaymentTransactionStatus::Failed]);

        $response = $this->postJson("/api/bookings/{$bookingNumber}/retry-payment", [
            'gateway_type' => 'stripe',
            'payment_type' => 'partial',
        ]);

        $response->assertStatus(201);
    }

    // ── generateQuote(): payment options reflect the discount up front ─────

    public function test_quote_hides_pay_at_property_and_flags_partial_unavailable_when_a_discount_resolves(): void
    {
        $scenario = $this->makeBookableProperty();
        $this->makePromoCode($scenario['country']);
        Sanctum::actingAs($scenario['customer']);

        $checkIn = now()->addDays(5);
        $checkOut = $checkIn->clone()->addDays(2);

        $response = $this->postJson('/api/bookings/quote', [
            'property_room_id' => $scenario['room']->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'coupon_code' => 'TESTPROMO',
        ]);

        $response->assertStatus(200);
        $this->assertNotContains('pay_at_property', $response->json('data.payment.available_methods'));
        $this->assertFalse($response->json('data.payment.partial_payment_available'));
    }

    public function test_quote_keeps_pay_at_property_available_with_a_discount_when_the_config_flag_is_disabled(): void
    {
        config(['app.require_full_payment_for_discounted_bookings' => false]);

        $scenario = $this->makeBookableProperty();
        $this->makePromoCode($scenario['country']);
        Sanctum::actingAs($scenario['customer']);

        $checkIn = now()->addDays(5);
        $checkOut = $checkIn->clone()->addDays(2);

        $response = $this->postJson('/api/bookings/quote', [
            'property_room_id' => $scenario['room']->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'coupon_code' => 'TESTPROMO',
        ]);

        $response->assertStatus(200);
        $this->assertContains('pay_at_property', $response->json('data.payment.available_methods'));
        $this->assertTrue($response->json('data.payment.partial_payment_available'));
    }

    public function test_quote_keeps_pay_at_property_available_without_a_discount(): void
    {
        $scenario = $this->makeBookableProperty();
        Sanctum::actingAs($scenario['customer']);

        $checkIn = now()->addDays(5);
        $checkOut = $checkIn->clone()->addDays(2);

        $response = $this->postJson('/api/bookings/quote', [
            'property_room_id' => $scenario['room']->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
        ]);

        $response->assertStatus(200);
        $this->assertContains('pay_at_property', $response->json('data.payment.available_methods'));
        $this->assertTrue($response->json('data.payment.partial_payment_available'));
    }

    public function test_quote_keeps_pay_at_property_available_with_a_discount_in_single_mode(): void
    {
        $scenario = $this->makeBookableProperty(['multiMode' => false]);
        $this->makePromoCode($scenario['country']);
        Sanctum::actingAs($scenario['customer']);

        $checkIn = now()->addDays(5);
        $checkOut = $checkIn->clone()->addDays(2);

        $response = $this->postJson('/api/bookings/quote', [
            'property_room_id' => $scenario['room']->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'coupon_code' => 'TESTPROMO',
        ]);

        $response->assertStatus(200);
        $this->assertContains('pay_at_property', $response->json('data.payment.available_methods'));
        $this->assertTrue($response->json('data.payment.partial_payment_available'));
    }
}
