<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasAdminDemoGuard;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Booking;
use App\Models\Country;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerView extends Page implements DeclaresTopbarControls, HasTable
{
    use HasAdminDemoGuard;
    use InteractsWithTable;

    protected static ?string $slug = 'customers/{record}/view';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.customer-view';

    public int $customerId;

    #[Url(as: 'tab')]
    public string $activeTab = 'bookings';

    public string $filterPeriod = 'this_year';

    public ?string $customStartDate = null;

    public ?string $customEndDate = null;

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getCustomer()->name;
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::CustomerManage);
    }

    public function mount(int $record): void
    {
        // Include soft-deleted users so admins can still open a profile after the
        // customer deletes their account (booking history etc. is still relevant).
        $user = User::query()->withTrashed()->findOrFail($record);
        $this->customerId = $user->id;
    }

    public function getCustomer(): User
    {
        return User::query()->withTrashed()->findOrFail($this->customerId);
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // ── Stats ──────────────────────────────────────────────────────────────

    private function countryBookingsQuery(): Builder
    {
        /** @var User $admin */
        $admin = auth()->user();

        return Booking::query()
            ->where('user_id', $this->customerId)
            ->whereHas('property', fn ($q) => $q->where('country_id', $admin->current_country_id));
    }

    public function getCustomerStats(): array
    {
        $customer = $this->getCustomer();
        $baseQuery = $this->countryBookingsQuery();

        // "Total Spent" = fully-paid bookings on still-valid stays.
        // Both conditions needed:
        //   - payment_status = paid → excludes partial-paid, unpaid COD, expired/pending
        //   - status IN (confirmed, checked_in, completed) → excludes cancelled-but-not-yet-refunded
        //     (cancellation with a pending refund still shows payment_status=paid until the refund settles)
        $totalSpent = (clone $baseQuery)
            ->whereIn('status', [
                BookingStatus::Confirmed,
                BookingStatus::CheckedIn,
                BookingStatus::Completed,
            ])
            ->where('payment_status', PaymentStatus::Paid)
            ->sum('total_amount');

        $totalBookings = (clone $baseQuery)->count();

        $totalCancelled = (clone $baseQuery)
            ->where('status', BookingStatus::Cancelled)
            ->count();

        $totalRefunded = (clone $baseQuery)
            ->where('payment_status', 'refunded')
            ->sum('total_amount');

        $joinedPlatform = match ($customer->platform) {
            'web' => 'Website',
            'android', 'ios' => 'Application',
            default => $customer->auth_provider === 'admin' ? 'Admin' : 'Website',
        };

        return [
            'total_spent' => $totalSpent,
            'total_bookings' => $totalBookings,
            'total_cancelled' => $totalCancelled,
            'total_refunded' => $totalRefunded,
            'joined_platform' => $joinedPlatform,
        ];
    }

    // ── Activity & Insights ────────────────────────────────────────────────

    private function getFilteredInsightsQuery(): Builder
    {
        $query = $this->countryBookingsQuery();

        if ($this->filterPeriod === 'this_week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->filterPeriod === 'this_month') {
            $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        } elseif ($this->filterPeriod === 'this_year') {
            $query->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]);
        } elseif ($this->filterPeriod === 'custom' && $this->customStartDate && $this->customEndDate) {
            $start = Carbon::parse($this->customStartDate)->startOfDay();
            $end = Carbon::parse($this->customEndDate)->endOfDay();

            if ($start->gt($end)) {
                $tmp = $start;
                $start = $end;
                $end = $tmp;
            }

            $query->whereBetween('created_at', [$start, $end]);
        }

        return $query;
    }

    public function getBookingsByTime(): array
    {
        $bookings = $this->getFilteredInsightsQuery()->get(['created_at']);

        if ($this->filterPeriod === 'this_week') {
            $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $data = array_fill(0, 7, 0);
            foreach ($bookings as $booking) {
                $dayIndex = $booking->created_at->format('N') - 1;
                $data[$dayIndex]++;
            }

            return ['labels' => $labels, 'data' => $data];
        } elseif ($this->filterPeriod === 'this_month') {
            $daysInMonth = now()->daysInMonth;
            $labels = range(1, $daysInMonth);
            $data = array_fill(0, $daysInMonth, 0);
            foreach ($bookings as $booking) {
                $dayIndex = $booking->created_at->format('j') - 1;
                $data[$dayIndex]++;
            }

            return ['labels' => array_map('strval', $labels), 'data' => $data];
        } elseif ($this->filterPeriod === 'custom' && $this->customStartDate && $this->customEndDate) {
            $start = Carbon::parse($this->customStartDate)->startOfDay();
            $end = Carbon::parse($this->customEndDate)->endOfDay();

            if ($start->gt($end)) {
                $tmp = $start;
                $start = $end;
                $end = $tmp;
            }

            $diffInDays = $start->diffInDays($end);

            if ($diffInDays <= 31) {
                $labels = [];
                $data = [];
                $current = $start->copy();
                while ($current->lte($end)) {
                    $labels[] = $current->format('M d');
                    $data[] = 0;
                    $current->addDay();
                }
                foreach ($bookings as $booking) {
                    $dateStr = $booking->created_at->format('M d');
                    $index = array_search($dateStr, $labels);
                    if ($index !== false) {
                        $data[$index]++;
                    }
                }

                return ['labels' => $labels, 'data' => $data];
            } else {
                $labels = [];
                $data = [];
                $current = $start->copy()->startOfMonth();
                $endMonth = $end->copy()->startOfMonth();
                while ($current->lte($endMonth)) {
                    $labels[] = $current->format('M Y');
                    $data[] = 0;
                    $current->addMonth();
                }
                foreach ($bookings as $booking) {
                    $monthStr = $booking->created_at->format('M Y');
                    $index = array_search($monthStr, $labels);
                    if ($index !== false) {
                        $data[$index]++;
                    }
                }

                return ['labels' => $labels, 'data' => $data];
            }
        } else {
            $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $data = array_fill(0, 12, 0);
            foreach ($bookings as $booking) {
                $monthIndex = $booking->created_at->format('n') - 1;
                $data[$monthIndex]++;
            }

            return ['labels' => $labels, 'data' => $data];
        }
    }

    public function getBookingStatusBreakdown(): array
    {
        $baseQuery = $this->getFilteredInsightsQuery();

        return [
            'completed' => (clone $baseQuery)->where('status', BookingStatus::Completed)->count(),
            'cancelled' => (clone $baseQuery)->where('status', BookingStatus::Cancelled)->count(),
            'confirmed' => (clone $baseQuery)->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])->count(),
        ];
    }

    /**
     * @return array<int, array{property_name: string, property_id: int, count: int, image: ?string}>
     */
    public function getMostBookedProperties(): array
    {
        return $this->getFilteredInsightsQuery()
            ->with('property')
            ->selectRaw('property_id, COUNT(*) as booking_count')
            ->groupBy('property_id')
            ->orderByDesc('booking_count')
            ->limit(5)
            ->get()
            ->map(fn (Booking $booking) => [
                'property_name' => $booking->property?->name ?? '-',
                'property_id' => $booking->property_id,
                'count' => $booking->booking_count,
                // primaryImages includes both videos and images ordered by sort_order;
                // explicitly pick the first IMAGE so we don't render <img src="…mp4">.
                'image' => $booking->property?->primaryImages()->where('media_type', 'image')->first()?->image_path,
            ])
            ->toArray();
    }

    public function exportInsights(): StreamedResponse
    {
        $bookings = $this->getFilteredInsightsQuery()
            ->with(['property.refCity', 'propertyRoom.roomType'])
            ->get();

        $filename = "customer_{$this->customerId}_bookings.csv";

        return response()->streamDownload(function () use ($bookings) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Booking ID', 'Property', 'Room Type', 'Check-in', 'Amount', 'Payment Status', 'Booking Status']);

            foreach ($bookings as $booking) {
                fputcsv($handle, [
                    $booking->booking_number,
                    $booking->property?->name,
                    $booking->propertyRoom?->roomType?->name,
                    $booking->check_in->format('Y-m-d'),
                    $booking->total_amount,
                    $booking->payment_status->label(),
                    $booking->status->label(),
                ]);
            }

            fclose($handle);
        }, $filename);
    }

    // ── Suspend Action ─────────────────────────────────────────────────────

    public function suspendAccountAction(): Action
    {
        $customer = $this->getCustomer();
        $isSuspended = $customer->status === UserStatus::Suspended;

        return Action::make('suspendAccount')
            ->label($isSuspended ? __('admin.activate_account') : __('admin.suspend_account'))
            ->icon($isSuspended ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
            ->color($isSuspended ? 'success' : 'danger')
            ->link()
            ->requiresConfirmation()
            ->modalHeading($isSuspended ? __('admin.activate_account') : __('admin.suspend_account'))
            ->modalDescription($isSuspended ? __('admin.activate_account_confirm') : __('admin.suspend_account_confirm'))
            ->modalSubmitActionLabel($isSuspended ? __('admin.yes_activate') : __('admin.yes_suspend'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->before($this->enforceRestrictedActionGuard())
            ->action(function () use ($customer, $isSuspended): void {
                $customer->update([
                    'status' => $isSuspended ? UserStatus::Active : UserStatus::Suspended,
                ]);

                Notification::make()
                    ->title($isSuspended ? __('admin.account_activated') : __('admin.account_suspended'))
                    ->success()
                    ->send();
            });
    }

    // ── Booking History Table ──────────────────────────────────────────────

    public function table(Table $table): Table
    {
        /** @var User $admin */
        $admin = auth()->user();
        $currency = Country::query()
            ->where('id', $admin->current_country_id)
            ->value('currency_symbol') ?? '$';

        return $table
            ->query(
                Booking::query()
                    ->where('user_id', $this->customerId)
                    ->whereHas('property', fn ($q) => $q->where('country_id', $admin->current_country_id))
                    ->with(['property.refCity', 'propertyRoom.roomType'])
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('booking_number')
                    ->label(__('admin.booking_id'))
                    ->searchable()
                    ->color('primary')
                    ->url(fn (Booking $record): string => BookingView::getUrl(['record' => $record->id])),

                TextColumn::make('property.name')
                    ->label(__('admin.property_info'))
                    ->description(fn (Booking $record): ?string => $record->property?->refCity?->name)
                    ->limit(20)
                    ->wrap(),

                TextColumn::make('propertyRoom.roomType.name')
                    ->label(__('admin.room_details'))
                    ->description(fn (Booking $record): ?string => $record->room_number)
                    ->extraAttributes(['class' => 'room-details-column'])
                    ->limit(20)
                    ->wrap(),

                TextColumn::make('check_in')
                    ->label(__('admin.booking_dates'))
                    ->formatStateUsing(fn (Booking $record): string => $record->check_in->format('M d').' → '.$record->check_out->format('M d'))
                    ->description(fn (Booking $record): string => $record->total_nights.' '.__('admin.nights')),

                TextColumn::make('total_amount')
                    ->label(__('admin.amount'))
                    ->formatStateUsing(fn (Booking $record): string => $currency.number_format((float) $record->total_amount, 2)),

                TextColumn::make('payment_status')
                    ->label(__('admin.payment'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => $state->color())
                    ->description(fn (Booking $record): ?string => $record->payment_method?->label()),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (Booking $record): string => BookingView::getUrl(['record' => $record->id])),

                Action::make('download')
                    ->iconButton()
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (Booking $record): string => route('invoice.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('customer-bookings')
                    ->exports([
                        'booking_number' => 'Booking ID',
                        'property.name' => 'Property',
                        'propertyRoom.roomType.name' => 'Room Type',
                        'check_in' => ['label' => 'Check-in', 'formatter' => fn (Booking $r): string => $r->check_in->format('M d, Y')],
                        'total_amount' => ['label' => 'Amount', 'formatter' => fn (Booking $r): string => $currency.number_format((float) $r->total_amount, 2)],
                        'payment_status' => ['label' => 'Payment', 'formatter' => fn (Booking $r): string => $r->payment_status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_bookings_yet'))
            ->emptyStateDescription(__('admin.customer_no_bookings_description'))
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->defaultPaginationPageOption(5);
    }
}
