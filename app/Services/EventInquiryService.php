<?php

namespace App\Services;

use App\Enums\EventInquiryStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventInquiry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EventInquiryService
{
    /**
     * Submit a new event inquiry from the API.
     *
     * @param  array{property_slug: string, event_id: int, name: string, email: string, dial_code?: string, phone?: string, message?: string}  $data
     *
     * @throws ValidationException
     */
    public function submitInquiry(array $data): EventInquiry
    {
        $event = Event::query()
            ->where('id', $data['event_id'])
            ->where('status', 'active')
            ->first();

        if (! $event) {
            throw ValidationException::withMessages([
                'event_id' => 'The selected event is not available.',
            ]);
        }

        $property = Property::query()
            ->where('slug', $data['property_slug'])
            ->first();

        if (! $property) {
            throw ValidationException::withMessages([
                'property_slug' => 'The selected property is not available.',
            ]);
        }

        $inquiry = EventInquiry::query()->create([
            'inquiry_number' => $this->generateInquiryNumber(),
            'property_id' => $property->id,
            'event_id' => $data['event_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'dial_code' => isset($data['dial_code']) ? '+'.ltrim($data['dial_code'], '+') : null,
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => EventInquiryStatus::Pending,
        ]);

        $inquiry->load(['property', 'event']);

        $admins = User::query()
            ->whereIn('role', [UserRole::Admin, UserRole::Staff])
            ->get();

        if ($admins->isNotEmpty()) {
            app(NotificationService::class)->send(
                type: 'new_event_inquiry',
                title: 'New Event Inquiry Received',
                body: $inquiry->name
                    .' submitted an inquiry for '
                    .($inquiry->event?->title ?? 'an event')
                    .' at '.($inquiry->property?->name ?? 'a property').'.',
                userIds: $admins->pluck('id'),
                data: [
                    'inquiry_id' => $inquiry->id,
                    'inquiry_number' => $inquiry->inquiry_number,
                ],
                link: '/event-inquiries',
                countryId: $inquiry->property?->country_id,
            );
        }

        return $inquiry;
    }

    /**
     * Update the status of an event inquiry (admin action).
     *
     * @throws ValidationException
     */
    public function updateStatus(EventInquiry $inquiry, string $status): void
    {
        $statusEnum = EventInquiryStatus::tryFrom($status);

        if (! $statusEnum) {
            throw ValidationException::withMessages([
                'status' => 'Invalid status value.',
            ]);
        }

        $inquiry->update(['status' => $statusEnum]);
    }

    /**
     * Generate a unique inquiry number: INQ-2026-001.
     */
    private function generateInquiryNumber(): string
    {
        $year = now()->year;
        $prefix = "INQ-{$year}-";

        $last = EventInquiry::query()
            ->withTrashed()
            ->where('inquiry_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('inquiry_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}
