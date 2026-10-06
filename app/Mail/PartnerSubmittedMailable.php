<?php

namespace App\Mail;

use App\Models\Partner;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerSubmittedMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Partner $partner,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('admin.partner_submitted_email_subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partner-submitted',
            with: [
                'partner' => $this->partner,
                'appName' => config('app.name'),
                'primaryColor' => Setting::get('primary_color', '#1A73E8'),
            ],
        );
    }
}
