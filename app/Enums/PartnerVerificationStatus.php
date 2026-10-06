<?php

namespace App\Enums;

enum PartnerVerificationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case CorrectionRequested = 'correction_requested';
    case Resubmission = 'resubmission';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('admin.pending'),
            self::Approved => __('admin.approved'),
            self::Rejected => __('admin.rejected'),
            self::Suspended => __('admin.suspended'),
            self::CorrectionRequested => __('admin.correction_requested'),
            self::Resubmission => __('admin.resubmission'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Suspended => 'warning',
            self::CorrectionRequested => 'warning',
            self::Resubmission => 'success',
        };
    }
}
