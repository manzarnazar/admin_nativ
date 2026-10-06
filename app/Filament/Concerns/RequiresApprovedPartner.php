<?php

namespace App\Filament\Concerns;

use App\Enums\PartnerVerificationStatus;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Auth;

trait RequiresApprovedPartner
{
    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user || $user->role !== UserRole::Partner) {
            return false;
        }

        return $user->partner?->verification_status === PartnerVerificationStatus::Approved;
    }
}
