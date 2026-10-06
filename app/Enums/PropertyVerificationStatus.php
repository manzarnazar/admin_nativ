<?php

namespace App\Enums;

enum PropertyVerificationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case CorrectionRequested = 'correction_requested';
    case Rejected = 'rejected';
    case Resubmission = 'resubmission';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('admin.pending'),
            self::Approved => __('admin.approved'),
            self::CorrectionRequested => __('admin.correction_requested'),
            self::Rejected => __('admin.rejected'),
            self::Resubmission => __('admin.resubmission'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Approved => 'success',
            self::CorrectionRequested => 'warning',
            self::Rejected => 'danger',
            self::Resubmission => 'success',
        };
    }
}
