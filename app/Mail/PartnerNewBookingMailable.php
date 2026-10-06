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

class PartnerNewBookingMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Partner $partner,
        public Booking $booking,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('admin.partner_new_booking_email_subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partner-new-booking',
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
