<?php

namespace App\Filament\Enums;

use App\Support\SystemMode;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasIcon, HasLabel
{
    case Bookings = 'Bookings';
    case RoomManagement = 'Room Management';
    case GuestReviews = 'Guest Reviews';
    case PropertyManagement = 'Property Management';
    case ContentManagement = 'Content Management';
    case Marketing = 'Marketing';
    case EventManagement = 'Event Management';
    case CustomerManage = 'Customer Manage';
    case StaffAccess = 'Staff & Access';
    case LocationPolicies = 'Location & Policies';
    case Settings = 'Settings';
    // Multi-mode only
    case Partners = 'Partners';
    case Finance = 'Finance';
    case Reports = 'Reports';

    public function getLabel(): ?string
    {
        $isMulti = SystemMode::isMulti();

        return match ($this) {
            self::Bookings => __('admin.bookings'),
            self::RoomManagement => __('admin.room_management'),
            self::GuestReviews => $isMulti ? __('admin.review_monitoring') : __('admin.guest_reviews'),
            self::PropertyManagement => $isMulti ? __('admin.properties') : __('admin.property_management'),
            self::ContentManagement => $isMulti ? __('admin.content_manage') : __('admin.content_management'),
            self::Marketing => __('admin.marketing'),
            self::EventManagement => __('admin.event_management'),
            self::CustomerManage => __('admin.customer_manage'),
            self::StaffAccess => __('admin.staff_access'),
            self::LocationPolicies => $isMulti ? __('admin.location_taxes') : __('admin.location_policies'),
            self::Settings => __('admin.settings'),
            self::Partners => __('admin.partners'),
            self::Finance => __('admin.finance'),
            self::Reports => __('admin.reports'),
        };
    }

    public function getIcon(): string|\BackedEnum|null
    {
        return null;
    }

    /**
     * Filament sorts cross-group navigation order by the enum's OWN case-declaration
     * order whenever a page's getNavigationGroup() returns the raw enum case — the
     * panel's navigationGroups() registration order is silently ignored in that case.
     * Returning the resolved label STRING instead (multi-mode only) makes Filament fall
     * back to registration-array order, which is how AdminPanelProvider customizes
     * group order for multi-mode without touching single-mode's (enum-order-driven)
     * behavior at all.
     */
    public static function resolve(self $case): string|self
    {
        return SystemMode::isMulti() ? $case->getLabel() : $case;
    }
}
