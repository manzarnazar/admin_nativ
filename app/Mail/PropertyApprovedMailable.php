<?php

namespace App\Mail;

use App\Models\Property;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PropertyApprovedMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Property $property,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('admin.property_approved_email_subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.property-approved',
            with: [
                'property' => $this->property,
                'appName' => config('app.name'),
                'loginUrl' => url('/partner/login'),
                'primaryColor' => Setting::get('primary_color', '#1A73E8'),
            ],
        );
    }
}
