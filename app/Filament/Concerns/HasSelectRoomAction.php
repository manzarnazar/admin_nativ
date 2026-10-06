<?php

namespace App\Filament\Concerns;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingRoomAssignment;
use App\Models\Floor;
use App\Models\Room;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Alignment;

trait HasSelectRoomAction
{
    public ?int $selectingBookingId = null;

    public array $pendingRoomIds = [];

    /**
     * Floor + room data for the modal, serialised as a plain array so it can be
     * passed to Alpine.js via $wire.selectRoomFloorData.
     *
     * Shape per entry:
     * ['id', 'name', 'rooms' => [['id', 'room_number', 'status', 'is_occupied'], ...]]
     *
     * @var array<int, array{id: int, name: string, rooms: array<int, array{id: int, room_number: string, status: string, is_occupied: bool}>}>
     */
    public array $selectRoomFloorData = [];

    /**
     * Load floor/room data for an existing booking and pre-populate the pending
     * selection from any existing BookingRoomAssignment rows.
     */
    public function loadSelectRoomData(int $bookingId): void
    {
        $booking = Booking::query()
            ->with('roomAssignments')
            ->findOrFail($bookingId);

        $this->selectingBookingId = $bookingId;
        $this->pendingRoomIds = $booking->roomAssignments->pluck('room_id')->toArray();

        $occupiedRoomIds = BookingRoomAssignment::query()
            ->select('booking_room_assignments.room_id')
            ->join('bookings', 'booking_room_assignments.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.status', [BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value])
            ->where('bookings.id', '!=', $booking->id)
            ->where('bookings.check_in', '<', $booking->check_out->toDateString())
            ->where('bookings.check_out', '>=', $booking->check_in->toDateString())
            ->whereNull('bookings.deleted_at')
            ->pluck('room_id')
            ->toArray();

        $floors = Floor::query()
            ->where('property_id', $booking->property_id)
            ->with(['rooms' => fn ($q) => $q
                ->where('property_room_id', $booking->property_room_id)
                ->orderBy('room_number'),
            ])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Floor $floor) => $floor->rooms->isNotEmpty());

        $this->selectRoomFloorData = $floors
            ->map(fn (Floor $floor) => [
                'id' => $floor->id,
                'name' => $floor->name,
                'rooms' => $floor->rooms
                    ->map(fn (Room $room) => [
                        'id' => $room->id,
                        'room_number' => $room->room_number,
                        'status' => $room->status->value,
                        'is_occupied' => in_array($room->id, $occupiedRoomIds, true),
                    ])
                    ->values()
                    ->toArray(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Called from Alpine.js via wire:click to toggle a room in/out of the pending selection.
     */
    public function togglePendingRoom(int $roomId): void
    {
        $idx = array_search($roomId, $this->pendingRoomIds, true);

        if ($idx !== false) {
            array_splice($this->pendingRoomIds, $idx, 1);
        } else {
            $this->pendingRoomIds[] = $roomId;
        }
    }

    /**
     * Validates count, ownership, and race conditions for the pending room selection.
     * Calls halt() on any failure, keeping the modal open.
     */
    protected function validatePendingRooms(int $required, int $propertyRoomId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): void
    {
        $selected = count($this->pendingRoomIds);

        if ($selected !== $required) {
            Notification::make()
                ->title(__('admin.room_selection_mismatch'))
                ->body(__('admin.room_selection_mismatch_body', ['required' => $required, 'selected' => $selected]))
                ->danger()
                ->send();

            $this->halt();

            return;
        }

        $validCount = Room::query()
            ->where('property_room_id', $propertyRoomId)
            ->whereIn('id', $this->pendingRoomIds)
            ->count();

        if ($validCount !== $selected) {
            Notification::make()
                ->title(__('admin.invalid_rooms_selected'))
                ->body(__('admin.invalid_rooms_selected_body'))
                ->danger()
                ->send();

            $this->halt();

            return;
        }

        $conflictQuery = BookingRoomAssignment::query()
            ->select('booking_room_assignments.room_id')
            ->join('bookings', 'booking_room_assignments.booking_id', '=', 'bookings.id')
            ->whereIn('booking_room_assignments.room_id', $this->pendingRoomIds)
            ->whereIn('bookings.status', [BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value])
            ->where('bookings.check_in', '<', $checkOut)
            ->where('bookings.check_out', '>=', $checkIn)
            ->whereNull('bookings.deleted_at');

        if ($excludeBookingId !== null) {
            $conflictQuery->where('bookings.id', '!=', $excludeBookingId);
        }

        $conflictingRoomIds = $conflictQuery->pluck('booking_room_assignments.room_id')->toArray();

        if (! empty($conflictingRoomIds)) {
            $roomNumbers = Room::query()
                ->whereIn('id', $conflictingRoomIds)
                ->pluck('room_number')
                ->join(', ');

            Notification::make()
                ->title(__('admin.rooms_no_longer_available'))
                ->body(__('admin.rooms_taken_since_modal_opened', ['rooms' => $roomNumbers]))
                ->danger()
                ->send();

            $this->halt();
        }
    }

    /**
     * Writes (replaces) the room assignments for the given booking and clears component state.
     */
    protected function persistRoomAssignments(int $bookingId): void
    {
        BookingRoomAssignment::query()
            ->where('booking_id', $bookingId)
            ->delete();

        $now = now();

        foreach ($this->pendingRoomIds as $roomId) {
            BookingRoomAssignment::query()->create([
                'booking_id' => $bookingId,
                'room_id' => $roomId,
                'assigned_by' => auth()->id(),
                'assigned_at' => $now,
            ]);
        }

        $this->pendingRoomIds = [];
        $this->selectRoomFloorData = [];
        $this->selectingBookingId = null;
    }

    /**
     * Validates and saves room assignments for an existing booking.
     * Used by the table record action and any other re-assignment flow.
     */
    protected function performRoomAssignment(Booking $booking): void
    {
        $this->validatePendingRooms(
            required: $booking->booked_rooms,
            propertyRoomId: $booking->property_room_id,
            checkIn: $booking->check_in->toDateString(),
            checkOut: $booking->check_out->toDateString(),
            excludeBookingId: $booking->id,
        );

        $this->persistRoomAssignments($booking->id);

        Notification::make()
            ->title(__('admin.rooms_assigned_successfully'))
            ->success()
            ->send();
    }

    /**
     * Returns the configured Select Rooms table record action.
     * Add this to any table's recordActions() that needs room assignment.
     */
    public function getSelectRoomsRecordAction(): Action
    {
        return Action::make('selectRooms')
            ->label(__('admin.select_rooms'))
            ->iconButton()
            ->icon('heroicon-o-squares-2x2')
            ->color('gray')
            ->tooltip(__('admin.select_rooms'))
            ->visible(fn (Booking $record): bool => ! in_array(
                $record->status,
                [BookingStatus::Expired, BookingStatus::Cancelled, BookingStatus::Completed],
                true
            ))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading(__('admin.select_rooms'))
            ->modalWidth('5xl')
            ->modalSubmitActionLabel(__('admin.save_booking'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->extraModalWindowAttributes(['class' => 'swap-modal-buttons'])
            ->modalFooterActionsAlignment(Alignment::Start)
            ->mountUsing(function (Booking $record): void {
                $this->loadSelectRoomData($record->id);
            })
            ->schema(fn (Booking $record): array => [
                View::make('filament.schemas.components.select-room-grid')
                    ->viewData([
                        'booked_rooms' => $record->booked_rooms,
                        'room_type_name' => $record->propertyRoom?->roomType?->name ?? '',
                        'check_in' => $record->check_in->format('M d, Y'),
                        'check_out' => $record->check_out->format('M d, Y'),
                    ]),
            ])
            ->action(fn (Booking $record) => $this->performRoomAssignment($record));
    }
}
