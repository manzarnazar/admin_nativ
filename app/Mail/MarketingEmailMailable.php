<?php

namespace App\Mail;

use App\Models\MarketingMessage;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MarketingEmailMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MarketingMessage $message
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->message->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.marketing-message',
            with: [
                'marketingMessage' => $this->message,
                'openTrackingUrl' => $this->message->openTrackingUrl(),
                'clickTrackingUrl' => $this->message->redirect_url
                    ? $this->message->clickTrackingUrl()
                    : null,
                'primaryColor' => Setting::get('primary_color', '#1A73E8'),
                'primaryColorLight' => Setting::get('primary_color_light', '#E8F1FD'),
            ],
        );
    }
}
