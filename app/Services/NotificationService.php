<?php

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\RefundStatus;
use App\Mail\RefundNotificationMailable;
use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Kreait\Firebase\Messaging\WebPushConfig;
use Kreait\Laravel\Firebase\Facades\Firebase;

class NotificationService
{
    /**
     * Send a notification to one or many users.
     *
     * @param  array|int|Collection  $userIds
     * @param  array  $data  Additional JSON data
     */
    public function send(string $type, string $title, string $body, $userIds, array $data = [], ?NotificationCategory $category = null, ?string $image = null, ?string $link = null, ?int $countryId = null): Notification
    {
        return DB::transaction(function () use ($type, $title, $body, $userIds, $data, $category, $image, $link, $countryId) {
            // Debug logs
            Log::info('Attempting to create notification', ['type' => $type, 'data' => $data]);

            // 1. Create the notification message record once
            $notification = Notification::create([
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'image' => $image,
                'link' => $link,
                'data' => $data,
                'country_id' => $countryId,
            ]);

            Log::info('Notification created successfully', ['id' => $notification->id]);

            // 2. Prepare user IDs (normalize to array)
            if ($userIds instanceof Collection) {
                $userIds = $userIds->toArray();
            } elseif (! is_array($userIds)) {
                $userIds = [$userIds];
            }

            // 3. Filter users by preference if category is provided
            if ($category && ! empty($userIds)) {
                $userIds = User::whereIn('id', $userIds)
                    ->whereDoesntHave('notificationPreferences', function ($query) use ($category) {
                        $query->where('category', $category->value)
                            ->where('is_enabled', false);
                    })
                    ->pluck('id')
                    ->toArray();
            }

            // 4. Link users via pivot table (efficiently)
            if (! empty($userIds)) {
                $notification->users()->attach($userIds);
            }

            return $notification;
        });
    }

    /**
     * Mark a notification as read for a specific user.
     */
    public function markAsRead(string $notificationId, int $userId): void
    {
        DB::table('notification_user')
            ->where('notification_id', $notificationId)
            ->where('user_id', $userId)
            ->update(['read_at' => now()]);
    }

    /**
     * Mark all notifications as read for a specific user.
     */
    public function markAllAsRead(int $userId): void
    {
        DB::table('notification_user')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Get unread notifications count for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        return DB::table('notification_user')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Send refund status notification (in-app + push) to the booking's customer.
     */
    public function sendRefundNotification(Refund $refund, RefundStatus $status): void
    {
        $refund->loadMissing(['payment.booking.property']);
        $booking = $refund->payment?->booking;

        if (! $booking || ! $booking->user_id) {
            return;
        }

        $property = $booking->property?->name ?? 'the property';
        $amount = $booking->currency_symbol.number_format((float) $refund->amount, 2);
        $type = $status === RefundStatus::Completed ? 'refund_completed' : 'refund_failed';

        $title = __("notifications.{$type}_title");
        $body = __("notifications.{$type}_body", [
            'amount' => $amount,
            'booking_number' => $booking->booking_number,
            'property' => $property,
        ]);

        $data = [
            'type' => $type,
            'booking_id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'refund_id' => $refund->id,
        ];

        try {
            $this->send(
                type: $type,
                title: $title,
                body: $body,
                userIds: $booking->user_id,
                data: $data,
                category: NotificationCategory::BookingUpdates,
                link: $booking->booking_number,
                countryId: $booking->property?->country_id,
            );

            $this->sendPush(
                title: $title,
                body: $body,
                userIds: $booking->user_id,
                data: $data,
                category: NotificationCategory::BookingUpdates,
                link: $booking->booking_number,
            );

            $userEmail = User::where('id', $booking->user_id)->value('email');

            if ($userEmail) {
                Mail::to($userEmail)->queue(new RefundNotificationMailable($refund, $status));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send refund notification', [
                'refund_id' => $refund->id,
                'status' => $status->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send an FCM push notification to one or many users.
     *
     * When $category is provided, recipients with that category explicitly
     * disabled (user_notification_preferences.is_enabled = false) are excluded
     * before any FCM call is made.
     *
     * @param  array  $data  Extra key-value pairs sent in the FCM data payload
     */
    public function sendPush(string $title, string $body, array|int|Collection $userIds, array $data = [], ?NotificationCategory $category = null, ?string $image = null, ?string $link = null): void
    {
        if ($userIds instanceof Collection) {
            $userIds = $userIds->toArray();
        } elseif (! is_array($userIds)) {
            $userIds = [$userIds];
        }

        if (empty($userIds)) {
            return;
        }

        if ($category !== null) {
            $userIds = User::whereIn('id', $userIds)
                ->whereDoesntHave('notificationPreferences', function ($query) use ($category) {
                    $query->where('category', $category->value)
                        ->where('is_enabled', false);
                })
                ->pluck('id')
                ->toArray();

            if (empty($userIds)) {
                return;
            }
        }

        $tokens = User::query()
            ->whereIn('id', $userIds)
            ->with('fcmTokens')
            ->get()
            ->flatMap(fn (User $u) => $u->fcmTokens->pluck('token'))
            ->filter()
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $messaging = Firebase::messaging();
            $notification = FcmNotification::create($title, $body, $image);

            $stringData = collect($data)
                ->map(fn ($v) => (string) $v)
                ->all();

            if ($link !== null) {
                $stringData['redirect_url'] = $link;
            }

            $androidConfig = AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => array_filter([
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'channel_id' => 'high_importance_channel',
                    'image' => $image,
                ]),
            ]);

            $apnsData = [
                'headers' => ['apns-priority' => '10'],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                        'content-available' => 1,
                        'mutable-content' => 1,
                    ],
                ],
            ];

            if ($image) {
                $apnsData['fcm_options'] = ['image' => $image];
            }

            $apnsConfig = ApnsConfig::fromArray($apnsData);

            $webPushConfig = WebPushConfig::fromArray([
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ]);

            Log::info('FCM sending push', [
                'title' => $title,
                'image' => $image,
                'user_ids' => $userIds,
                'token_count' => $tokens->count(),
                'data' => $stringData,
            ]);

            foreach ($tokens->chunk(500) as $chunk) {
                $messages = collect($chunk)->map(
                    fn (string $token) => CloudMessage::new()
                        ->withNotification($notification)
                        ->withData($stringData)
                        ->withAndroidConfig($androidConfig)
                        ->withApnsConfig($apnsConfig)
                        ->withWebPushConfig($webPushConfig)
                        ->toToken($token)
                )->all();

                $report = $messaging->sendAll($messages);

                $failureDetails = collect($report->failures()->getItems())->map(function ($item) {
                    $raw = $item->message()->jsonSerialize();
                    $token = $raw['token'] ?? null;
                    $userId = $token ? FcmToken::where('token', $token)->value('user_id') : null;

                    return [
                        'token' => $token,
                        'user_id' => $userId,
                        'error' => $item->error()?->getMessage(),
                        'error_class' => $item->error() ? get_class($item->error()) : null,
                    ];
                });

                $invalidTokens = $failureDetails
                    ->filter(fn ($d) => $d['error_class'] === NotFound::class)
                    ->pluck('token')
                    ->filter()
                    ->all();

                if (! empty($invalidTokens)) {
                    FcmToken::whereIn('token', $invalidTokens)->delete();
                }

                Log::info('FCM send result', [
                    'successes' => $report->successes()->count(),
                    'failures' => $report->failures()->count(),
                    'failure_details' => $failureDetails->all(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('FCM push notification failed', [
                'user_ids' => $userIds,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
