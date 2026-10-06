<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Partner;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerBookingCancelledMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Partner $partner,
        public Booking $booking,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('admin.partner_booking_cancelled_email_subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partner-booking-cancelled',
            with: [
                'partner' => $this->partner,
                'booking' => $this->booking,
                'appName' => config('app.name'),
                'loginUrl' => url('/partner/login'),
                'primaryColor' => Setting::get('primary_color', '#1A73E8'),
            ],
        );
    }
}
