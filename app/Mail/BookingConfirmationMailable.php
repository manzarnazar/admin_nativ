<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmationMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Booking Confirmation – '.$this->booking->booking_number,
        );
    }

    public function content(): Content
    {
        $primaryColorLight = Setting::get('primary_color_light', '#E8F1FD');

        return new Content(
            view: 'emails.booking-confirmation',
            with: [
                'booking' => $this->booking,
                'primaryColor' => Setting::get('primary_color', '#1A73E8'),
                'primaryColorLight' => $primaryColorLight,
                'primaryColorLightRgb' => $this->hexToRgb($primaryColorLight),
                'appName' => Setting::get('app_name', config('app.name')),
                'logoUrl' => Setting::get('logo') ? asset('storage/'.Setting::get('logo')) : null,
                'contactEmail' => Setting::get('contact_email'),
            ],
        );
    }

    /**
     * Convert a "#rrggbb" hex color into a "r, g, b" string for use in CSS rgba().
     */
    private function hexToRgb(string $hex): string
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return "{$r}, {$g}, {$b}";
    }
}
