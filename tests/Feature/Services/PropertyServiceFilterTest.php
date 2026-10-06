<?php

namespace Tests\Feature\Services;

use App\Enums\DisplayPlatform;
use App\Enums\HomepageSectionType;
use App\Enums\ReviewStatus;
use App\Enums\SortByRule;
use App\Models\City;
use App\Models\Country;
use App\Models\HomepageSection;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyType;
use App\Models\Review;
use App\Models\RoomType;
use App\Models\State;
use App\Services\Api\PropertyService;
use App\Services\HomepageContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PropertyServiceFilterTest extends TestCase
{
    use RefreshDatabase;

    private PropertyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PropertyService::class);
    }

    private function makeProperty(array $attributes = []): Property
    {
        $property = Property::factory()->create($attributes);
        PropertyRoom::factory()->create(['property_id' => $property->id]);

        return $property;
    }

    /**
     * @param  array<int, array{total_rooms: int, max_guests: int}>  $roomsSpec
     */
    private function makeRoomProperty(array $propertyAttrs, array $roomsSpec): Property
    {
        $property = Property::factory()->create($propertyAttrs);

        foreach ($roomsSpec as $spec) {
            $roomType = RoomType::factory()->create(['max_guests' => $spec['max_guests']]);
            PropertyRoom::factory()->create([
                'property_id' => $property->id,
                'room_type_id' => $roomType->id,
                'total_rooms' => $spec['total_rooms'],
            ]);
        }

        return $property;
    }

    /**
     * Registers a minimal `ref_cities` row so Property::refCity() (eager-loaded
     * unconditionally by getProperties()) resolves. ref_cities is populated by
     * `app:import-ref-data` from an external SQL dump, not a Laravel migration,
     * so it doesn't exist in the test DB at all until created here.
     */
    private function makeCity(Country $country, int $refCityId, string $name): City
    {
        if (! Schema::hasTable('ref_cities')) {
            Schema::create('ref_cities', function ($table) {
                $table->unsignedMediumInteger('id')->primary();
                $table->string('name');
            });
        }
        DB::table('ref_cities')->insertOrIgnore(['id' => $refCityId, 'name' => $name]);

        $state = State::create(['country_id' => $country->id, 'name' => "{$name} State"]);

        return City::create([
            'ref_city_id' => $refCityId,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => 'active',
        ]);
    }

    public function test_city_slug_filters_to_only_that_city(): void
    {
        $country = Country::factory()->create();

        $dubai = $this->makeCity($country, 501, 'Dubai');
        $this->makeCity($country, 999, 'Abu Dhabi');

        $dubaiProperty = $this->makeProperty(['country_id' => $country->id, 'ref_city_id' => $dubai->ref_city_id]);
        $this->makeProperty(['country_id' => $country->id, 'ref_city_id' => 999]);

        $result = $this->service->getProperties(['city_slug' => 'dubai']);

        $ids = collect($result['items'])->pluck('id')->all();
        $this->assertSame([$dubaiProperty->id], $ids);
    }

    public function test_city_slug_matches_nothing_when_unknown(): void
    {
        $country = Country::factory()->create();
        $this->makeCity($country, 501, 'Dubai');
        $this->makeProperty(['country_id' => $country->id, 'ref_city_id' => 501]);

        $result = $this->service->getProperties(['city_slug' => 'nonexistent-city']);

        $this->assertSame([], collect($result['items'])->pluck('id')->all());
    }

    public function test_property_type_id_accepts_a_comma_separated_list(): void
    {
        $country = Country::factory()->create();
        $hotel = PropertyType::factory()->create(['is_active' => true]);
        $villa = PropertyType::factory()->create(['is_active' => true]);
        $apartment = PropertyType::factory()->create(['is_active' => true]);

        $hotelProperty = $this->makeProperty(['country_id' => $country->id, 'property_type_id' => $hotel->id]);
        $villaProperty = $this->makeProperty(['country_id' => $country->id, 'property_type_id' => $villa->id]);
        $this->makeProperty(['country_id' => $country->id, 'property_type_id' => $apartment->id]);

        $result = $this->service->getProperties(['property_type_id' => "{$hotel->id},{$villa->id}"]);

        $ids = collect($result['items'])->pluck('id')->sort()->values()->all();
        $this->assertSame(collect([$hotelProperty->id, $villaProperty->id])->sort()->values()->all(), $ids);
    }

    public function test_property_type_id_still_accepts_a_single_value(): void
    {
        $country = Country::factory()->create();
        $hotel = PropertyType::factory()->create(['is_active' => true]);
        $villa = PropertyType::factory()->create(['is_active' => true]);

        $hotelProperty = $this->makeProperty(['country_id' => $country->id, 'property_type_id' => $hotel->id]);
        $this->makeProperty(['country_id' => $country->id, 'property_type_id' => $villa->id]);

        $result = $this->service->getProperties(['property_type_id' => (string) $hotel->id]);

        $ids = collect($result['items'])->pluck('id')->all();
        $this->assertSame([$hotelProperty->id], $ids);
    }

    public function test_newest_sort_orders_by_most_recently_created_first(): void
    {
        $country = Country::factory()->create();

        $older = $this->makeProperty(['country_id' => $country->id, 'created_at' => now()->subDays(2)]);
        $newer = $this->makeProperty(['country_id' => $country->id, 'created_at' => now()]);

        $result = $this->service->getProperties(['sort_by' => 'newest']);

        $ids = collect($result['items'])->pluck('id')->all();
        $this->assertSame([$newer->id, $older->id], $ids);
    }

    /**
     * Regression test: a "Top Rated" homepage section used to order by a plain
     * average rating, while property.index's highly_rated sort used a Bayesian
     * weighted rating — the same "Top Rated" query could return properties in a
     * different order depending on which endpoint served it. Both must now use
     * the exact same formula and therefore the exact same order.
     */
    public function test_homepage_top_rated_section_and_property_index_highly_rated_agree_on_order(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);

        // A: one lucky 5-star review. B: a large, solid track record at a lower
        // average. A naive average ranks A first; a Bayesian rating (which this
        // test doesn't need to reason about numerically) may not — the point is
        // only that both code paths must agree with each other, whatever the order.
        $propertyA = $this->makeProperty(['country_id' => $country->id, 'property_type_id' => $propertyType->id]);
        Review::factory()->create([
            'property_id' => $propertyA->id,
            'rating' => 5.0,
            'status' => ReviewStatus::Published->value,
            'is_visible' => true,
        ]);

        $propertyB = $this->makeProperty(['country_id' => $country->id, 'property_type_id' => $propertyType->id]);
        for ($i = 0; $i < 20; $i++) {
            Review::factory()->create([
                'property_id' => $propertyB->id,
                'rating' => 3.0,
                'status' => ReviewStatus::Published->value,
                'is_visible' => true,
            ]);
        }

        $propertyIndexOrder = collect(
            $this->service->getProperties(['property_type_id' => (string) $propertyType->id, 'sort_by' => 'highly_rated'])['items']
        )->pluck('id')->all();

        $section = HomepageSection::create([
            'section_title' => 'Top Rated Stays',
            'section_type' => HomepageSectionType::TopRated->value,
            'display_platform' => DisplayPlatform::Both->value,
            'sort_by_rule' => SortByRule::HighestRating->value,
            'country_id' => $country->id,
            'property_type_ids' => [$propertyType->id],
            'is_active' => true,
        ]);

        $sectionOrder = collect(
            app(HomepageContentService::class)->getSectionProperties($section, limit: 10, offset: 0)['properties']
        )->pluck('id')->all();

        $this->assertSame($propertyIndexOrder, $sectionOrder);
        $this->assertCount(2, $sectionOrder);
    }

    public function test_rooms_filter_includes_property_when_inventory_and_capacity_both_pass(): void
    {
        $country = Country::factory()->create();
        $property = $this->makeRoomProperty(
            ['country_id' => $country->id],
            [['total_rooms' => 5, 'max_guests' => 2], ['total_rooms' => 5, 'max_guests' => 3]]
        );

        $result = $this->service->getProperties([
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'rooms' => 8,
            'adults' => 20,
        ]);

        $this->assertSame([$property->id], collect($result['items'])->pluck('id')->all());
    }

    public function test_rooms_filter_includes_property_when_using_all_rooms(): void
    {
        $country = Country::factory()->create();
        $property = $this->makeRoomProperty(
            ['country_id' => $country->id],
            [['total_rooms' => 5, 'max_guests' => 2], ['total_rooms' => 5, 'max_guests' => 3]]
        );

        $result = $this->service->getProperties([
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'rooms' => 10,
            'adults' => 25,
        ]);

        $this->assertSame([$property->id], collect($result['items'])->pluck('id')->all());
    }

    public function test_rooms_filter_excludes_property_when_inventory_insufficient(): void
    {
        $country = Country::factory()->create();
        $this->makeRoomProperty(
            ['country_id' => $country->id],
            [['total_rooms' => 5, 'max_guests' => 2], ['total_rooms' => 5, 'max_guests' => 3]]
        );

        $result = $this->service->getProperties([
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'rooms' => 12,
            'adults' => 1,
        ]);

        $this->assertSame([], collect($result['items'])->pluck('id')->all());
    }

    public function test_rooms_filter_excludes_property_when_capacity_insufficient(): void
    {
        $country = Country::factory()->create();
        $this->makeRoomProperty(
            ['country_id' => $country->id],
            [['total_rooms' => 5, 'max_guests' => 2], ['total_rooms' => 5, 'max_guests' => 3]]
        );

        $result = $this->service->getProperties([
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'rooms' => 8,
            'adults' => 30,
        ]);

        $this->assertSame([], collect($result['items'])->pluck('id')->all());
    }

    public function test_rooms_filter_is_ignored_without_check_in_check_out(): void
    {
        $country = Country::factory()->create();
        // Only 3 total rooms — would fail a rooms=8 check if it were engaged.
        $property = $this->makeRoomProperty(['country_id' => $country->id], [['total_rooms' => 3, 'max_guests' => 2]]);

        $result = $this->service->getProperties(['rooms' => 8]);

        $this->assertSame([$property->id], collect($result['items'])->pluck('id')->all());
    }

    public function test_rooms_filter_leaves_behavior_unchanged_without_rooms_param(): void
    {
        $country = Country::factory()->create();
        // Only 1 total room — the old weaker check has no room-count concept at
        // all (only checks max_guests), so this must still appear.
        $property = $this->makeRoomProperty(['country_id' => $country->id], [['total_rooms' => 1, 'max_guests' => 4]]);

        $result = $this->service->getProperties([
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'adults' => 4,
        ]);

        $this->assertSame([$property->id], collect($result['items'])->pluck('id')->all());
    }

    public function test_single_room_type_available_flag_and_sort_tier(): void
    {
        $country = Country::factory()->create();

        // Named so a plain alphabetical sort would put the split-required
        // property first — the tier must override that.
        $exactMatch = $this->makeRoomProperty(
            ['country_id' => $country->id, 'name' => 'ZZZ Exact Match Inn'],
            [['total_rooms' => 8, 'max_guests' => 2]]
        );
        $splitRequired = $this->makeRoomProperty(
            ['country_id' => $country->id, 'name' => 'AAA Split Booking Inn'],
            [['total_rooms' => 3, 'max_guests' => 2], ['total_rooms' => 3, 'max_guests' => 2]]
        );

        $result = $this->service->getProperties([
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'rooms' => 5,
            'adults' => 4,
        ]);

        $items = collect($result['items']);

        $this->assertSame([$exactMatch->id, $splitRequired->id], $items->pluck('id')->all());
        $this->assertTrue($items->firstWhere('id', $exactMatch->id)['single_room_type_available']);
        $this->assertFalse($items->firstWhere('id', $splitRequired->id)['single_room_type_available']);
    }

    private function makePropertyWithRating(Country $country, float $rating): Property
    {
        $property = $this->makeProperty(['country_id' => $country->id]);

        Review::factory()->create([
            'property_id' => $property->id,
            'rating' => $rating,
            'status' => ReviewStatus::Published->value,
            'is_visible' => true,
        ]);

        return $property;
    }

    public function test_ratings_filter_floors_a_decimal_average_to_its_star_bucket(): void
    {
        $country = Country::factory()->create();

        // 4.9 average floors to bucket 4, not 5.
        $property = $this->makePropertyWithRating($country, 4.9);

        $this->assertSame(
            [$property->id],
            collect($this->service->getProperties(['ratings' => '4'])['items'])->pluck('id')->all()
        );
        $this->assertSame(
            [],
            collect($this->service->getProperties(['ratings' => '5'])['items'])->pluck('id')->all()
        );
    }

    public function test_ratings_filter_accepts_a_comma_separated_list_as_an_or_match(): void
    {
        $country = Country::factory()->create();

        $fiveStar = $this->makePropertyWithRating($country, 5.0);
        $fourStar = $this->makePropertyWithRating($country, 4.2);
        $threeStar = $this->makePropertyWithRating($country, 3.5);

        $ids = collect($this->service->getProperties(['ratings' => '5,4'])['items'])->pluck('id')->sort()->values()->all();

        $this->assertSame(
            collect([$fiveStar->id, $fourStar->id])->sort()->values()->all(),
            $ids
        );
        $this->assertNotContains($threeStar->id, $ids);
    }

    public function test_ratings_filter_excludes_properties_with_no_reviews(): void
    {
        $country = Country::factory()->create();
        $this->makeProperty(['country_id' => $country->id]); // no reviews at all

        $result = $this->service->getProperties(['ratings' => '1,2,3,4,5']);

        $this->assertSame([], collect($result['items'])->pluck('id')->all());
    }
}
