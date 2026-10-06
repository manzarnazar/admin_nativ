<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\ManualRefundStatus;
use App\Enums\PartnerVerificationStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyVerificationStatus;
use App\Enums\RefundStatus;
use App\Enums\SetupTask;
use App\Enums\UserRole;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Models\Booking;
use App\Models\City;
use App\Models\CountrySetupTask;
use App\Models\ManualRefundRequest;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\Refund;
use App\Models\Tax;
use App\Models\User;
use App\Services\LegalPolicyService;
use App\Support\SystemMode;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class Dashboard extends BaseDashboard implements DeclaresTopbarControls
{
    use HasPagePermission;

    protected static string $routePath = '/';

    protected static string $permissionSlug = 'dashboard-analytics';

    protected static ?int $navigationSort = -2;

    public static function topbarControls(): array
    {
        return SystemMode::isMulti() ? ['property' => false] : [];
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        $svgPath = resource_path('svg/sidebar/SquaresFour.svg');

        return new HtmlString('<div class="w-6 h-6">'.file_get_contents($svgPath).'</div>');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user();
    }

    protected static function canViewAnalytics(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->can(static::$permissionSlug.'.view');
    }

    public ?string $bookingOverviewFilter = 'last_7_days';

    public ?string $platformUsageFilter = 'last_7_days';

    public ?string $roomsBookedFilter = 'last_7_days';

    public ?string $topRoomsFilter = 'last_7_days';

    public ?string $saasBookingOverviewFilter = 'last_7_days';

    public ?string $saasTopCitiesFilter = 'last_7_days';

    public ?string $saasRevenueFilter = 'last_7_days';

    public function mount(): void
    {
        $this->bookingOverviewFilter = 'last_7_days';
        $this->platformUsageFilter = 'last_7_days';
        $this->roomsBookedFilter = 'last_7_days';
        $this->topRoomsFilter = 'last_7_days';
        $this->saasBookingOverviewFilter = 'last_7_days';
        $this->saasTopCitiesFilter = 'last_7_days';
        $this->saasRevenueFilter = 'last_7_days';
    }

    public function getTitle(): string|Htmlable
    {
        return parent::getTitle();
    }

    public function getHeading(): string|Htmlable
    {
        if (! $this->isCountrySetupComplete()) {
            return '';
        }

        return '';
    }

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user()->load('currentCountry.refCountry');

        if (! static::canViewAnalytics()) {
            return [
                'userName' => $user->name,
                'greeting' => $this->resolveGreeting($user),
            ];
        }

        if (SystemMode::isMulti()) {
            if (! $this->isCountrySetupComplete()) {
                return $this->getChecklistViewData($user);
            }

            return [
                'saasBookingOverviewFilter' => $this->saasBookingOverviewFilter,
                'saasTopCitiesFilter' => $this->saasTopCitiesFilter,
                'saasRevenueFilter' => $this->saasRevenueFilter,
                ...$this->getSaasDashboardData($user),
            ];
        }

        if (! $this->isCountrySetupComplete()) {
            return $this->getChecklistViewData($user);
        }

        return [
            'bookingOverviewFilter' => $this->bookingOverviewFilter,
            'platformUsageFilter' => $this->platformUsageFilter,
            'roomsBookedFilter' => $this->roomsBookedFilter,
            'topRoomsFilter' => $this->topRoomsFilter,
            ...$this->getDashboardData($user),
        ];
    }

    protected function getChecklistViewData(User $user): array
    {
        $countryId = $user->current_country_id;

        if (! $countryId) {
            return [
                'isSetupComplete' => false,
                'checklistTasks' => [],
                'completedCount' => 0,
                'totalTasks' => count(SetupTask::cases()),
            ];
        }

        $this->evaluatePendingSetupTasks($countryId);

        $tasks = CountrySetupTask::query()
            ->forCountry($countryId)
            ->get()
            ->keyBy(fn (CountrySetupTask $task) => $task->task_key->value);

        $checklistTasks = collect(SetupTask::applicableCases())->map(function (SetupTask $task) use ($tasks) {
            $record = $tasks->get($task->value);

            return [
                'key' => $task->value,
                'label' => $task->label(),
                'description' => $task->description(),
                'buttonLabel' => $task->buttonLabel(),
                'route' => $task->route(),
                'isCompleted' => $record?->completed_at !== null,
                'isGlobal' => $task->isGlobal(),
            ];
        });

        $completedCount = $checklistTasks->where('isCompleted', true)->count();

        $dashboardData = [];

        if (! SystemMode::isMulti() && $completedCount >= count(SetupTask::applicableCases())) {
            $dashboardData = $this->getDashboardData($user);
        }

        return [
            'isSetupComplete' => $completedCount >= count(SetupTask::applicableCases()),
            'checklistTasks' => $checklistTasks,
            'completedCount' => $completedCount,
            'totalTasks' => count(SetupTask::cases()),
            'bookingOverviewFilter' => $this->bookingOverviewFilter,
            'platformUsageFilter' => $this->platformUsageFilter,
            'roomsBookedFilter' => $this->roomsBookedFilter,
            'topRoomsFilter' => $this->topRoomsFilter,
            ...$dashboardData,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getDashboardData(User $user): array
    {
        $propertyId = $user->current_branch_id;
        $today = now()->toDateString();

        $greeting = $this->resolveGreeting($user);

        // Property overview stats
        $baseQuery = Booking::query()
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $user->current_country_id));

        if ($propertyId) {
            $baseQuery->where('property_id', $propertyId);
        }

        $todayCheckIn = (clone $baseQuery)
            ->where('check_in', $today)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->count();

        $todayCheckOut = (clone $baseQuery)
            ->where('check_out', $today)
            ->whereIn('status', [BookingStatus::CheckedIn, BookingStatus::Completed])
            ->count();

        $totalRoomsQuery = PropertyRoom::query()
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $user->current_country_id));

        if ($propertyId) {
            $totalRoomsQuery->where('property_id', $propertyId);
        }

        $totalRooms = (clone $totalRoomsQuery)->sum('total_rooms');

        $todayOccupied = (int) (clone $baseQuery)
            ->where('check_in', '<=', $today)
            ->where('check_out', '>', $today)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->sum('booked_rooms');

        // Platform usage counts (from users, not bookings) - filtered by time
        $platformStartDate = match ($this->platformUsageFilter) {
            'last_7_days' => now()->subDays(7),
            'last_30_days' => now()->subDays(30),
            'this_month' => now()->startOfMonth(),
            'this_year' => now()->startOfYear(),
            default => now()->subDays(7),
        };

        $platformWebCount = User::query()
            ->where('platform', 'web')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $platformStartDate)
            ->count();

        $platformAppCount = User::query()
            ->whereIn('platform', ['android', 'ios'])
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $platformStartDate)
            ->count();

        // Summary stats — exclude expired/pending_payment from booking & customer counts;
        // revenue counts only successfully captured payments; refund sums completed
        // gateway refunds + transferred manual refunds.
        $excludedStatuses = [BookingStatus::Expired, BookingStatus::PendingPayment];

        $totalBookings = (clone $baseQuery)
            ->whereNotIn('status', $excludedStatuses)
            ->count();

        $totalActiveCustomers = (clone $baseQuery)
            ->whereNotIn('status', $excludedStatuses)
            ->distinct('user_id')
            ->count('user_id');

        $bookingIdsQuery = (clone $baseQuery)->select('id');

        $totalRevenue = Payment::query()
            ->where('status', PaymentTransactionStatus::Success)
            ->whereIn('booking_id', $bookingIdsQuery)
            ->sum('amount');

        $totalGatewayRefund = Refund::query()
            ->where('status', RefundStatus::Completed)
            ->whereHas('payment', fn (Builder $q) => $q->whereIn('booking_id', $bookingIdsQuery))
            ->sum('amount');

        $totalManualRefund = ManualRefundRequest::query()
            ->where('status', ManualRefundStatus::Transferred)
            ->whereIn('booking_id', $bookingIdsQuery)
            ->sum('amount');

        $totalRefund = $totalGatewayRefund + $totalManualRefund;

        // Currency symbol
        $currencySymbol = $user->currentCountry?->currency_symbol ?? '$';

        return [
            'userName' => $user->name,
            'greeting' => $greeting,
            'todayDate' => now()->format('F j, Y'),
            'todayCheckIn' => $todayCheckIn,
            'todayCheckOut' => $todayCheckOut,
            'totalRooms' => $totalRooms,
            'todayOccupied' => $todayOccupied,
            'platformWebCount' => $platformWebCount,
            'platformAppCount' => $platformAppCount,
            'totalBookings' => $totalBookings,
            'totalRevenue' => $currencySymbol.number_format($totalRevenue, 0),
            'totalRefund' => $currencySymbol.number_format($totalRefund, 0),
            'totalActiveCustomers' => number_format($totalActiveCustomers),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSaasDashboardData(User $user): array
    {
        $countryId = $user->current_country_id;
        $excludedStatuses = [BookingStatus::Expired, BookingStatus::PendingPayment];
        $currencySymbol = $user->currentCountry?->currency_symbol ?? '$';

        $inCountry = fn (Builder $q) => $q->whereHas('property', fn (Builder $p) => $p->where('country_id', $countryId));

        $confirmedBookings = Booking::query()
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $countryId))
            ->whereNotIn('status', $excludedStatuses)
            ->count();

        $grossRevenue = Payment::query()
            ->where('status', PaymentTransactionStatus::Success)
            ->whereHas('booking', $inCountry)
            ->sum('amount');

        $totalGatewayRefund = Refund::query()
            ->where('status', RefundStatus::Completed)
            ->whereHas('payment', fn (Builder $q) => $q->whereHas('booking', $inCountry))
            ->sum('amount');

        $totalManualRefund = ManualRefundRequest::query()
            ->where('status', ManualRefundStatus::Transferred)
            ->whereHas('booking', $inCountry)
            ->sum('amount');

        $completedRefundsAmount = $totalGatewayRefund + $totalManualRefund;

        $completedRefundsCount = Refund::query()
            ->where('status', RefundStatus::Completed)
            ->whereHas('payment', fn (Builder $q) => $q->whereHas('booking', $inCountry))
            ->count()
            + ManualRefundRequest::query()
                ->where('status', ManualRefundStatus::Transferred)
                ->whereHas('booking', $inCountry)
                ->count();

        $netRevenue = $grossRevenue - $completedRefundsAmount;

        $platformEarnings = Booking::query()
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $countryId))
            ->whereNotIn('status', $excludedStatuses)
            ->sum('commission_amount');

        $liveListings = Property::query()
            ->where('country_id', $countryId)
            ->where('status', PropertyStatus::Active)
            ->where('verification_status', PropertyVerificationStatus::Approved)
            ->count();

        $verifiedPartners = Partner::query()
            ->where('verification_status', PartnerVerificationStatus::Approved)
            ->whereHas('properties', fn (Builder $q) => $q->where('country_id', $countryId))
            ->count();

        $registeredUsers = User::query()
            ->where('role', UserRole::Customer)
            ->whereNull('deleted_at')
            ->count();

        $greeting = $this->resolveGreeting($user);

        return [
            'userName' => $user->name,
            'greeting' => $greeting,
            'todayDate' => now()->format('F j, Y'),
            'currentCountryId' => $countryId,
            'confirmedBookings' => number_format($confirmedBookings),
            'grossRevenue' => $currencySymbol.number_format($grossRevenue, 0),
            'netRevenue' => $currencySymbol.number_format($netRevenue, 0),
            'platformEarnings' => $currencySymbol.number_format($platformEarnings, 0),
            'completedRefundsCount' => number_format($completedRefundsCount),
            'completedRefundsAmount' => $currencySymbol.number_format($completedRefundsAmount, 0),
            'liveListings' => number_format($liveListings),
            'verifiedPartners' => number_format($verifiedPartners),
            'registeredUsers' => number_format($registeredUsers),
        ];
    }

    private function resolveGreeting(User $user): string
    {
        $timezones = $user->currentCountry?->refCountry?->timezones ?? [];
        $countryTimezone = is_array($timezones) && ! empty($timezones) && isset($timezones[0]['zoneName'])
            ? $timezones[0]['zoneName']
            : 'UTC';
        $hour = (int) now()->timezone($countryTimezone)->format('H');

        return match (true) {
            $hour < 12 => __('admin.good_morning'),
            $hour < 17 => __('admin.good_afternoon'),
            default => __('admin.good_evening'),
        };
    }

    public function getView(): string
    {
        if (! static::canViewAnalytics()) {
            return 'filament.pages.dashboard-greeting';
        }

        if (SystemMode::isMulti()) {
            if (! $this->isCountrySetupComplete()) {
                return 'filament.pages.dashboard-checklist';
                // return 'filament.pages.coming-soon';
            }

            return 'filament.pages.dashboard-saas';
            // return 'filament.pages.coming-soon';
        }

        if (! $this->isCountrySetupComplete()) {
            return 'filament.pages.dashboard-checklist';
        }

        return 'filament.pages.dashboard-main';
    }

    protected function isCountrySetupComplete(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user?->current_country_id) {
            return false;
        }

        return CountrySetupTask::isCountryFullySetup($user->current_country_id);
    }

    protected function evaluatePendingSetupTasks(int $countryId): void
    {
        if (! CountrySetupTask::query()->where('task_key', SetupTask::Cities->value)->forCountry($countryId)->completed()->exists()) {
            if (City::query()->forCountry($countryId)->exists()) {
                CountrySetupTask::markComplete(SetupTask::Cities, $countryId);
            }
        }

        if (! CountrySetupTask::query()->where('task_key', SetupTask::Taxes->value)->forCountry($countryId)->completed()->exists()) {
            if (Tax::query()->forCountry($countryId)->exists()) {
                CountrySetupTask::markComplete(SetupTask::Taxes, $countryId);
            }
        }

        if (! CountrySetupTask::query()->where('task_key', SetupTask::LegalPolicy->value)->whereNull('country_id')->completed()->exists()) {
            app(LegalPolicyService::class)->checkAndMarkComplete();
        }
    }
}
