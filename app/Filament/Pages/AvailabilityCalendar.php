<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Enums\NavigationGroup;
use App\Models\Booking;
use App\Models\BookingRoomAssignment;
use App\Models\Country;
use App\Models\Floor;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\Room;
use App\Models\User;
use App\Support\SystemMode;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class AvailabilityCalendar extends Page
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }

    protected static ?string $slug = 'availability-calendar';

    public static function canAccess(): bool
    {
        if (SystemMode::isMulti()) {
            return false;
        }

        return static::traitCanAccess();
    }

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.availability-calendar';

    #[Url(as: 'view')]
    public string $activeView = 'calendar';

    #[Url(as: 'date')]
    public string $startDate = '';

    #[Url(as: 'end')]
    public string $endDate = '';

    public string $search = '';

    public ?int $gridRoomTypeFilter = null;

    public ?int $viewingBookingId = null;

    public function getTitle(): string|Htmlable
    {
        return __('admin.availability_calendar');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.availability_calendar');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Bookings);
    }

    public function getSubheading(): ?string
    {
        return __('admin.all_bookings_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        if (empty($this->startDate)) {
            $this->startDate = today()->format('Y-m-d');
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function getCurrentProperty(): ?Property
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->current_branch_id) {
            return null;
        }

        return Property::query()->find($user->current_branch_id);
    }

    public function getCurrencySymbol(): string
    {
        /** @var User $user */
        $user = auth()->user();

        return Country::query()
            ->where('id', $user->current_country_id)
            ->value('currency_symbol') ?? '$';
    }

    public function goToToday(): void
    {
        $this->startDate = today()->format('Y-m-d');
        $this->endDate = '';
    }

    // ── Calendar Data ──────────────────────────────────────────────────────

    /**
     * Get the 16-day date range starting 3 days before selected date.
     *
     * @return array<int, array{date: Carbon, dayName: string, dayNum: string, monthName: string, isToday: bool, isWeekend: bool}>
     */
    public function getDateColumns(): array
    {
        $start = Carbon::parse($this->startDate);
        $dates = [];

        if (! empty($this->endDate) && $this->endDate > $this->startDate) {
            $end = Carbon::parse($this->endDate);
            $days = (int) $start->diffInDays($end) + 1;
        } else {
            $start = $start->subDays(3);
            $days = 16;
        }

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $dates[] = [
                'date' => $date,
                'dayName' => $date->format('D'),
                'dayNum' => $date->format('j'),
                'monthName' => $date->format('M'),
                'isToday' => $date->isToday(),
                'isWeekend' => $date->isWeekend(),
            ];
        }

        return $dates;
    }

    /**
     * Get room types for the selected property with today's occupancy.
     *
     * @return Collection<int, array{room: PropertyRoom, totalRooms: int, occupiedToday: int}>
     */
    public function getRoomTypes(): Collection
    {
        $property = $this->getCurrentProperty();

        if (! $property) {
            return collect();
        }

        $today = today()->format('Y-m-d');

        return $property->rooms()
            ->with('roomType')
            ->get()
            ->map(function (PropertyRoom $room) use ($today) {
                $occupiedToday = Booking::query()
                    ->where('property_room_id', $room->id)
                    ->where(function ($query) use ($today) {
                        $query->where(function ($q) use ($today) {
                            // Confirmed bookings: use standard date range
                            $q->where('status', BookingStatus::Confirmed)
                                ->where('check_in', '<=', $today)
                                ->where('check_out', '>', $today);
                        })->orWhere(function ($q) {
                            // CheckedIn: occupied until physically checked out
                            $q->where('status', BookingStatus::CheckedIn)
                                ->whereNull('actual_checkout_at');
                        });
                    })
                    ->sum('booked_rooms');

                return [
                    'room' => $room,
                    'totalRooms' => $room->total_rooms,
                    'occupiedToday' => (int) $occupiedToday,
                ];
            });
    }

    /**
     * Get bookings for a specific room type within the visible date range.
     *
     * @return Collection<int, Booking>
     */
    public function getBookingsForRoom(int $propertyRoomId): Collection
    {
        $dates = $this->getDateColumns();
        $startDate = $dates[0]['date']->format('Y-m-d');
        $endDate = $dates[count($dates) - 1]['date']->format('Y-m-d');
        // $startDate / $endDate here are the visible date column range, not $this->startDate/$this->endDate

        $query = Booking::query()
            ->with('customer')
            ->where('property_room_id', $propertyRoomId)
            ->where('check_in', '<=', $endDate)
            ->where('check_out', '>', $startDate)
            ->whereIn('status', [
                BookingStatus::Confirmed,
                BookingStatus::CheckedIn,
                BookingStatus::Completed,
            ]);

        if (! empty($this->search)) {
            $search = $this->search;
            $query->whereHas('customer', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
        }

        return $query->orderBy('check_in')->get();
    }

    /**
     * Calculate grid column start/end for a booking block.
     *
     * @return array{start: int, end: int, guestName: string, status: string, bookingId: int}|null
     */
    public function getBookingPosition(Booking $booking, array $dateColumns): ?array
    {
        $checkIn = $booking->check_in;
        $checkOut = $booking->check_out;
        $firstDate = $dateColumns[0]['date'];
        $lastDate = $dateColumns[count($dateColumns) - 1]['date'];

        // Booking doesn't overlap with visible range
        if ($checkIn->gt($lastDate) || $checkOut->lte($firstDate)) {
            return null;
        }

        // Clamp to visible range
        $visibleStart = $checkIn->lt($firstDate) ? $firstDate : $checkIn;
        $visibleEnd = $checkOut->gt($lastDate->copy()->addDay()) ? $lastDate->copy()->addDay() : $checkOut;

        // Calculate grid positions (1-indexed, column 1 = first date)
        $startCol = (int) $firstDate->diffInDays($visibleStart) + 1;
        $endCol = (int) $firstDate->diffInDays($visibleEnd) + 1;

        return [
            'start' => $startCol,
            'end' => $endCol,
            'guestName' => $booking->customer?->name ?? 'Guest',
            'status' => $booking->status->value,
            'bookingId' => $booking->id,
        ];
    }

    // ── Room Grid View ─────────────────────────────────────────────────────

    /**
     * Options for the room type filter in Room Grid View.
     *
     * @return array<int, string>
     */
    public function getPropertyRoomOptions(): array
    {
        $property = $this->getCurrentProperty();

        if (! $property) {
            return [];
        }

        return $property->rooms()
            ->with('roomType')
            ->get()
            ->mapWithKeys(fn (PropertyRoom $pr) => [$pr->id => $pr->roomType->name])
            ->toArray();
    }

    /**
     * Floors with rooms annotated for Room Grid View (Available / Booked / Inactive).
     * A room is Booked if it has an assignment for any booking overlapping the date range.
     *
     * @return Collection<int, array{floor: Floor, rooms: Collection<int, array{room: Room, isBooked: bool, bookingId: ?int, isInactive: bool}>}>
     */
    public function getRoomGridFloors(): Collection
    {
        $property = $this->getCurrentProperty();

        if (! $property) {
            return collect();
        }

        $start = $this->startDate ?: today()->format('Y-m-d');
        $end = (! empty($this->endDate) && $this->endDate >= $start) ? $this->endDate : $start;
        $search = trim($this->search);

        $bookedRoomInfo = BookingRoomAssignment::query()
            ->select('booking_room_assignments.room_id', 'booking_room_assignments.booking_id')
            ->join('bookings', 'booking_room_assignments.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.status', [BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value])
            ->where('bookings.check_in', '<=', $end)
            ->where(function ($q) use ($start) {
                $q->where('bookings.check_out', '>', $start)
                    ->orWhere(function ($q2) {
                        $q2->where('bookings.status', BookingStatus::CheckedIn->value)
                            ->whereNull('bookings.actual_checkout_at');
                    });
            })
            ->whereNull('bookings.deleted_at')
            ->where('bookings.property_id', $property->id)
            ->get()
            ->keyBy('room_id');

        // When search is active, find rooms whose current guest name matches.
        $guestMatchedRoomIds = [];
        if ($search !== '') {
            $guestMatchedRoomIds = BookingRoomAssignment::query()
                ->select('booking_room_assignments.room_id')
                ->join('bookings', 'booking_room_assignments.booking_id', '=', 'bookings.id')
                ->leftJoin('users', 'bookings.user_id', '=', 'users.id')
                ->whereIn('bookings.status', [BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value])
                ->where('bookings.check_in', '<=', $end)
                ->where(function ($q) use ($start) {
                    $q->where('bookings.check_out', '>', $start)
                        ->orWhere(function ($q2) {
                            $q2->where('bookings.status', BookingStatus::CheckedIn->value)
                                ->whereNull('bookings.actual_checkout_at');
                        });
                })
                ->whereNull('bookings.deleted_at')
                ->where('bookings.property_id', $property->id)
                ->where(
                    fn ($q) => $q
                        ->where('users.name', 'like', "%{$search}%")
                        ->orWhere('bookings.guest_name', 'like', "%{$search}%")
                )
                ->pluck('room_id')
                ->toArray();
        }

        $floors = Floor::query()
            ->where('property_id', $property->id)
            ->with(['rooms' => function ($q) {
                if ($this->gridRoomTypeFilter) {
                    $q->where('property_room_id', $this->gridRoomTypeFilter);
                }
                $q->with('propertyRoom.roomType')->orderBy('room_number');
            }])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Floor $floor) => $floor->rooms->isNotEmpty());

        return $floors
            ->map(fn (Floor $floor) => [
                'floor' => $floor,
                'rooms' => $floor->rooms->map(fn (Room $room) => [
                    'room' => $room,
                    'isBooked' => isset($bookedRoomInfo[$room->id]),
                    'bookingId' => $bookedRoomInfo[$room->id]->booking_id ?? null,
                    'isInactive' => $room->status->value === 'inactive',
                ])->filter(
                    fn (array $roomData) => $search === ''
                        || str_contains(strtolower((string) $roomData['room']->room_number), strtolower($search))
                        || in_array($roomData['room']->id, $guestMatchedRoomIds, true)
                )->values(),
            ])
            ->filter(fn (array $floorData) => $floorData['rooms']->isNotEmpty())
            ->values();
    }

    /**
     * Open the booking detail modal for a room in Room Grid View.
     * Finds the first overlapping booking assignment for the current date range.
     */
    public function openBookingModalFromRoom(int $roomId): void
    {
        $start = $this->startDate ?: today()->format('Y-m-d');
        $end = (! empty($this->endDate) && $this->endDate >= $start) ? $this->endDate : $start;

        $assignment = BookingRoomAssignment::query()
            ->where('room_id', $roomId)
            ->whereHas(
                'booking',
                fn (Builder $q) => $q
                    ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value])
                    ->where('check_in', '<=', $end)
                    ->where(function ($q2) use ($start) {
                        $q2->where('check_out', '>', $start)
                            ->orWhere(function ($q3) {
                                $q3->where('status', BookingStatus::CheckedIn->value)
                                    ->whereNull('actual_checkout_at');
                            });
                    })
                    ->whereNull('deleted_at')
            )
            ->first();

        if ($assignment) {
            $this->openBookingModal($assignment->booking_id);
        }
    }

    // ── Room Grid Reservation Notes ────────────────────────────────────────

    /**
     * Per-room-type breakdown of active bookings (Confirmed + CheckedIn) for the
     * selected date range. Used to show "X Confirmed · Y Checked In · Z Available"
     * notes on the Room Grid, giving admins a full picture of room utilisation.
     *
     * @return Collection<int, array{name: string, total: int, confirmed: int, checked_in: int, available: int}>
     */
    public function getRoomTypeReservationNotes(): Collection
    {
        $property = $this->getCurrentProperty();

        if (! $property) {
            return collect();
        }

        $start = $this->startDate ?: today()->format('Y-m-d');
        $end = (! empty($this->endDate) && $this->endDate >= $start) ? $this->endDate : $start;

        $rows = Booking::query()
            ->where('property_id', $property->id)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->where('check_in', '<=', $end)
            ->where(function ($q) use ($start) {
                $q->where('check_out', '>', $start)
                    ->orWhere(function ($q2) {
                        $q2->where('status', BookingStatus::CheckedIn)
                            ->whereNull('actual_checkout_at');
                    });
            })
            ->whereNull('deleted_at')
            ->selectRaw('property_room_id, status, SUM(booked_rooms) as reserved_count')
            ->groupBy('property_room_id', 'status')
            ->get();

        $confirmedByRoom = $rows
            ->where('status', BookingStatus::Confirmed->value)
            ->pluck('reserved_count', 'property_room_id');

        $checkedInByRoom = $rows
            ->where('status', BookingStatus::CheckedIn->value)
            ->pluck('reserved_count', 'property_room_id');

        return $property->rooms()
            ->with('roomType')
            ->get()
            ->map(fn (PropertyRoom $room) => [
                'name' => $room->roomType->name ?? '—',
                'total' => (int) $room->total_rooms,
                'confirmed' => (int) ($confirmedByRoom[$room->id] ?? 0),
                'checked_in' => (int) ($checkedInByRoom[$room->id] ?? 0),
                'available' => max(0, (int) $room->total_rooms - (int) ($confirmedByRoom[$room->id] ?? 0) - (int) ($checkedInByRoom[$room->id] ?? 0)),
            ])
            ->filter(fn (array $item) => $item['confirmed'] > 0 || $item['checked_in'] > 0)
            ->values();
    }

    // ── Booking Detail Modal ───────────────────────────────────────────────

    public function openBookingModal(int $bookingId): void
    {
        $this->viewingBookingId = $bookingId;
        $this->mountAction('viewBooking');
    }

    public function getViewingBooking(): ?Booking
    {
        if (! $this->viewingBookingId) {
            return null;
        }

        return Booking::with([
            'customer',
            'propertyRoom.roomType',
            'roomAssignments.room.floor',
        ])->find($this->viewingBookingId);
    }

    public function viewBookingAction(): Action
    {
        return Action::make('viewBooking')
            ->modalHeading(__('admin.booking_details'))
            ->modalWidth('2xl')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->extraModalWindowAttributes(['class' => 'booking-detail-sidebar'])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.close'))
            ->extraModalFooterActions([
                Action::make('viewFullDetails')
                    ->label(__('admin.view_booking_details'))
                    ->url(fn (): string => $this->viewingBookingId
                        ? BookingView::getUrl(['record' => $this->viewingBookingId])
                        : '#')
                    ->color('primary'),
            ])
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema(fn (): array => [
                View::make('filament.schemas.components.booking-details-readonly')
                    ->viewData(['booking' => $this->getViewingBooking()]),
            ]);
    }
}
