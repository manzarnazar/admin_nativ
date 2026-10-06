<?php

namespace App\Mail;

use App\Models\Partner;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerRejectedMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Partner $partner,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('admin.partner_rejected_email_subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partner-rejected',
            with: [
                'partner' => $this->partner,
                'reason' => $this->reason,
                'appName' => config('app.name'),
                'primaryColor' => Setting::get('primary_color', '#1A73E8'),
            ],
        );
    }
}
