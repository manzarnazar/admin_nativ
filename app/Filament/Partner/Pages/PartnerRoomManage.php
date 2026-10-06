<?php

namespace App\Filament\Partner\Pages;

use App\Enums\FacilityStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Partner\Concerns\HasPartnerDemoGuard;
use App\Filament\Partner\Enums\NavigationGroup;
use App\Models\Facility;
use App\Models\Partner;
use App\Models\RoomType;
use App\Models\User;
use App\Services\RoomTypeService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class PartnerRoomManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPartnerDemoGuard;
    use InteractsWithTable;
    use RequiresApprovedPartner;

    protected static ?string $slug = 'rooms';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.partner.pages.room-manage';

    public function getTitle(): string|Htmlable
    {
        return __('admin.room_types');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.room_types');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::RoomManagement;
    }

    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            PartnerRoomTypeView::getRouteName(),
        ];
    }

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.room_types');
    }

    public function getSubheading(): ?string
    {
        return __('admin.manage_room_types_description');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    // ── Scoping Helpers ─────────────────────────────────────────────────────

    private function getPartner(): ?Partner
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->partner;
    }

    public function hasRoomTypes(): bool
    {
        return RoomType::query()->exists();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getAddRoomTypeAction(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(RoomType::query()->with(['images', 'facilities']))
            ->columns([
                ImageColumn::make('first_image')
                    ->label(__('admin.room_types'))
                    ->square()
                    ->imageSize(48)
                    ->disk('public')
                    ->defaultImageUrl(fn (): string => asset('images/placeholder-room.png'))
                    ->getStateUsing(function (RoomType $record): ?string {
                        $firstImage = $record->images->first();

                        return $firstImage?->image_path;
                    })
                    ->grow(false)
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('')
                    ->searchable()
                    ->limit(30)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('slug')
                    ->label(__('admin.slug'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
                TextColumn::make('max_guests')
                    ->label(__('admin.specification'))
                    ->formatStateUsing(fn (RoomType $record): HtmlString => new HtmlString(
                        '<span class="inline-flex items-center gap-1.5">'
                        .svg('icon-users', 'h-6 w-6')->toHtml()
                        .'<span class="text-gray-500">'.__('admin.max_guests').' : </span>'
                        .'<span class="font-semibold text-gray-950 dark:text-white">'.$record->max_guests.' '.str('adult')->plural($record->max_guests).'</span>'
                        .'</span>'
                    ))
                    ->description(fn (RoomType $record): HtmlString => new HtmlString(
                        '<span class="inline-flex items-center gap-1.5">'
                        .svg('icon-bed', 'h-6 w-6')->toHtml()
                        .'<span class="text-gray-500">'.__('admin.bed_type').' : </span>'
                        .'<span class="font-semibold text-gray-950 dark:text-white">'.e($record->bed_type).'</span>'
                        .'</span>'
                    ), position: 'below')
                    ->toggleable(),
            ])
            ->filters([])
            ->recordActions([
                $this->getViewAction(),
                $this->getEditAction(),
                $this->getDeleteAction(),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('room-types')
                    ->exports([
                        'name' => 'Room Name',
                        'bed_type' => 'Bed Type',
                        'max_guests' => 'Max Guests',
                        'description' => ['label' => 'Description', 'formatter' => fn (RoomType $record): string => strip_tags((string) $record->description)],
                        'status' => ['label' => 'Status', 'formatter' => fn (RoomType $record): string => $record->status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_room_types_available'))
            ->emptyStateDescription(__('admin.no_room_types_description'))
            ->emptyStateIcon('heroicon-o-home-modern')
            ->defaultPaginationPageOption(10);
    }

    private function getAddRoomTypeAction(): Action
    {
        return Action::make('addRoomType')
            ->label(__('admin.add_room_type'))
            ->modalHeading(__('admin.add_new_room_type'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('3xl')
            ->steps([
                Step::make(__('admin.basic_details'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema($this->getBasicDetailsSchema())
                    ->columns(2),
                Step::make(__('admin.select_room_amenities'))
                    ->icon('heroicon-o-check-circle')
                    ->schema($this->getAmenitiesSchema()),
                Step::make(__('admin.room_images'))
                    ->icon('heroicon-o-photo')
                    ->schema($this->getImagesSchema()),
            ])
            ->modalSubmitActionLabel(__('admin.save_room_type'))
            ->action(function (array $data): void {
                $partner = $this->getPartner();
                $facilityIds = $data['facility_ids'] ?? [];
                $images = $data['images'] ?? [];

                app(RoomTypeService::class)->createRoomType(
                    data: [
                        'partner_id' => $partner?->id,
                        'name' => $data['name'],
                        'bed_type' => $data['bed_type'],
                        'max_guests' => $data['max_guests'],
                        'description' => $data['description'],
                        'meta_title' => $data['meta_title'] ?? null,
                        'meta_description' => $data['meta_description'] ?? null,
                        'meta_keywords' => $data['meta_keywords'] ?? null,
                        'schema_markup' => $data['schema_markup'] ?? null,
                    ],
                    facilityIds: $facilityIds,
                    images: $images,
                );

                Notification::make()
                    ->title(__('admin.room_type_created_successfully'))
                    ->success()
                    ->send();
            });
    }

    private function getEditAction(): Action
    {
        return Action::make('edit')
            ->iconButton()
            ->icon('phosphor-pencil-simple-line')
            ->color('gray')
            ->modalHeading(__('admin.edit_room_type'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('3xl')
            ->modalSubmitActionLabel(__('admin.save_room_type'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->before($this->enforceEditPermission())
            ->fillForm(fn (RoomType $record): array => [
                'name' => $record->name,
                'bed_type' => $record->bed_type,
                'max_guests' => $record->max_guests,
                'description' => $record->description,
                'facility_ids' => $record->facilities->pluck('id')->toArray(),
                'images' => $record->images->pluck('image_path')->toArray(),
                'meta_title' => $record->meta_title,
                'meta_description' => $record->meta_description,
                'meta_keywords' => $record->meta_keywords,
                'schema_markup' => $record->schema_markup,
            ])
            ->schema(function (): array {
                return [
                    Text::make(new HtmlString(
                        '<div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">'.
                        '<div class="flex items-start gap-3">'.
                        '<div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900">'.
                        svg('heroicon-o-exclamation-triangle', 'h-5 w-5 text-red-600 dark:text-red-400')->toHtml().
                        '</div>'.
                        '<div>'.
                        '<p class="text-sm font-semibold text-red-800 dark:text-red-200">'.__('admin.edit_room_type_warning').'</p>'.
                        '</div>'.
                        '</div>'.
                        '</div>'
                    ))->extraAttributes(['class' => 'block w-full']),

                    Section::make(__('admin.basic_details'))
                        ->icon('heroicon-o-clipboard-document-list')
                        ->schema($this->getBasicDetailsSchema())
                        ->columns(2),

                    ...$this->getImagesSchema(),

                    Section::make(__('admin.room_amenities'))
                        ->icon('heroicon-o-check-circle')
                        ->schema($this->getAmenitiesSchema()),
                ];
            })
            ->action(function (RoomType $record, array $data): void {
                $facilityIds = $data['facility_ids'] ?? [];
                $images = $data['images'] ?? [];

                app(RoomTypeService::class)->updateRoomType(
                    roomType: $record,
                    data: [
                        'name' => $data['name'],
                        'bed_type' => $data['bed_type'],
                        'max_guests' => $data['max_guests'],
                        'description' => $data['description'],
                        'meta_title' => $data['meta_title'] ?? null,
                        'meta_description' => $data['meta_description'] ?? null,
                        'meta_keywords' => $data['meta_keywords'] ?? null,
                        'schema_markup' => $data['schema_markup'] ?? null,
                    ],
                    facilityIds: $facilityIds,
                    images: $images,
                );

                Notification::make()
                    ->title(__('admin.room_type_updated_successfully'))
                    ->success()
                    ->send();
            });
    }

    private function getViewAction(): Action
    {
        return Action::make('view')
            ->iconButton()
            ->icon('phosphor-eye')
            ->color('gray')
            ->url(fn (RoomType $record): string => PartnerRoomTypeView::getUrl(['record' => $record->id]));
    }

    private function getDeleteAction(): Action
    {
        return Action::make('delete')
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('gray')
            ->before($this->enforceDeletePermission())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalHeading(__('admin.delete_room_type'))
            ->modalDescription(__('admin.delete_room_type_confirmation'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->action(function (RoomType $record): void {
                try {
                    app(RoomTypeService::class)->deleteRoomType($record);
                } catch (ValidationException) {
                    Notification::make()
                        ->title(__('admin.room_type_has_associated_rooms'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('admin.room_type_deleted_successfully'))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, Component>
     */
    private function getBasicDetailsSchema(): array
    {
        return [
            TextInput::make('name')
                ->label(__('admin.room_name'))
                ->placeholder(__('admin.room_type_name_placeholder'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            TextInput::make('bed_type')
                ->label(__('admin.bed_type'))
                ->placeholder(__('admin.bed_type_placeholder'))
                ->required()
                ->maxLength(255),
            TextInput::make('max_guests')
                ->label(__('admin.max_guests'))
                ->placeholder(__('admin.max_guests_placeholder'))
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(50),
            RichEditor::make('description')
                ->label(__('admin.description'))
                ->placeholder(__('admin.description_placeholder'))
                ->required()
                ->columnSpanFull(),
            TextInput::make('meta_title')
                ->label(__('admin.meta_title'))
                ->placeholder(__('admin.meta_title_placeholder'))
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('meta_description')
                ->label(__('admin.meta_description'))
                ->placeholder(__('admin.meta_description_placeholder'))
                ->maxLength(500)
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('meta_keywords')
                ->label(__('admin.meta_keywords'))
                ->placeholder(__('admin.meta_keywords_placeholder'))
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('schema_markup')
                ->label(__('admin.schema_markup'))
                ->placeholder(__('admin.schema_markup_placeholder'))
                ->helperText(new HtmlString(__('admin.schema_markup_helper')))
                ->rows(5)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private function getAmenitiesSchema(): array
    {
        return [
            CheckboxList::make('facility_ids')
                ->hiddenLabel()
                ->options(
                    Facility::query()
                        ->where('status', FacilityStatus::Active)
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                )
                ->columns(2)
                ->gridDirection('row'),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private function getImagesSchema(): array
    {
        return [
            Section::make(__('admin.room_images'))
                ->icon('heroicon-o-photo')
                ->afterHeader([
                    Text::make(fn (Get $get): string => __('admin.room_images_max', [
                        'count' => count($get('images') ?? []),
                        'max' => config('services.rooms.max_images'),
                    ]))->color('danger'),
                ])
                ->schema([
                    FileUpload::make('images')
                        ->label(__('admin.upload_images'))
                        ->multiple()
                        ->image()
                        ->automaticallyResizeImagesMode('cover')
                        ->automaticallyCropImagesToAspectRatio('795:663')
                        ->maxSize(config('services.rooms.max_image_size_mb') * 1024)
                        ->minFiles(config('services.rooms.min_images'))
                        ->maxFiles(config('services.rooms.max_images'))
                        ->directory('room-types')
                        ->disk('public')
                        ->reorderable()
                        ->panelLayout('grid')
                        ->acceptedFileTypes(['image/jpeg', 'image/png'])
                        ->helperText($this->roomImagesUploadHint())
                        ->live()
                        ->required(),
                ]),
        ];
    }

    private function roomImagesUploadHint(): string
    {
        $min = config('services.rooms.min_images');
        $max = config('services.rooms.max_images');
        $size = config('services.rooms.max_image_size_mb');

        if ($min === $max) {
            return __('admin.room_images_upload_hint_exactly', ['size' => $size, 'max' => $max]);
        }

        return __('admin.room_images_upload_hint_range', ['size' => $size, 'min' => $min, 'max' => $max]);
    }
}
