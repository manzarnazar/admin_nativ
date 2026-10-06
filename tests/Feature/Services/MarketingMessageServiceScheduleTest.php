<?php

namespace Tests\Feature\Services;

use App\Enums\MarketingMessageAudience;
use App\Enums\MarketingMessageType;
use App\Models\Country;
use App\Models\User;
use App\Services\MarketingMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingMessageServiceScheduleTest extends TestCase
{
    use RefreshDatabase;

    private MarketingMessageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MarketingMessageService::class);
    }

    public function test_schedule_converts_the_admins_wall_clock_time_to_utc_before_storing(): void
    {
        $admin = $this->makeAdmin();

        $message = $this->service->schedule($this->scheduleData([
            'scheduled_at' => '2026-08-15 14:00:00',
            'timezone' => 'Asia/Kolkata',
        ]), $admin);

        $this->assertSame('2026-08-15 08:30:00', $message->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Kolkata', $message->scheduled_timezone);
    }

    public function test_schedule_leaves_a_utc_admins_time_unchanged(): void
    {
        $admin = $this->makeAdmin();

        $message = $this->service->schedule($this->scheduleData([
            'scheduled_at' => '2026-08-15 14:00:00',
            'timezone' => 'UTC',
        ]), $admin);

        $this->assertSame('2026-08-15 14:00:00', $message->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $message->scheduled_timezone);
    }

    private function makeAdmin(): User
    {
        $country = Country::factory()->create();

        return User::factory()->admin()->create(['current_country_id' => $country->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function scheduleData(array $overrides): array
    {
        return array_merge([
            'type' => MarketingMessageType::Push->value,
            'title' => 'Test notification',
            'body' => 'Test body',
            'audience' => MarketingMessageAudience::All->value,
        ], $overrides);
    }
}
