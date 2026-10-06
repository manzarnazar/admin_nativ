<?php

namespace Tests\Feature\Services;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Models\Booking;
use App\Models\Property;
use App\Services\PropertyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertySuspensionTest extends TestCase
{
    use RefreshDatabase;

    private PropertyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PropertyService::class);
    }

    public function test_suspend_throws_when_property_has_a_confirmed_upcoming_booking(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Active]);
        Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'status' => BookingStatus::Confirmed,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->suspendProperty($property, 'Testing suspension block');
    }

    public function test_suspend_throws_when_property_has_a_checked_in_ongoing_booking(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Active]);
        Booking::factory()->checkedIn()->create([
            'property_id' => $property->id,
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->suspendProperty($property, 'Testing suspension block');
    }

    public function test_suspend_succeeds_when_the_only_booking_is_a_past_stay(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Active]);
        Booking::factory()->past()->create([
            'property_id' => $property->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->service->suspendProperty($property, 'Testing suspension');

        $this->assertSame(PropertyStatus::Suspended, $property->fresh()->status);
    }

    public function test_suspend_succeeds_when_the_only_upcoming_booking_is_cancelled(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Active]);
        Booking::factory()->cancelled()->create([
            'property_id' => $property->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
        ]);

        $this->service->suspendProperty($property, 'Testing suspension');

        $this->assertSame(PropertyStatus::Suspended, $property->fresh()->status);
    }

    public function test_suspend_sets_status_reason_timestamp_and_snapshots_prior_status(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Active]);

        $this->service->suspendProperty($property, 'Fraudulent activity reported');

        $fresh = $property->fresh();
        $this->assertSame(PropertyStatus::Suspended, $fresh->status);
        $this->assertSame('Fraudulent activity reported', $fresh->suspension_reason);
        $this->assertNotNull($fresh->suspended_at);
        $this->assertSame('active', $fresh->pre_suspension_status);
    }

    public function test_suspend_snapshots_inactive_as_the_prior_status(): void
    {
        $property = Property::factory()->inactive()->create();

        $this->service->suspendProperty($property, 'Testing');

        $this->assertSame('inactive', $property->fresh()->pre_suspension_status);
    }

    public function test_unsuspend_restores_the_snapshotted_prior_status(): void
    {
        $property = Property::factory()->inactive()->create();
        $this->service->suspendProperty($property, 'Testing');

        $this->service->unsuspendProperty($property->fresh());

        $fresh = $property->fresh();
        $this->assertSame(PropertyStatus::Inactive, $fresh->status);
        $this->assertNull($fresh->suspended_at);
        $this->assertNull($fresh->suspension_reason);
        $this->assertNull($fresh->pre_suspension_status);
    }

    public function test_unsuspend_falls_back_to_active_when_no_prior_status_was_snapshotted(): void
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::Suspended,
            'pre_suspension_status' => null,
        ]);

        $this->service->unsuspendProperty($property);

        $this->assertSame(PropertyStatus::Active, $property->fresh()->status);
    }
}
