<?php

namespace Tests\Feature\Models;

use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test: notification_preferences was missing from Partner::$fillable,
     * so PartnerSettingsManage::save()'s $partner->update([...]) was silently
     * discarded — no exception, no error, the toggle just never persisted.
     */
    public function test_notification_preferences_is_mass_assignable(): void
    {
        $partner = Partner::factory()->create(['notification_preferences' => null]);

        $partner->update(['notification_preferences' => ['email_new_booking' => false]]);

        $this->assertSame(['email_new_booking' => false], $partner->fresh()->notification_preferences);
    }

    public function test_wants_email_notification_defaults_to_true_when_preferences_are_unset(): void
    {
        $partner = Partner::factory()->create(['notification_preferences' => null]);

        $this->assertTrue($partner->wantsEmailNotification('email_new_booking'));
        $this->assertTrue($partner->wantsEmailNotification('email_cancellations'));
        $this->assertTrue($partner->wantsEmailNotification('email_payouts'));
    }

    public function test_wants_email_notification_defaults_to_true_when_the_specific_key_is_missing(): void
    {
        $partner = Partner::factory()->create(['notification_preferences' => ['email_new_booking' => false]]);

        $this->assertFalse($partner->wantsEmailNotification('email_new_booking'));
        $this->assertTrue($partner->wantsEmailNotification('email_cancellations'));
    }

    public function test_wants_email_notification_respects_an_explicit_false(): void
    {
        $partner = Partner::factory()->create(['notification_preferences' => [
            'email_new_booking' => true,
            'email_cancellations' => false,
            'email_payouts' => true,
        ]]);

        $this->assertTrue($partner->wantsEmailNotification('email_new_booking'));
        $this->assertFalse($partner->wantsEmailNotification('email_cancellations'));
        $this->assertTrue($partner->wantsEmailNotification('email_payouts'));
    }
}
