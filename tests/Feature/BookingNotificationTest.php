<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Models\Country;
use App\Models\Notification;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyType;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_creates_in_app_notification()
    {
        // 1. Setup Mock Data
        $user = User::factory()->create();

        $country = Country::create([
            'name' => 'India',
            'iso_code' => 'IN',
            'currency_code' => 'INR',
            'currency_symbol' => '₹',
            'currency_name' => 'Indian Rupee',
            'phone_code' => '+91',
        ]);

        // ref_states/ref_cities are populated by a raw-SQL import command
        // (App\Console\Commands\ImportRefDataCommand), not by a Laravel migration —
        // those tables don't exist at all in the SQLite test database. Property's
        // ref_state_id/ref_city_id have no FK constraint and every reader uses ?->,
        // so leaving them null here is correct and avoids depending on that import.

        $propertyType = PropertyType::factory()->create();

        $property = Property::create([
            'name' => 'Test Hotel',
            'slug' => 'test-hotel',
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'status' => PropertyStatus::Active,
            'check_in_time' => '12:00',
            'check_out_time' => '11:00',
            'latitude' => 23.24,
            'longitude' => 69.66,
            'pay_at_property' => true,
        ]);

        $roomType = RoomType::factory()->create(['name' => 'Deluxe Room']);

        $room = PropertyRoom::factory()->create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'base_price_per_night' => 1000,
            'total_rooms' => 5,
        ]);

        // 2. Perform Booking — two-step flow: reserve inventory, then confirm from the lock.
        $bookingService = app(BookingService::class);

        // Authenticate user
        $this->actingAs($user);

        $lock = $bookingService->createInventoryLock($user, [
            'property_room_id' => $room->id,
            'check_in' => Carbon::now()->addDays(1)->toDateString(),
            'check_out' => Carbon::now()->addDays(2)->toDateString(),
            'rooms' => 1,
        ]);

        $result = $bookingService->confirmBookingFromLock($user, [
            'lock_id' => $lock['lock_id'],
            'payment_method' => 'pay_at_property',
            'adults' => 2,
            'children' => 0,
            'has_pets' => false,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '1234567890',
        ]);

        $bookingId = $result['booking']['id'];

        // 3. Assertions
        // Check if booking exists
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'user_id' => $user->id,
        ]);

        // Check if notification was created in our custom table
        $this->assertDatabaseHas('notifications', [
            'type' => 'booking_confirmation',
            'title' => __('notifications.booking_confirmed_title'),
        ]);

        // Check if it's linked to the user
        $notification = Notification::where('type', 'booking_confirmation')->first();
        $this->assertNotNull($notification, 'Notification was not created');

        $this->assertDatabaseHas('notification_user', [
            'notification_id' => $notification->id,
            'user_id' => $user->id,
        ]);

        // Check if data is stored correctly as array/json
        $this->assertIsArray($notification->data);
        $this->assertEquals($bookingId, $notification->data['booking_id']);
    }
}
