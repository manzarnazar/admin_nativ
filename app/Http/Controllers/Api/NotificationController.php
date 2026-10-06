<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\UserNotificationPreference;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function getNotification(Request $request)
    {
        $user = $request->user();

        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);
        $page = (int) ($offset / $limit) + 1;

        $query = Notification::query();

        if ($user) {
            // Logged in: fetch specific notifications + general ones
            $query->where(function ($q) use ($user) {
                $q->whereHas('users', fn ($u) => $u->where('user_id', $user->id))
                    ->orWhere('is_general', true);
            })->with(['users' => fn ($q) => $q->where('user_id', $user->id)]);
        } else {
            // Guest: only general ones
            $query->where('is_general', true);
        }

        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function ($notification) use ($user) {
            $readAt = null;
            if ($user && $notification->users->isNotEmpty()) {
                $readAt = $notification->users->first()->pivot->read_at;
            }

            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'body' => $notification->body,
                'image' => $notification->image,
                'link' => $notification->link,
                'data' => $notification->data,
                'read_at' => $readAt ? Carbon::parse($readAt)->toIso8601String() : null,
                'created_at' => $notification->created_at?->toIso8601String(),
            ];
        })->toArray();

        return $this->paginatedResponse($paginator, $items, 'Notifications fetched successfully', [], $offset);
    }

    /**
     * Get user notification preferences status.
     */
    public function getPreferences(Request $request)
    {
        $user = $request->user();
        $preferences = $user->notificationPreferences()->get();

        $data = collect(NotificationCategory::cases())->map(function ($category) use ($preferences) {
            $pref = $preferences->where('category', $category)->first();

            return [
                'category' => $category->value,
                'label' => $category->label(),
                'is_enabled' => $pref ? (bool) $pref->is_enabled : true, // Default to true
            ];
        });

        return $this->successResponse($data, 'Preferences fetched successfully');
    }

    /**
     * Update a specific notification preference.
     */
    public function updatePreference(Request $request)
    {
        $request->validate([
            'category' => ['required', Rule::enum(NotificationCategory::class)],
            'is_enabled' => 'required|boolean',
        ]);

        $user = $request->user();

        $preference = UserNotificationPreference::updateOrCreate(
            ['user_id' => $user->id, 'category' => $request->category],
            ['is_enabled' => $request->is_enabled]
        );

        return $this->successResponse($preference, 'Preference updated successfully');
    }
}
