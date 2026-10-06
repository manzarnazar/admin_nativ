<?php

namespace App\Enums;

enum PartnerSetupTask: string
{
    case PartnerProfile = 'partner_profile';
    case CancellationPolicy = 'cancellation_policy';

    public function label(): string
    {
        return match ($this) {
            self::PartnerProfile => 'Partner Profile',
            self::CancellationPolicy => 'Cancellation Policy',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PartnerProfile => 'Complete your partner profile details.',
            self::CancellationPolicy => 'Review and configure your cancellation policy.',
        };
    }

    public function buttonLabel(): string
    {
        return match ($this) {
            self::PartnerProfile => 'Complete Profile',
            self::CancellationPolicy => 'Set Policy',
        };
    }

    public function route(): string
    {
        return match ($this) {
            self::PartnerProfile => '/partner/partner-profile',
            self::CancellationPolicy => '/partner/cancellation-policy',
        };
    }
}
