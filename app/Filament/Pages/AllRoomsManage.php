<?php

namespace App\Filament\Pages;

use App\Enums\FacilityStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Enums\NavigationGroup;
use App\Models\Country;
use App\Models\Facility;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\RoomType;
use App\Models\User;
use App\Services\PropertyService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;

class AllRoomsManage extends Page implements HasTable
{
    use HasPagePermission, InteractsWithTable;

    public static function canAccess(): bool
    {
        return SystemMode::isSingle() && Auth::check();
    }

    protected static ?string $slug = 'all-rooms';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.all-rooms-manage';

    /** @var int Modal step: 1 or 2 */
    public int $modalStep = 1;

    /** @var ?int Property room ID being edited */
    public ?int $editingPropertyRoomId = null;

    /** @var array Step 1 form data stored when advancing to step 2 */
    public array $step1Data = [];

    public function getTitle(): string|Htmlable
    {
        return __('admin.all_rooms');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.all_rooms');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::RoomManagement);
    }

    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            AllRoomsView::getRouteName(),
        ];
    }

    public function getSubheading(): ?string
    {
        return __('admin.all_rooms_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function hasRooms(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        $query = PropertyRoom::query()
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $user->current_country_id));

        if ($user->current_branch_id) {
            $query->where('property_id', $user->current_branch_id);
        }

        return $query->exists();
    }

    private function getCurrencySymbol(): string
    {
        /** @var User $user */
        $user = auth()->user();

        return Country::query()
            ->where('id', $user->current_country_id)
            ->value('currency_symbol') ?? '$';
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

    // ── Table ──────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        $query = PropertyRoom::query()
            ->with(['property', 'roomType.images', 'roomType.facilities'])
            ->withCount('reviews')
            ->withAvg('reviews as reviews_avg_rating', 'rating')
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $user->current_country_id));

        if ($user->current_branch_id) {
            $query->where('property_id', $user->current_branch_id);
        }

        return $table
            ->query($query)
            ->columns([
                ImageColumn::make('roomType.firstImagePath')
                    ->label('')
                    ->getStateUsing(fn (PropertyRoom $record): ?string => $record->roomType?->images->first()?->image_path)
                    ->disk('public')
                    ->width(48)
                    ->height(48)
                    ->circular(false)
                    ->defaultImageUrl(fn (): string => asset('avatars/defaultUser.svg')),

                TextColumn::make('roomType.name')
                    ->label(__('admin.room_types'))
                    ->searchable()
                    ->description(fn (PropertyRoom $record): ?string => $user->current_branch_id ? null : $record->property?->name)
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('specification')
                    ->label(__('admin.specification'))
                    ->getStateUsing(fn (PropertyRoom $record): string => $record->roomType?->name ?? '-')
                    ->formatStateUsing(function (PropertyRoom $record): string {
                        $rt = $record->roomType;
                        $parts = [];
                        if ($rt?->max_guests) {
                            $parts[] = __('admin.max_guests').': '.$rt->max_guests.' adults';
                        }
                        if ($rt?->bed_type) {
                            $parts[] = __('admin.bed_type').': '.$rt->bed_type;
                        }
                        if ($record->room_size) {
                            $parts[] = __('admin.room_size').': '.$record->room_size.' sqft';
                        }

                        return implode("\n", $parts);
                    })
                    ->html(false)
                    ->wrap(),

                TextColumn::make('total_rooms')
                    ->label(__('admin.total_rooms'))
                    ->badge()
                    ->color('success')
                    ->suffix(' '.strtoupper(__('admin.rooms'))),

                TextColumn::make('reviews_count')
                    ->label(__('admin.reviews'))
                    ->formatStateUsing(function (PropertyRoom $record): string {
                        if ($record->reviews_count > 0) {
                            $avg = $record->reviews_avg_rating ? number_format((float) $record->reviews_avg_rating, 1) : '0.0';

                            return $avg.' ⭐';
                        }

                        return '—';
                    })
                    ->description(fn (PropertyRoom $record): string => $record->reviews_count > 0 ? $record->reviews_count.' '.__('admin.reviews') : 'No reviews')
                    ->badge()
                    ->color(fn (PropertyRoom $record): string => $record->reviews_count > 0 ? 'success' : 'gray'),

                TextColumn::make('base_price_per_night')
                    ->label(__('admin.base_price'))
                    ->formatStateUsing(fn (PropertyRoom $record): string => $this->getCurrencySymbol().number_format((float) $record->base_price_per_night, 2).' / '.__('admin.night'))
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (PropertyRoom $record): string => AllRoomsView::getUrl(['record' => $record->id])),

                Action::make('edit')
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->disabled(static::disabledUnlessCanEdit())
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalHeading(__('admin.edit_room'))
                    ->modalWidth('2xl')
                    ->modalSubmitActionLabel(__('admin.save_room'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->fillForm(fn (PropertyRoom $record): array => [
                        'room_size' => $record->room_size,
                        'base_price_per_night' => $record->base_price_per_night,
                        'room_images' => $record->roomType->images->pluck('image_path')->toArray(),
                        'room_amenities' => $record->roomType->facilities->where('status', FacilityStatus::Active)->pluck('id')->toArray(),
                    ])
                    ->schema(fn (PropertyRoom $record): array => $this->getEditRoomSchema($record))
                    ->action(function (array $data, PropertyRoom $record): void {
                        $roomType = $record->roomType;

                        // Sync room type images
                        $roomType->images()->delete();
                        foreach ($data['room_images'] ?? [] as $index => $imagePath) {
                            $roomType->images()->create([
                                'image_path' => $imagePath,
                                'sort_order' => $index,
                            ]);
                        }

                        // Sync amenities
                        $roomType->facilities()->sync($data['room_amenities'] ?? []);

                        // Update property room
                        $record->update([
                            'room_size' => $data['room_size'] ?? null,
                            'base_price_per_night' => $data['base_price_per_night'],
                        ]);

                        Notification::make()
                            ->title(__('admin.room_updated'))
                            ->success()
                            ->send();
                    }),

                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->before(static::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_room'))
                    ->modalDescription(__('admin.delete_room_from_branch'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
                    ->action(function (PropertyRoom $record): void {
                        app(PropertyService::class)->removeRoom($record);

                        Notification::make()
                            ->title(__('admin.room_deleted'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('all-rooms')
                    ->exports([
                        'roomType.name' => 'Room Type',
                        'total_rooms' => 'Total Rooms',
                        'room_size' => 'Room Size',
                        'base_price_per_night' => 'Base Price',
                        'property.name' => 'Property',
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_rooms_added_yet'))
            ->emptyStateDescription(__('admin.no_rooms_added_yet_description'))
            ->emptyStateIcon('heroicon-o-building-office')
            ->defaultPaginationPageOption(10);
    }

    // ── Header Actions ─────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            $this->getAddRoomAction(),
        ];
    }

    private function getAddRoomAction(): Action
    {
        return Action::make('addRoom')
            ->label(__('admin.add_room'))
            ->icon('heroicon-o-plus')
            ->visible(fn (): bool => Property::query()
                ->where('country_id', auth()->user()->current_country_id)
                ->exists())
            ->disabled(fn (): bool => ! static::canCreate())
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading(__('admin.add_new_room'))
            ->modalWidth('xl')
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalSubmitActionLabel(fn (): string => $this->modalStep === 1 ? __('admin.next') : __('admin.save_room'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->mountUsing(function (Schema $form): void {
                $this->editingPropertyRoomId = null;
                $this->modalStep = 1;

                $form->fill([
                    'property_id' => $this->getCurrentProperty()?->id,
                    'room_type_id' => null,
                    'room_name' => '',
                    'bed_type' => '',
                    'max_guests' => '',
                    'room_description' => '',
                    'room_images' => [],
                    'room_amenities' => [],
                    'room_size' => '',
                    'base_price_per_night' => '',
                ]);
            })
            ->schema(fn (): array => $this->getModalSchema())
            ->action(function (array $data): void {
                if ($this->modalStep === 1) {
                    $this->step1Data = $data;
                    $this->modalStep = 2;
                    $this->halt();
                }

                $allData = array_merge($this->step1Data, $data);
                $property = $this->getCurrentProperty()
                    ?? Property::query()->find($allData['property_id']);

                try {
                    app(PropertyService::class)->saveRoomWithType($property, $allData);
                } catch (UniqueConstraintViolationException) {
                    Notification::make()
                        ->title(__('admin.room_type_already_exists'))
                        ->danger()
                        ->send();
                    $this->halt();
                }

                $this->modalStep = 1;
                $this->step1Data = [];

                Notification::make()
                    ->title(__('admin.room_added'))
                    ->success()
                    ->send();
            });
    }

    // ── Modal Schema (2-step) ──────────────────────────────────────────────

    /**
     * @return array<int, Component>
     */
    private function getModalSchema(): array
    {
        $property = $this->getCurrentProperty();
        $excludeRoomTypeIds = [];

        if ($property) {
            $query = $property->rooms();
            if ($this->editingPropertyRoomId) {
                $query->where('id', '!=', $this->editingPropertyRoomId);
            }
            $excludeRoomTypeIds = $query->pluck('room_type_id')->toArray();
        }

        /** @var User $user */
        $user = auth()->user();

        return [
            // Step 1
            Group::make([
                Section::make(__('admin.select_room_type'))
                    ->schema([
                        Select::make('property_id')
                            ->label(__('admin.property'))
                            ->options(
                                Property::query()
                                    ->where('country_id', $user->current_country_id)
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default($this->getCurrentProperty()?->id)
                            ->visible(fn (): bool => $this->getCurrentProperty() === null),

                        Select::make('room_type_id')
                            ->label(__('admin.room_type'))
                            ->options(
                                RoomType::query()
                                    ->where('status', 'active')
                                    ->whereNotIn('id', $excludeRoomTypeIds)
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText(__('admin.room_type_prefill_warning'))
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                if (! $state) {
                                    return;
                                }

                                $roomType = RoomType::query()->with(['images', 'facilities'])->find($state);
                                if (! $roomType) {
                                    return;
                                }

                                $set('room_name', $roomType->name);
                                $set('bed_type', $roomType->bed_type);
                                $set('max_guests', (string) $roomType->max_guests);
                                $set('room_description', $roomType->description);
                                $set('room_images', $roomType->images->pluck('image_path')->toArray());
                                $set('room_amenities', $roomType->facilities->where('status', FacilityStatus::Active)->pluck('id')->toArray());
                            }),
                    ]),

                Section::make(__('admin.basic_details'))
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        TextInput::make('room_name')
                            ->label(__('admin.room_name'))
                            ->placeholder('e.g. Deluxe Ocean Suite')
                            ->required()
                            ->maxLength(255),

                        Grid::make(2)->schema([
                            TextInput::make('bed_type')
                                ->label(__('admin.bed_type'))
                                ->placeholder('e.g. 1 King Bed + 1 Sofa Bed')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('max_guests')
                                ->label(__('admin.max_guests'))
                                ->placeholder('e.g. 3')
                                ->required()
                                ->numeric()
                                ->minValue(1),
                        ]),

                        Textarea::make('room_description')
                            ->label(__('admin.description'))
                            ->placeholder(__('admin.brief_description_of_this_property_type'))
                            ->required()
                            ->rows(4),
                    ]),

                Section::make(__('admin.select_room_amenities'))
                    ->icon('heroicon-o-sparkles')
                    ->description(fn (Get $get): string => count($get('room_amenities') ?? []).' / '.Facility::query()->where('status', 'active')->count().' '.__('admin.selected'))
                    ->schema([
                        CheckboxList::make('room_amenities')
                            ->hiddenLabel()
                            ->options(
                                Facility::query()
                                    ->where('status', 'active')
                                    ->orderBy('sort_order')
                                    ->pluck('name', 'id')
                            )
                            ->columns(2)
                            ->live(),
                    ]),
            ])->visible(fn (): bool => $this->modalStep === 1),

            // Step 2
            Group::make([
                TextInput::make('room_size')
                    ->label(__('admin.room_size').' (sqft)')
                    ->numeric()
                    ->minValue(1)
                    ->placeholder('e.g. 450'),

                Section::make(__('admin.room_price'))
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextInput::make('base_price_per_night')
                            ->label(__('admin.base_price_per_night'))
                            ->helperText(__('admin.base_price_helper'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix($this->getCurrencySymbol()),
                    ]),

                Section::make(__('admin.room_images'))
                    ->icon('heroicon-o-photo')
                    ->description(fn (Get $get): string => __('admin.room_images_max', [
                        'count' => count($get('room_images') ?? []),
                        'max' => config('services.rooms.max_images'),
                    ]))
                    ->schema([
                        FileUpload::make('room_images')
                            ->label(__('admin.upload_images'))
                            ->image()
                            ->multiple()
                            ->maxFiles(config('services.rooms.max_images'))
                            ->maxSize(config('services.rooms.max_image_size_mb') * 1024)
                            ->directory('room-types')
                            ->disk('public')
                            ->reorderable()
                            ->panelLayout('grid')
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->helperText(__('admin.room_images_upload_hint_up_to', [
                                'size' => config('services.rooms.max_image_size_mb'),
                                'max' => config('services.rooms.max_images'),
                            ])),
                    ]),
            ])->visible(fn (): bool => $this->modalStep === 2),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private function getEditRoomSchema(PropertyRoom $record): array
    {
        $roomType = $record->roomType;

        return [
            // Room Details Card (read-only)
            View::make('filament.schemas.components.room-details-card')
                ->viewData(['roomType' => $roomType, 'amenitiesCount' => $roomType->facilities->count()]),

            // Editable fields
            TextInput::make('room_size')
                ->label(__('admin.room_size').' (sqft)')
                ->numeric()
                ->minValue(1)
                ->placeholder('e.g. 450'),

            Section::make(__('admin.room_price'))
                ->icon('heroicon-o-banknotes')
                ->schema([
                    TextInput::make('base_price_per_night')
                        ->label(__('admin.base_price_per_night'))
                        ->helperText(__('admin.base_price_helper'))
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->prefix($this->getCurrencySymbol()),
                ]),

            Section::make(__('admin.room_images'))
                ->icon('heroicon-o-photo')
                ->description(fn (Get $get): string => __('admin.room_images_max', [
                    'count' => count($get('room_images') ?? []),
                    'max' => config('services.rooms.max_images'),
                ]))
                ->schema([
                    FileUpload::make('room_images')
                        ->label(__('admin.upload_images'))
                        ->image()
                        ->multiple()
                        ->maxFiles(config('services.rooms.max_images'))
                        ->maxSize(config('services.rooms.max_image_size_mb') * 1024)
                        ->directory('room-types')
                        ->disk('public')
                        ->reorderable()
                        ->panelLayout('grid')
                        ->acceptedFileTypes(['image/jpeg', 'image/png'])
                        ->helperText(__('admin.room_images_upload_hint_up_to', [
                            'size' => config('services.rooms.max_image_size_mb'),
                            'max' => config('services.rooms.max_images'),
                        ])),
                ]),

            Section::make(__('admin.select_room_amenities'))
                ->icon('heroicon-o-sparkles')
                ->description(fn (Get $get): string => count($get('room_amenities') ?? []).' / '.Facility::query()->where('status', 'active')->count().' '.__('admin.selected'))
                ->schema([
                    CheckboxList::make('room_amenities')
                        ->hiddenLabel()
                        ->options(
                            Facility::query()
                                ->where('status', 'active')
                                ->orderBy('sort_order')
                                ->pluck('name', 'id')
                        )
                        ->columns(2)
                        ->live(),
                ]),
        ];
    }
}
