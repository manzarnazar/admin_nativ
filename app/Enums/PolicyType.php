<?php

namespace App\Enums;

enum PolicyType: string
{
    case TermsCondition = 'terms_condition';
    case PrivacyPolicy = 'privacy_policy';
    case PlatformPolicy = 'platform_policy';
    case CancellationPolicy = 'cancellation_policy';
    case PartnerPolicy = 'partner_policy';

    public function label(): string
    {
        return match ($this) {
            self::TermsCondition => 'Terms & Condition',
            self::PrivacyPolicy => 'Privacy Policy',
            self::PlatformPolicy => 'Platform Policy',
            self::CancellationPolicy => 'Cancellation Policy',
            self::PartnerPolicy => 'Partner Policy',
        };
    }
}
