<?php

namespace App\Filament\Partner\Pages;

use App\Enums\BookingStatus;
use App\Enums\ManualRefundStatus;
use App\Enums\PartnerSetupTask;
use App\Enums\PartnerVerificationStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\Country;
use App\Models\ManualRefundRequest;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PropertyRoom;
use App\Models\Refund;
use App\Models\User;
use App\Support\PartnerContext;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class Dashboard extends Page
{
    protected static ?string $slug = '/';

    protected static ?int $navigationSort = -2;

    protected string $view = 'filament.partner.pages.dashboard';

    public ?string $bookingOverviewFilter = 'last_7_days';

    public ?string $roomsBookedFilter = 'last_7_days';

    public ?string $topRoomsFilter = 'last_7_days';

    public function mount(): void
    {
        $this->bookingOverviewFilter = 'last_7_days';
        $this->roomsBookedFilter = 'last_7_days';
        $this->topRoomsFilter = 'last_7_days';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.dashboard');
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        $svgPath = resource_path('svg/sidebar/dashboard.svg');

        return new HtmlString('<div class="w-6 h-6">'.file_get_contents($svgPath).'</div>');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && $user->role === UserRole::Partner;
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.dashboard');
    }

    public function getHeading(): string|Htmlable
    {
        return new HtmlString('');
    }

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Partner|null $partner */
        $partner = $user->partner;

        $isApproved = $partner?->verification_status === PartnerVerificationStatus::Approved;

        $checklistTasks = collect();
        $completedCount = 0;
        $totalTasks = 0;
        $isSetupComplete = false;

        if ($isApproved) {
            $partnerCountryIds = $partner->countries()->pluck('countries.id');

            $checklistTasks = collect(PartnerSetupTask::cases())
                // Cancellation Policy is optional — the admin's default policy applies
                // automatically when a partner doesn't set their own, so it shouldn't
                // gate setup completion. Commented out for now; remove this reject()
                // to bring the card back (PartnerSetupTask::CancellationPolicy case
                // is left intact).
                ->reject(fn (PartnerSetupTask $task): bool => $task === PartnerSetupTask::CancellationPolicy)
                ->map(function (PartnerSetupTask $task) use ($user, $partnerCountryIds): array {
                    $isCompleted = match ($task) {
                        PartnerSetupTask::PartnerProfile => filled($user->first_name),
                        PartnerSetupTask::CancellationPolicy => $partnerCountryIds->isNotEmpty()
                            && CancellationPolicy::query()
                                ->whereIn('country_id', $partnerCountryIds)
                                ->whereHas('rules')
                                ->exists(),
                    };

                    return [
                        'key' => $task->value,
                        'label' => $task->label(),
                        'description' => $task->description(),
                        'buttonLabel' => $task->buttonLabel(),
                        'route' => $task->route(),
                        'isCompleted' => $isCompleted,
                    ];
                });

            $completedCount = $checklistTasks->where('isCompleted', true)->count();
            $totalTasks = $checklistTasks->count();
            $isSetupComplete = $completedCount >= $totalTasks;
        }

        $dashboardData = [];

        if ($isApproved && $isSetupComplete) {
            $dashboardData = $this->getDashboardData($partner);
        }

        return [
            'userName' => $user->name ?? '',
            'greeting' => $this->resolveGreeting(),
            'checklistTasks' => $checklistTasks,
            'completedCount' => $completedCount,
            'totalTasks' => $totalTasks,
            'isSetupComplete' => $isSetupComplete,
            'isApproved' => $isApproved,
            'bookingOverviewFilter' => $this->bookingOverviewFilter,
            'roomsBookedFilter' => $this->roomsBookedFilter,
            'topRoomsFilter' => $this->topRoomsFilter,
            ...$dashboardData,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getDashboardData(Partner $partner): array
    {
        $countryId = PartnerContext::currentCountryId($partner);
        $propertyId = $countryId ? PartnerContext::currentPropertyId($partner, $countryId) : null;
        $today = now()->toDateString();

        $baseQuery = Booking::query()
            ->whereHas('property', fn (Builder $q) => $q->where('partner_id', $partner->id)->where('country_id', $countryId));

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
            ->whereHas('property', fn (Builder $q) => $q->where('partner_id', $partner->id)->where('country_id', $countryId));

        if ($propertyId) {
            $totalRoomsQuery->where('property_id', $propertyId);
        }

        $totalRooms = (clone $totalRoomsQuery)->sum('total_rooms');

        $todayOccupied = (int) (clone $baseQuery)
            ->where('check_in', '<=', $today)
            ->where('check_out', '>', $today)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->sum('booked_rooms');

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

        $currencySymbol = ($countryId ? Country::query()->where('id', $countryId)->value('currency_symbol') : null) ?? '₹';

        return [
            'todayDate' => now()->format('F j, Y'),
            'todayCheckIn' => $todayCheckIn,
            'todayCheckOut' => $todayCheckOut,
            'totalRooms' => $totalRooms,
            'todayOccupied' => $todayOccupied,
            'totalBookings' => $totalBookings,
            'totalRevenue' => $currencySymbol.number_format($totalRevenue, 0),
            'totalRefund' => $currencySymbol.number_format($totalRefund, 0),
            'totalActiveCustomers' => number_format($totalActiveCustomers),
        ];
    }

    private function resolveGreeting(): string
    {
        $hour = (int) now()->format('H');

        return match (true) {
            $hour < 12 => __('admin.good_morning'),
            $hour < 17 => __('admin.good_afternoon'),
            default => __('admin.good_evening'),
        };
    }
}
