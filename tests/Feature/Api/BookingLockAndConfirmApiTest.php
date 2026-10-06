<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\CommissionPartnerOverride;
use App\Models\CommissionRate;
use App\Models\Country;
use App\Models\InventoryLock;
use App\Models\Partner;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Exercises the real customer-facing pay-at-property flow — POST /api/bookings/lock
 * then POST /api/bookings/confirm — through actual HTTP requests with Sanctum auth,
 * rather than calling BookingService directly. Complements BookingFinancialEndToEndTest
 * (which proves the formulas) by proving those formulas are reachable through the API
 * customers actually use, and that FormRequest validation + auth are enforced.
 */
class BookingLockAndConfirmApiTest extends TestCase
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
            'countryDefaultRate' => null,
            'propertyTypeRate' => null,
            'commissionOverrideRate' => null,
            'withPartner' => true,
            'multiMode' => true,
            'payAtProperty' => true,
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
            'pay_at_property' => $opts['payAtProperty'],
        ]);

        if ($opts['countryDefaultRate'] !== null) {
            CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => null, 'rate' => $opts['countryDefaultRate']]);
        }
        if ($opts['propertyTypeRate'] !== null) {
            CommissionRate::factory()->create(['country_id' => $country->id, 'property_type_id' => $propertyType->id, 'rate' => $opts['propertyTypeRate']]);
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

    private function lockRoom(User $customer, PropertyRoom $room, int $checkInDays = 1, int $nights = 2, int $rooms = 1)
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

    private function confirmGuestPayload(array $overrides = []): array
    {
        return array_merge([
            'adults' => 1,
            'children' => 0,
            'has_pets' => false,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '9998887777',
            'payment_method' => 'pay_at_property',
        ], $overrides);
    }

    // ── Commission hierarchy through the real HTTP flow ────────────────────

    public function test_lock_then_confirm_uses_country_default_commission(): void
    {
        $scenario = $this->makeBookableProperty(['countryDefaultRate' => 8]);
        $lockResponse = $this->lockRoom($scenario['customer'], $scenario['room']);
        $lockId = $lockResponse->json('data.lock_id');

        $response = $this->postJson('/api/bookings/confirm', $this->confirmGuestPayload(['lock_id' => $lockId]));

        $response->assertStatus(201);
        $bookingNumber = $response->json('data.booking.booking_number');
        $booking = Booking::where('booking_number', $bookingNumber)->firstOrFail();

        $this->assertSame(2000.0, (float) $booking->base_amount);
        $this->assertSame(8.0, (float) $booking->commission_rate);
        $this->assertSame('country_default', $booking->commission_source);
        $this->assertSame(160.0, (float) $booking->commission_amount);
    }

    public function test_lock_then_confirm_prefers_property_type_commission_rate(): void
    {
        $scenario = $this->makeBookableProperty(['countryDefaultRate' => 8, 'propertyTypeRate' => 12]);
        $lockResponse = $this->lockRoom($scenario['customer'], $scenario['room']);
        $lockId = $lockResponse->json('data.lock_id');

        $response = $this->postJson('/api/bookings/confirm', $this->confirmGuestPayload(['lock_id' => $lockId]));

        $response->assertStatus(201);
        $booking = Booking::where('booking_number', $response->json('data.booking.booking_number'))->firstOrFail();

        $this->assertSame(12.0, (float) $booking->commission_rate);
        $this->assertSame('country_type_override', $booking->commission_source);
        $this->assertSame(240.0, (float) $booking->commission_amount);
    }

    public function test_lock_then_confirm_prefers_partner_commission_override(): void
    {
        $scenario = $this->makeBookableProperty(['countryDefaultRate' => 8, 'propertyTypeRate' => 12, 'commissionOverrideRate' => 20]);
        $lockResponse = $this->lockRoom($scenario['customer'], $scenario['room']);
        $lockId = $lockResponse->json('data.lock_id');

        $response = $this->postJson('/api/bookings/confirm', $this->confirmGuestPayload(['lock_id' => $lockId]));

        $response->assertStatus(201);
        $booking = Booking::where('booking_number', $response->json('data.booking.booking_number'))->firstOrFail();

        $this->assertSame(20.0, (float) $booking->commission_rate);
        $this->assertSame('partner_override', $booking->commission_source);
        $this->assertSame(400.0, (float) $booking->commission_amount);
    }

    // ── Validation ───────────────────────────────────────────────────────

    public function test_lock_rejects_a_check_in_date_in_the_past(): void
    {
        $scenario = $this->makeBookableProperty();
        Sanctum::actingAs($scenario['customer']);

        $response = $this->postJson('/api/bookings/lock', [
            'property_room_id' => $scenario['room']->id,
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'rooms' => 1,
        ]);

        $response->assertStatus(422)->assertJson(['error' => true]);
    }

    public function test_lock_rejects_check_out_on_or_before_check_in(): void
    {
        $scenario = $this->makeBookableProperty();
        Sanctum::actingAs($scenario['customer']);

        $checkIn = now()->addDay()->toDateString();

        $response = $this->postJson('/api/bookings/lock', [
            'property_room_id' => $scenario['room']->id,
            'check_in' => $checkIn,
            'check_out' => $checkIn,
            'rooms' => 1,
        ]);

        $response->assertStatus(422)->assertJson(['error' => true]);
    }

    public function test_lock_rejects_an_unknown_property_room(): void
    {
        $scenario = $this->makeBookableProperty();
        Sanctum::actingAs($scenario['customer']);

        $response = $this->postJson('/api/bookings/lock', [
            'property_room_id' => 999999,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'rooms' => 1,
        ]);

        $response->assertStatus(422)->assertJson(['error' => true]);
    }

    public function test_confirm_requires_authentication(): void
    {
        $response = $this->postJson('/api/bookings/confirm', $this->confirmGuestPayload(['lock_id' => 1]));

        $response->assertStatus(401);
    }

    public function test_confirm_requires_pay_at_property_when_total_is_greater_than_zero(): void
    {
        $scenario = $this->makeBookableProperty();
        $lockResponse = $this->lockRoom($scenario['customer'], $scenario['room']);
        $lockId = $lockResponse->json('data.lock_id');

        $response = $this->postJson('/api/bookings/confirm', $this->confirmGuestPayload(['lock_id' => $lockId, 'payment_method' => null]));

        $response->assertStatus(422);
        $this->assertStringContainsString('payment method is required', $response->json('message'));
    }

    public function test_confirm_rejects_pay_online_as_the_payment_method(): void
    {
        $scenario = $this->makeBookableProperty();
        $lockResponse = $this->lockRoom($scenario['customer'], $scenario['room']);
        $lockId = $lockResponse->json('data.lock_id');

        $response = $this->postJson('/api/bookings/confirm', $this->confirmGuestPayload(['lock_id' => $lockId, 'payment_method' => 'pay_online']));

        $response->assertStatus(422);
        $this->assertStringContainsString('payment initiation endpoint', $response->json('message'));
    }

    public function test_confirm_fails_when_property_does_not_allow_pay_at_property(): void
    {
        $scenario = $this->makeBookableProperty(['payAtProperty' => false]);
        $lockResponse = $this->lockRoom($scenario['customer'], $scenario['room']);
        $lockId = $lockResponse->json('data.lock_id');

        $response = $this->postJson('/api/bookings/confirm', $this->confirmGuestPayload(['lock_id' => $lockId]));

        $response->assertStatus(422);
        $this->assertStringContainsString('not available for pay at property', $response->json('message'));
    }

    public function test_confirm_with_an_expired_lock_returns_a_validation_error(): void
    {
        $scenario = $this->makeBookableProperty();
        $lockResponse = $this->lockRoom($scenario['customer'], $scenario['room']);
        $lockId = $lockResponse->json('data.lock_id');

        InventoryLock::where('id', $lockId)->update(['expires_at' => now()->subMinute()]);

        $response = $this->postJson('/api/bookings/confirm', $this->confirmGuestPayload(['lock_id' => $lockId]));

        $response->assertStatus(422);
        $this->assertStringContainsString('expired', $response->json('message'));
    }

    public function test_confirm_rejects_a_lock_belonging_to_another_user(): void
    {
        $scenario = $this->makeBookableProperty();
        $lockResponse = $this->lockRoom($scenario['customer'], $scenario['room']);
        $lockId = $lockResponse->json('data.lock_id');

        $otherCustomer = User::factory()->create();
        Sanctum::actingAs($otherCustomer);

        $response = $this->postJson('/api/bookings/confirm', $this->confirmGuestPayload(['lock_id' => $lockId]));

        $response->assertStatus(422);
        $this->assertStringContainsString('does not belong to the current user', $response->json('message'));
    }

    // ── Edge cases ───────────────────────────────────────────────────────

    public function test_single_mode_booking_via_api_has_null_commission_fields(): void
    {
        $scenario = $this->makeBookableProperty(['multiMode' => false, 'countryDefaultRate' => 8]);
        $lockResponse = $this->lockRoom($scenario['customer'], $scenario['room']);
        $lockId = $lockResponse->json('data.lock_id');

        $response = $this->postJson('/api/bookings/confirm', $this->confirmGuestPayload(['lock_id' => $lockId]));

        $response->assertStatus(201);
        $booking = Booking::where('booking_number', $response->json('data.booking.booking_number'))->firstOrFail();

        $this->assertNull($booking->commission_rate);
        $this->assertNull($booking->commission_amount);
        $this->assertNull($booking->commission_source);
    }

    public function test_property_with_no_partner_still_confirms_using_country_default_commission(): void
    {
        $scenario = $this->makeBookableProperty(['withPartner' => false, 'countryDefaultRate' => 10]);
        $lockResponse = $this->lockRoom($scenario['customer'], $scenario['room']);
        $lockId = $lockResponse->json('data.lock_id');

        $response = $this->postJson('/api/bookings/confirm', $this->confirmGuestPayload(['lock_id' => $lockId]));

        $response->assertStatus(201);
        $booking = Booking::where('booking_number', $response->json('data.booking.booking_number'))->firstOrFail();

        $this->assertSame(10.0, (float) $booking->commission_rate);
        $this->assertSame('country_default', $booking->commission_source);
    }
}
