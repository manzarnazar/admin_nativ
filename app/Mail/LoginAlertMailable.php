<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginAlertMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $method,
        public CarbonImmutable $loggedInAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New sign-in to your account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login-alert',
            with: [
                'name' => $this->user->name,
                'method' => $this->method,
                'loggedInAt' => $this->loggedInAt,
                'primaryColor' => Setting::get('primary_color', '#1A73E8'),
            ],
        );
    }
}
