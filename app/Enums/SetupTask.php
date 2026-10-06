<?php

namespace App\Enums;

use App\Support\SystemMode;

enum SetupTask: string
{
    case AdminProfile = 'admin_profile';
    case Cities = 'cities';
    case Taxes = 'taxes';
    case CancellationPolicy = 'cancellation_policy';
    case LegalPolicy = 'legal_policy';
    case Commission = 'commission';
    case PropertyTypeSetup = 'property_type_setup';

    public function label(): string
    {
        return match ($this) {
            self::AdminProfile => 'Admin Profile',
            self::Cities => 'Add Cities',
            self::Taxes => 'Tax Configuration',
            self::CancellationPolicy => 'Cancellation Policy',
            self::LegalPolicy => 'Legal Policy',
            self::Commission => 'Commission Setup',
            self::PropertyTypeSetup => 'Property Type Setup',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AdminProfile => 'Set up your admin profile details.',
            self::Cities => 'Configure the cities your platform will operate in.',
            self::Taxes => 'Set up applicable taxes and fees.',
            self::CancellationPolicy => 'Define standard cancellation rules.',
            self::LegalPolicy => 'Add terms & condition and privacy policy.',
            self::Commission => 'Define Global and Property-level commissions.',
            self::PropertyTypeSetup => 'Manage tax rules based on country, and property type.',
        };
    }

    public function buttonLabel(): string
    {
        return match ($this) {
            self::AdminProfile => 'Complete Profile',
            self::Cities => 'Add Cities',
            self::Taxes => 'Manage Taxes',
            self::CancellationPolicy => 'Set Policy',
            self::LegalPolicy => 'Add Policies',
            self::Commission => 'Setup Commission',
            self::PropertyTypeSetup => 'Manage Types',
        };
    }

    public function route(): string
    {
        return match ($this) {
            self::AdminProfile => '/admin-profile',
            self::Cities => '/cities',
            self::Taxes => '/taxes',
            self::CancellationPolicy => SystemMode::isMulti() ? '/cancellation-policies' : '/manage-cancellation-policy',
            self::LegalPolicy => '/legal-policies',
            self::Commission => '/commission',
            self::PropertyTypeSetup => '/property-type',
        };
    }

    public function isGlobal(): bool
    {
        return in_array($this, [self::AdminProfile, self::LegalPolicy, self::PropertyTypeSetup]);
    }

    public function isMultiModeOnly(): bool
    {
        return in_array($this, [self::Commission, self::PropertyTypeSetup]);
    }

    /**
     * @return array<self>
     */
    public static function countryScoped(): array
    {
        return [
            self::Cities,
            self::Taxes,
            self::CancellationPolicy,
            self::Commission,
        ];
    }

    /**
     * Returns the tasks applicable to the current system mode.
     *
     * @return array<self>
     */
    public static function applicableCases(): array
    {
        return array_values(
            array_filter(self::cases(), fn (self $task) => ! ($task->isMultiModeOnly() && SystemMode::isSingle()))
        );
    }
}
