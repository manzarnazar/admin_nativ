<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return match ($user->role) {
            UserRole::Admin, UserRole::Staff => true,
            UserRole::Partner => $user->partner !== null
                && $booking->property?->partner_id === $user->partner->id,
            UserRole::Customer => $booking->user_id === $user->id,
        };
    }
}
