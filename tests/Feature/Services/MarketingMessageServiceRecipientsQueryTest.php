<?php

namespace Tests\Feature\Services;

use App\Enums\BookingStatus;
use App\Enums\MarketingMessageAudience;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\State;
use App\Models\User;
use App\Services\MarketingMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingMessageServiceRecipientsQueryTest extends TestCase
{
    use RefreshDatabase;

    private MarketingMessageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MarketingMessageService::class);
    }

    public function test_all_returns_only_active_customers_and_never_partners(): void
    {
        $customer = User::factory()->create(['status' => UserStatus::Active]);
        $inactiveCustomer = User::factory()->create(['status' => UserStatus::Inactive]);
        $partner = Partner::factory()->create();

        $recipients = $this->service->recipientsQuery(MarketingMessageAudience::All, null)->pluck('id');

        $this->assertTrue($recipients->contains($customer->id));
        $this->assertFalse($recipients->contains($inactiveCustomer->id));
        $this->assertFalse($recipients->contains($partner->user_id));
    }

    public function test_city_based_includes_only_customers_who_booked_a_property_in_that_city(): void
    {
        $country = Country::factory()->create();
        $state = State::query()->create([
            'country_id' => $country->id,
            'name' => 'Test State',
        ]);
        $city = City::query()->create([
            'ref_city_id' => 501,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'name' => 'Test City',
            'slug' => 'test-city',
        ]);

        // ref_city_id is set via a raw update, bypassing Property's creating/updating
        // events — those look up App\Models\RefCity, whose table is populated by a
        // raw-SQL import command, not a migration, and doesn't exist in SQLite tests.
        $property = Property::factory()->create(['country_id' => $country->id]);
        Property::query()->where('id', $property->id)->update(['ref_city_id' => 501]);

        $otherProperty = Property::factory()->create(['country_id' => $country->id]);
        Property::query()->where('id', $otherProperty->id)->update(['ref_city_id' => 999]);

        $customerInCity = User::factory()->create(['status' => UserStatus::Active]);
        $customerInCity->bookings()->create($this->bookingAttributes($property, $customerInCity));

        $customerElsewhere = User::factory()->create(['status' => UserStatus::Active]);
        $customerElsewhere->bookings()->create($this->bookingAttributes($otherProperty, $customerElsewhere));

        $recipients = $this->service->recipientsQuery(MarketingMessageAudience::CityBased, $city->id)->pluck('id');

        $this->assertTrue($recipients->contains($customerInCity->id));
        $this->assertFalse($recipients->contains($customerElsewhere->id));
    }

    public function test_partners_includes_only_approved_and_not_suspended_partners(): void
    {
        $approvedPartner = Partner::factory()->create();
        $pendingPartner = Partner::factory()->pending()->create();
        $suspendedPartner = Partner::factory()->suspended()->create();
        $approvedButSuspendedAt = Partner::factory()->create(['suspended_at' => now()]);
        $customer = User::factory()->create(['status' => UserStatus::Active]);

        $recipients = $this->service->recipientsQuery(MarketingMessageAudience::Partners, null)->pluck('id');

        $this->assertTrue($recipients->contains($approvedPartner->user_id));
        $this->assertFalse($recipients->contains($pendingPartner->user_id));
        $this->assertFalse($recipients->contains($suspendedPartner->user_id));
        $this->assertFalse($recipients->contains($approvedButSuspendedAt->user_id));
        $this->assertFalse($recipients->contains($customer->id));
    }

    public function test_all_users_includes_active_customers_and_approved_partners_but_not_suspended_partners(): void
    {
        $customer = User::factory()->create(['status' => UserStatus::Active]);
        $approvedPartner = Partner::factory()->create();
        $suspendedPartner = Partner::factory()->suspended()->create();

        $recipients = $this->service->recipientsQuery(MarketingMessageAudience::AllUsers, null)->pluck('id');

        $this->assertTrue($recipients->contains($customer->id));
        $this->assertTrue($recipients->contains($approvedPartner->user_id));
        $this->assertFalse($recipients->contains($suspendedPartner->user_id));
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingAttributes(Property $property, User $customer): array
    {
        $propertyRoom = $property->rooms()->create([
            'room_type_id' => RoomType::factory()->create()->id,
            'base_price_per_night' => 100,
            'total_rooms' => 1,
        ]);

        return [
            'booking_number' => 'BK-'.fake()->unique()->numerify('########'),
            'property_id' => $property->id,
            'property_room_id' => $propertyRoom->id,
            'user_id' => $customer->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'total_nights' => 2,
            'adults' => 1,
            'children' => 0,
            'booked_rooms' => 1,
            'base_amount' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 100,
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
        ];
    }
}
