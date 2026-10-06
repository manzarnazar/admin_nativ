<?php

namespace Tests\Unit\Models;

use App\Models\Country;
use App\Models\RefCountry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ref_countries is reference data imported via raw SQL (database/sql/countries.sql),
 * not a Laravel migration, so it never exists in the RefreshDatabase SQLite test DB.
 * A minimal version of the table (id, name, timezones) is created here.
 */
class CountryTimezoneSelectOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ref_countries')) {
            Schema::create('ref_countries', function ($table): void {
                $table->increments('id');
                $table->string('name');
                $table->json('timezones')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_no_recorded_timezones_returns_empty_options_and_no_lock(): void
    {
        $refCountry = RefCountry::query()->forceCreate(['name' => 'Nowhere', 'timezones' => []]);
        $country = Country::factory()->create(['ref_country_id' => $refCountry->id]);

        $result = $country->timezoneSelectOptions();

        $this->assertSame([], $result['options']);
        $this->assertNull($result['locked_to']);
    }

    public function test_a_single_timezone_is_auto_selected_and_locked(): void
    {
        $refCountry = RefCountry::query()->forceCreate([
            'name' => 'India',
            'timezones' => [
                ['zoneName' => 'Asia/Kolkata', 'gmtOffsetName' => '+05:30', 'tzName' => 'India Standard Time'],
            ],
        ]);
        $country = Country::factory()->create(['ref_country_id' => $refCountry->id]);

        $result = $country->timezoneSelectOptions();

        $this->assertSame('Asia/Kolkata', $result['locked_to']);
        $this->assertArrayHasKey('Asia/Kolkata', $result['options']);
        $this->assertCount(1, $result['options']);
    }

    public function test_multiple_timezones_are_all_offered_with_no_lock(): void
    {
        $refCountry = RefCountry::query()->forceCreate([
            'name' => 'United States',
            'timezones' => [
                ['zoneName' => 'America/New_York', 'gmtOffsetName' => '-05:00', 'tzName' => 'Eastern Standard Time'],
                ['zoneName' => 'America/Los_Angeles', 'gmtOffsetName' => '-08:00', 'tzName' => 'Pacific Standard Time'],
            ],
        ]);
        $country = Country::factory()->create(['ref_country_id' => $refCountry->id]);

        $result = $country->timezoneSelectOptions();

        $this->assertNull($result['locked_to']);
        $this->assertCount(2, $result['options']);
        $this->assertArrayHasKey('America/New_York', $result['options']);
        $this->assertArrayHasKey('America/Los_Angeles', $result['options']);
    }
}
