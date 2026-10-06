<?php

namespace App\Actions;

use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Mail\PartnerBookingCancelledMailable;
use App\Mail\PartnerNewBookingMailable;
use App\Models\Booking;
use App\Models\Partner;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBookingNotificationsAction
{
    /**
     * Send new-booking notifications to all admins/staff + the customer.
     */
    public function sendNew(Booking $booking): void
    {
        $booking->loadMissing(['customer', 'property', 'propertyRoom.roomType']);

        $admins = User::query()
            ->whereIn('role', [UserRole::Admin, UserRole::Staff])
            ->get();

        if ($admins->isNotEmpty()) {
            $customer = $booking->customer;
            $property = $booking->property;
            $roomType = $booking->propertyRoom?->roomType;

            $adminTitle = 'New Booking Received';
            $adminBody = ($customer?->name ?? 'Guest')
                .' booked '.($roomType?->name ?? 'a room')
                .' in '.($property?->name ?? 'a property')
                .' for '.$booking->check_in->format('M d, Y').'.';

            app(NotificationService::class)->send(
                type: 'new_booking',
                title: $adminTitle,
                body: $adminBody,
                userIds: $admins->pluck('id'),
                data: [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                ],
                link: '/bookings/'.$booking->id.'/view',
                countryId: $booking->property?->country_id,
            );
        }

        if ($booking->user_id) {
            try {
                $notificationService = app(NotificationService::class);
                $property = $booking->property;

                $title = __('notifications.booking_confirmed_title');
                $body = __('notifications.booking_confirmed_body', [
                    'booking_number' => $booking->booking_number,
                    'property' => $property?->name ?? 'the property',
                    'date' => $booking->check_in->format('M d, Y'),
                ]);

                $notificationData = [
                    'type' => 'booking',
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                ];

                $notificationService->send(
                    type: 'booking_confirmation',
                    title: $title,
                    body: $body,
                    userIds: $booking->user_id,
                    data: $notificationData,
                    category: NotificationCategory::BookingUpdates,
                    link: $booking->booking_number,
                    countryId: $property?->country_id,
                );

                $notificationService->sendPush(
                    title: $title,
                    body: $body,
                    userIds: $booking->user_id,
                    data: $notificationData,
                    category: NotificationCategory::BookingUpdates,
                    link: $booking->booking_number,
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send booking confirmation notification', [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->notifyPartner(
            $booking,
            'email_new_booking',
            fn (Partner $partner): PartnerNewBookingMailable => new PartnerNewBookingMailable($partner, $booking),
        );
    }

    /**
     * Send a status-change notification (cancelled, checked_in, completed) to the customer.
     */
    public function sendStatusUpdate(Booking $booking, string $type): void
    {
        if (! $booking->user_id) {
            return;
        }

        $booking->loadMissing(['property']);
        $property = $booking->property?->name ?? 'the property';

        $title = __("notifications.{$type}_title");
        $body = __("notifications.{$type}_body", [
            'booking_number' => $booking->booking_number,
            'property' => $property,
        ]);

        $data = [
            'type' => 'booking',
            'booking_id' => $booking->id,
            'booking_number' => $booking->booking_number,
        ];

        try {
            $notificationService = app(NotificationService::class);

            $notificationService->send(
                type: $type,
                title: $title,
                body: $body,
                userIds: $booking->user_id,
                data: $data,
                category: NotificationCategory::BookingUpdates,
                link: $booking->booking_number,
                countryId: $booking->property?->country_id,
            );

            $notificationService->sendPush(
                title: $title,
                body: $body,
                userIds: $booking->user_id,
                data: $data,
                category: NotificationCategory::BookingUpdates,
                link: $booking->booking_number,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send booking status notification', [
                'booking_id' => $booking->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }

        if ($type === 'booking_cancelled') {
            $this->notifyPartner(
                $booking,
                'email_cancellations',
                fn (Partner $partner): PartnerBookingCancelledMailable => new PartnerBookingCancelledMailable($partner, $booking),
            );
        }
    }

    /**
     * Email the booking's property owner, if there is one (single-mode properties
     * have no partner) and they haven't opted out via their notification preferences.
     */
    private function notifyPartner(Booking $booking, string $preferenceKey, callable $mailableFactory): void
    {
        $partner = $booking->property?->partner;

        if (! $partner || ! $partner->wantsEmailNotification($preferenceKey)) {
            return;
        }

        $partner->loadMissing('user');

        if (! $partner->user?->email) {
            return;
        }

        try {
            Mail::to($partner->user->email)->queue($mailableFactory($partner));
        } catch (\Throwable $e) {
            Log::error('Failed to send partner notification email', [
                'booking_id' => $booking->id,
                'preference_key' => $preferenceKey,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
