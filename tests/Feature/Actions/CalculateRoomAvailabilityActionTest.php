<?php

namespace Tests\Feature\Actions;

use App\Actions\CalculateRoomAvailabilityAction;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\RoomInventory;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateRoomAvailabilityActionTest extends TestCase
{
    use RefreshDatabase;

    private CalculateRoomAvailabilityAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(CalculateRoomAvailabilityAction::class);
    }

    private function candidateIds(): Builder
    {
        return Property::query()->select('id');
    }

    private function makeRoom(Property $property, int $totalRooms, int $maxGuests): PropertyRoom
    {
        $roomType = RoomType::factory()->create(['max_guests' => $maxGuests]);

        return PropertyRoom::factory()->create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'total_rooms' => $totalRooms,
        ]);
    }

    public function test_sums_inventory_and_computes_greedy_capacity_across_room_types(): void
    {
        $property = Property::factory()->create();
        $this->makeRoom($property, 5, 2); // Deluxe
        $this->makeRoom($property, 5, 3); // Super Deluxe

        $row = $this->action->handle(
            $this->candidateIds(),
            now()->addDay()->toDateString(),
            now()->addDays(4)->toDateString(),
            8,
        )->get()->firstWhere('property_id', $property->id);

        $this->assertSame(10, (int) $row->total_available_rooms);
        $this->assertSame(21, (int) $row->greedy_capacity);
        $this->assertSame(0, (int) $row->single_room_type_available);
    }

    public function test_single_room_type_available_true_when_one_type_covers_the_request(): void
    {
        $property = Property::factory()->create();
        $this->makeRoom($property, 5, 2);
        $this->makeRoom($property, 5, 3);

        $row = $this->action->handle(
            $this->candidateIds(),
            now()->addDay()->toDateString(),
            now()->addDays(4)->toDateString(),
            5,
        )->get()->firstWhere('property_id', $property->id);

        $this->assertSame(1, (int) $row->single_room_type_available);
    }

    public function test_booked_and_locked_rooms_reduce_availability(): void
    {
        $property = Property::factory()->create();
        $room = $this->makeRoom($property, 5, 2);

        $checkIn = now()->addDay()->startOfDay();
        $checkOut = $checkIn->copy()->addDays(3);

        // Night 1: 1 booked. Night 2 (worst night): 2 booked + 1 locked. Night 3: no row at all.
        RoomInventory::factory()->create([
            'property_id' => $property->id,
            'property_room_id' => $room->id,
            'date' => $checkIn->toDateString(),
            'total_rooms' => 5,
            'booked_rooms' => 1,
            'locked_rooms' => 0,
        ]);
        RoomInventory::factory()->create([
            'property_id' => $property->id,
            'property_room_id' => $room->id,
            'date' => $checkIn->copy()->addDay()->toDateString(),
            'total_rooms' => 5,
            'booked_rooms' => 2,
            'locked_rooms' => 1,
        ]);

        $row = $this->action->handle(
            $this->candidateIds(),
            $checkIn->toDateString(),
            $checkOut->toDateString(),
            1,
        )->get()->firstWhere('property_id', $property->id);

        // Worst night uses 3 (2 booked + 1 locked) => available = 5 - 3 = 2.
        $this->assertSame(2, (int) $row->total_available_rooms);
    }

    public function test_missing_inventory_row_counts_as_fully_available(): void
    {
        $property = Property::factory()->create();
        $this->makeRoom($property, 5, 2);

        // No room_inventory rows seeded at all for the stay.
        $row = $this->action->handle(
            $this->candidateIds(),
            now()->addDay()->toDateString(),
            now()->addDays(2)->toDateString(),
            1,
        )->get()->firstWhere('property_id', $property->id);

        $this->assertSame(5, (int) $row->total_available_rooms);
    }

    public function test_partial_credit_on_the_crossing_room_type(): void
    {
        $property = Property::factory()->create();
        $this->makeRoom($property, 5, 3); // higher max_guests, taken fully
        $this->makeRoom($property, 5, 2); // crossing type, only partially needed

        // Needing 6 rooms: all 5 of max_guests=3 (15) + 1 of max_guests=2 (2) = 17.
        $row = $this->action->handle(
            $this->candidateIds(),
            now()->addDay()->toDateString(),
            now()->addDays(3)->toDateString(),
            6,
        )->get()->firstWhere('property_id', $property->id);

        $this->assertSame(10, (int) $row->total_available_rooms);
        $this->assertSame(17, (int) $row->greedy_capacity);
    }
}
