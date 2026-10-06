<?php

namespace Tests\Feature\Actions;

use App\Actions\SendBookingNotificationsAction;
use App\Mail\PartnerBookingCancelledMailable;
use App\Mail\PartnerNewBookingMailable;
use App\Models\Booking;
use App\Models\Partner;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendBookingNotificationsActionTest extends TestCase
{
    use RefreshDatabase;

    private SendBookingNotificationsAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(SendBookingNotificationsAction::class);
    }

    private function makeBookingForPartner(Partner $partner, bool $withCustomer = true): Booking
    {
        $property = Property::factory()->create(['partner_id' => $partner->id]);
        $room = PropertyRoom::factory()->create(['property_id' => $property->id]);

        return Booking::factory()->create([
            'property_id' => $property->id,
            'property_room_id' => $room->id,
            'user_id' => $withCustomer ? User::factory()->create()->id : null,
        ]);
    }

    public function test_send_new_emails_the_partner_when_preference_is_enabled(): void
    {
        Mail::fake();

        $partner = Partner::factory()->create(['notification_preferences' => ['email_new_booking' => true]]);
        $booking = $this->makeBookingForPartner($partner);

        $this->action->sendNew($booking);

        Mail::assertQueued(
            PartnerNewBookingMailable::class,
            fn (PartnerNewBookingMailable $mail): bool => $mail->partner->is($partner) && $mail->booking->is($booking)
        );
    }

    public function test_send_new_skips_the_partner_email_when_preference_is_disabled(): void
    {
        Mail::fake();

        $partner = Partner::factory()->create(['notification_preferences' => ['email_new_booking' => false]]);
        $booking = $this->makeBookingForPartner($partner);

        $this->action->sendNew($booking);

        Mail::assertNotQueued(PartnerNewBookingMailable::class);
    }

    public function test_send_new_defaults_to_sending_when_preferences_are_unset(): void
    {
        Mail::fake();

        $partner = Partner::factory()->create(['notification_preferences' => null]);
        $booking = $this->makeBookingForPartner($partner);

        $this->action->sendNew($booking);

        Mail::assertQueued(PartnerNewBookingMailable::class);
    }

    public function test_send_new_does_not_email_a_partner_who_has_no_email(): void
    {
        Mail::fake();

        $partner = Partner::factory()->create(['notification_preferences' => ['email_new_booking' => true]]);
        $partner->user()->update(['email' => null]);
        $booking = $this->makeBookingForPartner($partner);

        $this->action->sendNew($booking);

        Mail::assertNotQueued(PartnerNewBookingMailable::class);
    }

    public function test_send_new_does_not_crash_for_a_single_mode_property_with_no_partner(): void
    {
        Mail::fake();

        $property = Property::factory()->create(['partner_id' => null]);
        $room = PropertyRoom::factory()->create(['property_id' => $property->id]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'property_room_id' => $room->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->action->sendNew($booking);

        Mail::assertNotQueued(PartnerNewBookingMailable::class);
    }

    public function test_send_status_update_emails_the_partner_on_cancellation_when_preference_is_enabled(): void
    {
        Mail::fake();

        $partner = Partner::factory()->create(['notification_preferences' => ['email_cancellations' => true]]);
        $booking = $this->makeBookingForPartner($partner);

        $this->action->sendStatusUpdate($booking, 'booking_cancelled');

        Mail::assertQueued(
            PartnerBookingCancelledMailable::class,
            fn (PartnerBookingCancelledMailable $mail): bool => $mail->partner->is($partner) && $mail->booking->is($booking)
        );
    }

    public function test_send_status_update_skips_the_partner_email_when_cancellation_preference_is_disabled(): void
    {
        Mail::fake();

        $partner = Partner::factory()->create(['notification_preferences' => ['email_cancellations' => false]]);
        $booking = $this->makeBookingForPartner($partner);

        $this->action->sendStatusUpdate($booking, 'booking_cancelled');

        Mail::assertNotQueued(PartnerBookingCancelledMailable::class);
    }

    public function test_send_status_update_never_sends_the_cancellation_email_for_other_status_types(): void
    {
        Mail::fake();

        $partner = Partner::factory()->create(['notification_preferences' => ['email_cancellations' => true]]);
        $booking = $this->makeBookingForPartner($partner);

        $this->action->sendStatusUpdate($booking, 'booking_checked_in');

        Mail::assertNotQueued(PartnerBookingCancelledMailable::class);
    }
}
