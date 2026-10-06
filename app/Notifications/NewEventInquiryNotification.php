<?php

namespace App\Notifications;

use App\Models\EventInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewEventInquiryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public EventInquiry $inquiry
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Event Inquiry Received',
            'message' => $this->inquiry->name
                .' submitted an inquiry for '
                .($this->inquiry->event?->title ?? 'an event')
                .' at '.($this->inquiry->property?->name ?? 'a property').'.',
            'inquiry_id' => $this->inquiry->id,
            'inquiry_number' => $this->inquiry->inquiry_number,
            'icon' => 'heroicon-o-ticket',
            'color' => 'warning',
            'url' => '/event-inquiries',
        ];
    }
}
