<?php

namespace App\Filament\Pages;

use App\Actions\GenerateRoomsAction;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Enums\NavigationGroup;
use App\Models\BookingRoomAssignment;
use App\Models\Floor;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\Room;
use App\Models\User;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class RoomInventory extends Page
{
    use HasPagePermission;

    public static function canAccess(): bool
    {
        return SystemMode::isSingle() && Auth::check();
    }

    protected static ?string $slug = 'room-inventory';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.room-inventory';

    public bool $showAddForm = false;

    /** @var array<string, mixed> */
    public array $addFormData = [];

    /** @var array<int> */
    public array $selectedRoomIds = [];

    public int $pendingBulkCount = 0;

    public function getTitle(): string|Htmlable
    {
        return __('admin.room_inventory');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.room_inventory');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::RoomManagement);
    }

    public function getSubheading(): ?string
    {
        return __('admin.room_inventory_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    // ── Header Actions ─────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleAddForm')
                ->label('+ '.__('admin.add_rooms'))
                ->color('primary')
                ->action(fn () => $this->showAddForm = ! $this->showAddForm),
        ];
    }

    // ── Inline Add Form ────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(4)->schema([
                    Select::make('floor_id')
                        ->label(__('admin.floor'))
                        ->options(fn () => $this->getFloorOptions())
                        ->required()
                        ->placeholder(__('admin.select_floor')),

                    Select::make('property_room_id')
                        ->label(__('admin.room_type'))
                        ->options(fn () => $this->getRoomTypeOptions())
                        ->required()
                        ->placeholder(__('admin.select_room_type')),

                    TextInput::make('count')
                        ->label(__('admin.no_of_rooms'))
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->placeholder('e.g., 5'),

                    TextInput::make('start_number')
                        ->label(__('admin.start_number'))
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->placeholder('e.g., 101'),
                ]),
            ])
            ->statePath('addFormData');
    }

    public function generateRooms(): void
    {
        $data = $this->form->getState();

        $result = app(GenerateRoomsAction::class)->handle([
            'floor_id' => $data['floor_id'],
            'property_room_id' => $data['property_room_id'],
            'count' => (int) $data['count'],
            'start_number' => (int) $data['start_number'],
        ]);

        $this->form->fill();
        $this->showAddForm = false;

        if ($result['created'] === 0) {
            $skippedList = implode(', ', $result['skipped']);
            Notification::make()
                ->title(__('admin.no_rooms_created'))
                ->body(__('admin.rooms_already_exist_body', ['rooms' => $skippedList]))
                ->danger()
                ->send();
        } elseif (count($result['skipped']) > 0) {
            $skippedList = implode(', ', $result['skipped']);
            Notification::make()
                ->title(__('admin.rooms_partially_created', ['count' => $result['created']]))
                ->body(__('admin.rooms_skipped_body', ['rooms' => $skippedList]))
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title(__('admin.rooms_generated_successfully'))
                ->success()
                ->send();
        }
    }

    // ── Page-Level Actions (triggered from Blade) ──────────────────────────

    public function editRoomAction(): Action
    {
        return Action::make('editRoom')
            ->disabled(static::disabledUnlessCanEdit())
            ->modalHeading(__('admin.edit_room_details'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalSubmitActionLabel(__('admin.save_room'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->fillForm(function (array $arguments): array {
                $room = Room::find($arguments['roomId'] ?? null);

                if (! $room) {
                    return [];
                }

                return [
                    'room_number' => $room->room_number,
                    'property_room_id' => $room->property_room_id,
                    'floor_id' => $room->floor_id,
                    'status' => $room->status->value,
                ];
            })
            ->schema([
                TextInput::make('room_number')
                    ->label(__('admin.room_number'))
                    ->required()
                    ->maxLength(20),

                Grid::make(2)->schema([
                    Select::make('property_room_id')
                        ->label(__('admin.room_type'))
                        ->options(fn () => $this->getRoomTypeOptions())
                        ->required(),

                    Select::make('floor_id')
                        ->label(__('admin.floor'))
                        ->options(fn () => $this->getFloorOptions())
                        ->required(),
                ]),

                Radio::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        'active' => __('admin.active'),
                        'inactive' => __('admin.inactive'),
                    ])
                    ->required()
                    ->inline(),
            ])
            ->action(function (array $data, array $arguments): void {
                $room = Room::find($arguments['roomId'] ?? null);

                if (! $room) {
                    return;
                }

                if ($data['room_number'] !== $room->room_number) {
                    $exists = Room::query()
                        ->withTrashed()
                        ->where('property_id', $room->property_id)
                        ->where('room_number', $data['room_number'])
                        ->where('id', '!=', $room->id)
                        ->exists();

                    if ($exists) {
                        Notification::make()
                            ->title(__('admin.room_number_already_exists'))
                            ->danger()
                            ->send();

                        return;
                    }
                }

                // Block deactivation if the room has any active booking assignments.
                if ($data['status'] === 'inactive' && $room->status === RoomStatus::Active) {
                    $hasActiveAssignments = BookingRoomAssignment::query()
                        ->where('room_id', $room->id)
                        ->whereHas('booking', fn ($q) => $q->whereIn('status', [
                            BookingStatus::Confirmed->value,
                            BookingStatus::CheckedIn->value,
                        ]))
                        ->exists();

                    if ($hasActiveAssignments) {
                        Notification::make()
                            ->title(__('admin.room_cannot_deactivate_active_booking'))
                            ->danger()
                            ->send();

                        return;
                    }
                }

                $oldPropertyRoomId = $room->property_room_id;
                $newPropertyRoomId = (int) $data['property_room_id'];

                if ($oldPropertyRoomId !== $newPropertyRoomId) {
                    $hasActiveAssignments = BookingRoomAssignment::query()
                        ->where('room_id', $room->id)
                        ->whereHas('booking', fn ($q) => $q->whereIn('status', [
                            BookingStatus::Confirmed->value,
                            BookingStatus::CheckedIn->value,
                        ]))
                        ->exists();

                    if ($hasActiveAssignments) {
                        Notification::make()
                            ->title(__('admin.room_type_cannot_change_active_assignments'))
                            ->danger()
                            ->send();

                        return;
                    }
                }

                $room->update([
                    'room_number' => $data['room_number'],
                    'property_room_id' => $data['property_room_id'],
                    'floor_id' => $data['floor_id'],
                    'status' => $data['status'],
                ]);

                $this->syncTotalRooms($oldPropertyRoomId);

                if ($oldPropertyRoomId !== $newPropertyRoomId) {
                    $this->syncTotalRooms($newPropertyRoomId);
                }

                Notification::make()->title(__('admin.room_updated'))->success()->send();
            });
    }

    /**
     * Returns which bulk action buttons to show based on the statuses of selected rooms.
     * All active → only show Deactivate. All inactive → only show Activate. Mixed → show both.
     *
     * @return array{has_active: bool, has_inactive: bool}
     */
    public function getSelectedRoomStatusSummary(): array
    {
        if (empty($this->selectedRoomIds)) {
            return ['has_active' => false, 'has_inactive' => false];
        }

        $statuses = Room::query()
            ->whereIn('id', $this->selectedRoomIds)
            ->pluck('status');

        return [
            'has_active' => $statuses->contains(RoomStatus::Active),
            'has_inactive' => $statuses->contains(RoomStatus::Inactive),
        ];
    }

    public function prepBulkActivate(): void
    {
        $count = Room::query()
            ->whereIn('id', $this->selectedRoomIds)
            ->where('status', 'inactive')
            ->count();

        if ($count === 0) {
            Notification::make()->title(__('admin.rooms_already_active'))->warning()->send();

            return;
        }

        $this->pendingBulkCount = $count;
        $this->mountAction('bulkActivate');
    }

    public function prepBulkInactivate(): void
    {
        $count = Room::query()
            ->whereIn('id', $this->selectedRoomIds)
            ->where('status', 'active')
            ->count();

        if ($count === 0) {
            Notification::make()->title(__('admin.rooms_already_inactive'))->warning()->send();

            return;
        }

        $this->pendingBulkCount = $count;
        $this->mountAction('bulkInactivate');
    }

    public function bulkActivateAction(): Action
    {
        return Action::make('bulkActivate')
            ->before(static::enforceEditPermission())
            ->requiresConfirmation()
            ->modalHeading(__('admin.bulk_activate_heading'))
            ->modalDescription(fn () => __('admin.bulk_activate_description', ['count' => $this->pendingBulkCount]))
            ->modalSubmitActionLabel(__('admin.bulk_activate_confirm'))
            ->modalAlignment(Alignment::Center)
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->color('warning')
            ->action(fn () => $this->bulkActivate());
    }

    public function bulkInactivateAction(): Action
    {
        return Action::make('bulkInactivate')
            ->before(static::enforceEditPermission())
            ->requiresConfirmation()
            ->modalHeading(__('admin.bulk_inactivate_heading'))
            ->modalDescription(fn () => __('admin.bulk_inactivate_description', ['count' => $this->pendingBulkCount]))
            ->modalSubmitActionLabel(__('admin.bulk_inactivate_confirm'))
            ->modalAlignment(Alignment::Center)
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->color('warning')
            ->action(fn () => $this->bulkDeactivate());
    }

    public function deleteRoomAction(): Action
    {
        return Action::make('deleteRoom')
            ->before(static::enforceDeletePermission())
            ->requiresConfirmation()
            ->modalHeading(__('admin.delete_room'))
            ->modalDescription(__('admin.delete_room_confirmation'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalAlignment(Alignment::Center)
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->color('danger')
            ->action(function (array $arguments): void {
                $room = Room::find($arguments['roomId'] ?? null);

                if (! $room) {
                    return;
                }

                $hasActiveAssignments = BookingRoomAssignment::query()
                    ->where('room_id', $room->id)
                    ->whereHas('booking', fn ($q) => $q->whereIn('status', [
                        BookingStatus::Confirmed->value,
                        BookingStatus::CheckedIn->value,
                    ]))
                    ->exists();

                if ($hasActiveAssignments) {
                    Notification::make()
                        ->title(__('admin.room_has_active_assignments'))
                        ->danger()
                        ->send();

                    return;
                }

                $propertyRoomId = $room->property_room_id;
                $room->delete();
                $this->syncTotalRooms($propertyRoomId);

                $this->selectedRoomIds = array_values(
                    array_filter($this->selectedRoomIds, fn ($id) => $id !== $room->id)
                );

                Notification::make()->title(__('admin.room_deleted'))->success()->send();
            });
    }

    // ── Bulk Actions ───────────────────────────────────────────────────────

    public function bulkActivate(): void
    {
        if (empty($this->selectedRoomIds)) {
            return;
        }

        Room::query()
            ->whereIn('id', $this->selectedRoomIds)
            ->where('status', 'inactive')
            ->update(['status' => 'active']);

        $this->syncAffectedPropertyRooms();
        $this->selectedRoomIds = [];

        Notification::make()->title(__('admin.rooms_activated'))->success()->send();
    }

    public function bulkDeactivate(): void
    {
        if (empty($this->selectedRoomIds)) {
            return;
        }

        // Only consider rooms that are currently active — inactive rooms need no action.
        $activeSelectedIds = Room::query()
            ->whereIn('id', $this->selectedRoomIds)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        // Of the active rooms, find which have active booking assignments — these cannot be deactivated.
        $blockedRoomIds = BookingRoomAssignment::query()
            ->select('booking_room_assignments.room_id')
            ->join('bookings', 'booking_room_assignments.booking_id', '=', 'bookings.id')
            ->whereIn('booking_room_assignments.room_id', $activeSelectedIds)
            ->whereIn('bookings.status', [BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value])
            ->whereNull('bookings.deleted_at')
            ->pluck('booking_room_assignments.room_id')
            ->unique()
            ->toArray();

        $deactivatableIds = array_values(array_diff($activeSelectedIds, $blockedRoomIds));

        if (! empty($deactivatableIds)) {
            Room::query()
                ->whereIn('id', $deactivatableIds)
                ->update(['status' => 'inactive']);

            $this->syncTotalRoomsForIds($deactivatableIds);

            Notification::make()->title(__('admin.rooms_deactivated'))->success()->send();
        }

        $this->selectedRoomIds = [];

        if (! empty($blockedRoomIds)) {
            $blockedNumbers = Room::query()
                ->whereIn('id', $blockedRoomIds)
                ->pluck('room_number')
                ->join(', ');

            Notification::make()
                ->title(__('admin.rooms_skipped_active_booking'))
                ->body(__('admin.rooms_skipped_active_booking_body', ['rooms' => $blockedNumbers]))
                ->warning()
                ->send();
        }
    }

    // ── Data Helpers ───────────────────────────────────────────────────────

    public function getFloorsWithRooms(): Collection
    {
        $property = $this->getCurrentProperty();

        if (! $property) {
            return new Collection;
        }

        return Floor::query()
            ->where('property_id', $property->id)
            ->has('rooms')
            ->with([
                'rooms' => fn ($q) => $q->with('propertyRoom.roomType')->orderBy('room_number'),
            ])
            ->orderBy('sort_order')
            ->get();
    }

    public function hasRooms(): bool
    {
        $property = $this->getCurrentProperty();

        if (! $property) {
            return false;
        }

        return Room::query()
            ->where('property_id', $property->id)
            ->exists();
    }

    public function hasFloors(): bool
    {
        $property = $this->getCurrentProperty();

        if (! $property) {
            return false;
        }

        return Floor::query()
            ->where('property_id', $property->id)
            ->exists();
    }

    private function getCurrentProperty(): ?Property
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->current_branch_id) {
            return null;
        }

        return Property::query()->find($user->current_branch_id);
    }

    private function getFloorOptions(): array
    {
        $property = $this->getCurrentProperty();

        if (! $property) {
            return [];
        }

        return Floor::query()
            ->where('property_id', $property->id)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->toArray();
    }

    private function getRoomTypeOptions(): array
    {
        $property = $this->getCurrentProperty();

        if (! $property) {
            return [];
        }

        return PropertyRoom::query()
            ->with('roomType')
            ->where('property_id', $property->id)
            ->get()
            ->mapWithKeys(fn (PropertyRoom $pr) => [$pr->id => $pr->roomType->name])
            ->toArray();
    }

    private function syncTotalRooms(int $propertyRoomId): void
    {
        $activeCount = Room::query()
            ->where('property_room_id', $propertyRoomId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->count();

        PropertyRoom::query()
            ->where('id', $propertyRoomId)
            ->update(['total_rooms' => $activeCount]);
    }

    private function syncAffectedPropertyRooms(): void
    {
        $propertyRoomIds = Room::query()
            ->whereIn('id', $this->selectedRoomIds)
            ->pluck('property_room_id')
            ->unique();

        foreach ($propertyRoomIds as $propertyRoomId) {
            $this->syncTotalRooms($propertyRoomId);
        }
    }

    /**
     * @param  array<int>  $roomIds
     */
    private function syncTotalRoomsForIds(array $roomIds): void
    {
        $propertyRoomIds = Room::query()
            ->whereIn('id', $roomIds)
            ->pluck('property_room_id')
            ->unique();

        foreach ($propertyRoomIds as $propertyRoomId) {
            $this->syncTotalRooms($propertyRoomId);
        }
    }
}
