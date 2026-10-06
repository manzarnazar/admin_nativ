<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Storage;

class EventService
{
    /**
     * Get all active events (API response).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveEventsForApi(): array
    {
        return Event::query()
            ->where('status', 'active')
            ->latest()
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'description' => $event->description,
                'image' => $event->image_path ? asset('storage/'.$event->image_path) : null,
                'features' => $event->features ?? [],
            ])
            ->values()
            ->toArray();
    }

    /**
     * Create a new event.
     */
    public function createEvent(array $data): Event
    {
        return Event::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'image_path' => $data['image_path'] ?? null,
            'features' => $data['features'] ?? [],
            'status' => $data['status'] ?? 'active',
        ]);
    }

    /**
     * Update an existing event.
     */
    public function updateEvent(Event $event, array $data): bool
    {
        if (isset($data['image_path']) && $event->image_path && $data['image_path'] !== $event->image_path) {
            Storage::disk('public')->delete($event->image_path);
        }

        return $event->update([
            'title' => $data['title'] ?? $event->title,
            'description' => $data['description'] ?? $event->description,
            'image_path' => $data['image_path'] ?? $event->image_path,
            'features' => $data['features'] ?? $event->features,
            'status' => $data['status'] ?? $event->status,
        ]);
    }

    /**
     * Delete an event.
     */
    public function deleteEvent(Event $event): bool
    {
        return $event->delete();
    }
}
