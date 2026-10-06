<?php

namespace App\Mail;

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RefundNotificationMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Refund $refund,
        public RefundStatus $status,
    ) {}

    public function envelope(): Envelope
    {
        $booking = $this->refund->payment?->booking;
        $bookingNumber = $booking?->booking_number ?? 'N/A';

        $subject = $this->status === RefundStatus::Completed
            ? "Refund Processed – Booking #{$bookingNumber}"
            : "Refund Update – Booking #{$bookingNumber}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.refund-notification',
            with: [
                'refund' => $this->refund,
                'status' => $this->status,
                'booking' => $this->refund->payment?->booking,
                'primaryColor' => Setting::get('primary_color', '#1A73E8'),
            ],
        );
    }
}
