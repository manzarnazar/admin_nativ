<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\PropertyRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $propertyRoom = PropertyRoom::factory()->create();

        $checkIn = now()->addDay();
        $checkOut = $checkIn->clone()->addDays(2);

        return [
            'booking_number' => 'BK-'.fake()->unique()->numerify('########'),
            'property_id' => $propertyRoom->property_id,
            'property_room_id' => $propertyRoom->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'total_nights' => 2,
            'adults' => 2,
            'children' => 0,
            'booked_rooms' => 1,
            'base_amount' => 200,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 200,
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
        ];
    }

    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::CheckedIn,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    /** Stay that already ended (used for suspend-eligibility tests). */
    public function past(): static
    {
        $checkIn = now()->subDays(5);
        $checkOut = now()->subDays(3);

        return $this->state(fn (array $attributes) => [
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
        ]);
    }

    public function withCommission(float $rate, float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'commission_rate' => $rate,
            'commission_amount' => $amount,
        ]);
    }

    public function walletCredited(): static
    {
        return $this->state(fn (array $attributes) => [
            'wallet_credited_at' => now(),
        ]);
    }
}
