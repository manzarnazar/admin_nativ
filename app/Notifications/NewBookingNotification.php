<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to all admin users when a new booking is created.
 *
 * HOW IT WORKS:
 * 1. __construct($booking) — receives the booking that was just created
 * 2. via() — tells Laravel WHERE to send this notification. 'database' means
 *    it saves to the `notifications` table (not email, not SMS — just DB)
 * 3. toArray() — defines WHAT data to store in the `data` JSON column.
 *    This is what we read when displaying the notification in the UI.
 *
 * HOW TO CREATE MORE NOTIFICATIONS:
 * 1. Run: php artisan make:notification YourNotificationName
 * 2. Set via() to return ['database']
 * 3. Define toArray() with the data you want to show
 * 4. Call: $user->notify(new YourNotificationName($yourModel))
 */
class NewBookingNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking
    ) {}

    /**
     * Delivery channels — 'database' stores in the notifications table.
     * Other options: 'mail', 'vonage' (SMS), 'broadcast' (websocket).
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Data stored in the `data` JSON column of the notifications table.
     * This is what we read when showing notifications in the bell dropdown and the notifications page.
     */
    public function toArray(object $notifiable): array
    {
        $customer = $this->booking->customer;
        $property = $this->booking->property;
        $roomType = $this->booking->propertyRoom?->roomType;

        return [
            'title' => 'New Booking Received',
            'message' => ($customer?->name ?? 'Guest')
                .' booked '.($roomType?->name ?? 'a room')
                .' in '.($property?->name ?? 'a property')
                .' for '.$this->booking->check_in->format('M d, Y').'.',
            'booking_id' => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'icon' => 'heroicon-o-calendar-days',
            'color' => 'primary',
            'url' => '/bookings/'.$this->booking->id.'/view',
        ];
    }
}
