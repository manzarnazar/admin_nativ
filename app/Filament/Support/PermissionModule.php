<?php

namespace App\Filament\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionModule
{
    public const array DEFAULT_ACTIONS = ['view', 'create', 'edit', 'delete'];

    public function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly string $description,
        public readonly array $actions = self::DEFAULT_ACTIONS,
        public readonly bool $multiModeOnly = false,
    ) {}

    public static function all(): array
    {
        return [
            new self('dashboard-analytics', 'Dashboard Analytics', 'View main overview stats.'),
            new self('staff-management', 'Staff Management', 'Manage internal staff accounts.'),
            new self('roles-permissions', 'Roles & Permissions', 'Define access levels.'),
            new self('all-customers', 'All Customers', 'Manage end-user accounts.'),
            new self('wallet-transactions', 'Payment Transactions', 'Manage customer payment transactions.'),
            new self('manual-refunds', 'Manual Refunds', 'Process customer manual bank transfer refunds.'),
            // new self('property-wallet-transactions', 'Property Payment Transactions', 'Manage property payment transactions.'),
            new self('all-properties', 'All Properties', 'Manage property listings.'),
            new self('room-types', 'Room Types', 'Configure room types.'),
            new self('amenities-facilities', 'Amenities & Facilities', 'Manage global amenities.'),
            new self('property-rules', 'Property Rules', 'Define house rules.'),
            new self('all-bookings', 'All Bookings', 'Manage customer reservations.'),
            new self('event-management', 'Event Management', 'Manage event contents.'),
            new self('reviews-ratings', 'Reviews & Ratings', 'Moderate user reviews.'),
            new self('country-settings', 'Country Settings', 'Manage supported countries.'),
            new self('city-management', 'City Management', 'Manage cities and regions.'),
            new self('currency-settings', 'Currency Settings', 'Manage exchange rates.'),
            new self('tax-rules', 'Tax Rules', 'Configure global tax rates.'),
            new self('promo-codes', 'Promo Codes', 'Manage discount campaigns.'),
            new self('home-page-content', 'Home Page Content', 'Manage homepage content.'),
            new self('push-notifications', 'Push Notifications', 'Send system alerts.'),
            new self('blogs-articles', 'Blogs & Articles', 'Manage help center.'),
            new self('faq-management', 'FAQ Management', 'Manage help center.'),
            new self('general-settings', 'General Settings', 'App-wide configurations.'),
            new self('commission-rules', 'Commission Rules', 'Set platform commission rates.', multiModeOnly: true),
            new self('property-verification', 'Property Verification', 'Review and approve property submissions.', ['view', 'edit'], multiModeOnly: true),
            new self('partner-management', 'Partner Management', 'Manage partner accounts and verification.', multiModeOnly: true),
            new self('withdrawal-requests', 'Withdrawal Requests', 'Review and approve partner withdrawal requests.', ['view', 'edit'], multiModeOnly: true),
            new self('property-payouts', 'Property Payouts', 'View property payout reports.', ['view'], multiModeOnly: true),
            new self('reports', 'Reports', 'View platform reports.', ['view'], multiModeOnly: true),
            new self('cancellation-policy-types', 'Cancellation Policy Types', 'Configure per-property-type cancellation policies.', multiModeOnly: true),
            new self('homepage-sections', 'Homepage Sections', 'Manage homepage section layout.', multiModeOnly: true),
            new self('all-property-types', 'Property Type Catalog', 'Manage which property types are enabled per country.', multiModeOnly: true),
        ];
    }

    public static function actions(): array
    {
        return self::DEFAULT_ACTIONS;
    }

    public function permissionName(string $action): string
    {
        return $this->slug.'.'.$action;
    }

    public static function allPermissionNames(): array
    {
        $names = [];
        foreach (self::all() as $module) {
            foreach ($module->actions as $action) {
                $names[] = $module->permissionName($action);
            }
        }

        return $names;
    }

    /**
     * Maps the first URL segment of a Filament page slug to its permission module slug.
     * Used by both EnforceStaffPermissions middleware and HasPagePermission trait.
     */
    public static function pageSlugToPermission(): array
    {
        return [
            'staff-management' => 'staff-management',
            'roles-permissions' => 'roles-permissions',
            'all-customers' => 'all-customers',
            'customers' => 'all-customers',
            'all-bookings' => 'all-bookings',
            'bookings' => 'all-bookings',
            'todays-check-ins' => 'all-bookings',
            'todays-checkouts' => 'all-bookings',
            'availability-calendar' => 'all-bookings',
            'all-rooms' => 'room-types',
            'room-types' => 'room-types',
            'room-inventory' => 'room-types',
            'facilities' => 'amenities-facilities',
            'properties' => 'all-properties',
            'property-type' => 'all-properties',
            'property-rules' => 'property-rules',
            'all-events' => 'event-management',
            'event-inquiries' => 'event-management',
            'guest-reviews' => 'reviews-ratings',
            'countries' => 'country-settings',
            'cities' => 'city-management',
            'taxes' => 'tax-rules',
            'promo-codes' => 'promo-codes',
            'marketing-notifications' => 'push-notifications',
            'manage-homepage' => 'home-page-content',
            'banners' => 'home-page-content',
            'manage-about' => 'home-page-content',
            'system-settings' => 'general-settings',
            'activity-logs' => 'general-settings',
            'manage-languages' => 'general-settings',
            'payment-gateway-settings' => 'general-settings',
            'social-media' => 'general-settings',
            'seo-settings' => 'general-settings',
            'registration-fields' => 'general-settings',
            'help-support' => 'faq-management',
            'faqs' => 'faq-management',
            'legal-policies' => 'faq-management',
            'blogs' => 'blogs-articles',
            'payments' => 'wallet-transactions',
            'currency-manage' => 'currency-settings',
            'manage-cancellation-policy' => 'property-rules',
            'user-queries' => 'general-settings',
            'manual-refunds' => 'manual-refunds',
            'refer-earn' => 'general-settings',
            'system-update' => 'general-settings',

            // Multi-mode: new modules
            'commission' => 'commission-rules',
            'property-verification' => 'property-verification',
            'property-verification-detail' => 'property-verification',
            'all-partners' => 'partner-management',
            'all-partners-detail' => 'partner-management',
            'partner-verification' => 'partner-management',
            'partner-verification-detail' => 'partner-management',
            'partner-registration-fields' => 'partner-management',
            'withdrawal-requests' => 'withdrawal-requests',
            'property-payout' => 'property-payouts',
            'all-reports' => 'reports',
            'cancellation-policies' => 'cancellation-policy-types',
            'homepage-sections' => 'homepage-sections',
            'property-types' => 'all-property-types',

            // Multi-mode: fold into existing modules
            'reserved-bookings' => 'all-bookings',
            'pay-at-property' => 'all-bookings',
            'cancellations-refunds' => 'all-bookings',
            'review-removal-requests' => 'reviews-ratings',
            'removed-reviews' => 'reviews-ratings',
            'become-partner-faq' => 'faq-management',
            'all-properties' => 'all-properties',
        ];
    }

    public static function seed(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        foreach (self::allPermissionNames() as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => $guard]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
