<?php

namespace App\Enums;

enum ManualRefundStatus: string
{
    case PendingReview = 'pending_review';
    case Transferred = 'transferred';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'Pending Review',
            self::Transferred   => 'Transferred',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingReview => 'warning',
            self::Transferred   => 'success',
        };
    }
}
