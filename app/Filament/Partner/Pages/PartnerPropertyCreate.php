<?php

namespace App\Filament\Partner\Pages;

use App\Actions\GenerateFloorsAction;
use App\Enums\AnswerType;
use App\Enums\FacilityStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyVerificationStatus;
use App\Enums\RegistrationFieldType;
use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Partner\Concerns\HasPartnerDemoGuard;
use App\Models\CancellationPolicy;
use App\Models\Country;
use App\Models\Facility;
use App\Models\FacilityCategory;
use App\Models\Partner;
use App\Models\Property;
use App\Models\PropertyRegistrationValue;
use App\Models\PropertyRoom;
use App\Models\PropertyRule;
use App\Models\PropertyRuleQuestion;
use App\Models\RefCity;
use App\Models\RefCountry;
use App\Models\RefState;
use App\Models\RegistrationField;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\User;
use App\Services\CancellationPolicyService;
use App\Services\CommissionService;
use App\Services\PropertyService;
use App\Support\DemoMode;
use App\Support\MapProvider;
use App\Support\PartnerContext;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\RawJs;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * @property-read Schema $form
 */
class PartnerPropertyCreate extends Page implements DeclaresTopbarControls
{
    use HasPartnerDemoGuard;
    use RequiresApprovedPartner;

    protected static ?string $slug = 'properties/create';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    protected string $view = 'filament.partner.pages.property-create';

    public ?int $propertyId = null;

    public int $currentStep = 1;

    public bool $isDraftSave = false;

    public int $totalSteps = 8;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array<int> Selected facility IDs for Step 2 */
    public array $selectedFacilities = [];

    public ?int $activeCategoryId = null;

    /** @var string|null Room form mode: null=list, 'create', 'edit' */
    public ?string $roomFormMode = null;

    public ?int $editingRoomId = null;

    /** @var array<string, mixed> Room form state */
    public ?array $roomData = [];

    /** @var array<string, mixed>|null */
    public ?array $cancellationCutoffData = [];

    public string $cancellationPolicySource = 'admin_default';

    /** @var array<string, mixed>|null */
    public ?array $cancellationRuleData = [];

    /** @var array<int, array<string, mixed>> */
    public array $cancellationRulesList = [];

    public ?int $editingCancellationRuleId = null;

    public bool $showCancellationRuleForm = false;

    public function getTitle(): string|Htmlable
    {
        return $this->propertyId
            ? __('admin.edit_property')
            : __('admin.add_new_property');
    }

    public function getHeading(): string|Htmlable
    {
        return $this->propertyId
            ? __('admin.edit_property')
            : __('admin.add_new_property');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        $record = request()->query('record');

        if ($record) {
            $property = Property::query()->findOrFail($record);

            /** @var User $user */
            $user = auth()->user();

            // Guard: property must belong to this partner
            if ($property->partner_id !== $user->partner?->id) {
                $this->redirect(PartnerPropertiesManage::getUrl());

                return;
            }

            // Suspended properties are fully locked — partner cannot edit them
            if ($property->status === PropertyStatus::Suspended) {
                Notification::make()
                    ->title(__('admin.property_suspended_cannot_edit'))
                    ->danger()
                    ->send();

                $this->redirect(PartnerPropertiesManage::getUrl());

                return;
            }

            $this->propertyId = $property->id;
            $this->currentStep = $property->completed_step >= $this->totalSteps
                ? 1
                : $property->completed_step + 1;
        }

        $this->fillFormForCurrentStep();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make($this->getStep1Schema())->visible(fn () => $this->currentStep === 1),
                Group::make($this->getStep2Schema())->visible(fn () => $this->currentStep === 2),
                Group::make($this->getStep3Schema())->visible(fn () => $this->currentStep === 3),
                Group::make($this->getStep4Schema())->visible(fn () => $this->currentStep === 4),
                Group::make($this->getStep5Schema())->visible(fn () => $this->currentStep === 5),
                Group::make($this->getStep6Schema())->visible(fn () => $this->currentStep === 6),
                Group::make($this->getStep7Schema())->visible(fn () => $this->currentStep === 7),
                Group::make($this->getStep8Schema())->visible(fn () => $this->currentStep === 8),
            ])
            ->statePath('data');
    }

    public function roomForm(Schema $schema): Schema
    {
        return $schema
            ->components($this->getRoomFormSchema())
            ->statePath('roomData');
    }

    public function cancellationCutoffForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TimePicker::make('cutoff_time')
                    ->label(__('admin.cancellation_cutoff_time'))
                    ->required()
                    ->seconds(false),
            ])
            ->statePath('cancellationCutoffData');
    }

    public function cancellationRuleForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('days_before')
                        ->label(__('admin.days_before_checkin'))
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefixIcon('heroicon-o-calendar')
                        ->live(onBlur: true),
                    Select::make('is_refundable')
                        ->label(__('admin.is_this_refundable'))
                        ->options([
                            'refundable' => __('admin.yes_refundable'),
                            'non_refundable' => __('admin.non_refundable'),
                        ])
                        ->required()
                        ->live(),
                    TextInput::make('refund_percent')
                        ->label(__('admin.refund_percentage'))
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01)
                        ->rules(['numeric', 'between:0,100'])
                        ->suffix('%')
                        ->visible(fn (Get $get): bool => $get('is_refundable') === 'refundable')
                        ->live(onBlur: true),
                ]),
            ])
            ->statePath('cancellationRuleData');
    }

    public function getProperty(): ?Property
    {
        if (! $this->propertyId) {
            return null;
        }

        return Property::query()->find($this->propertyId);
    }

    // ── Scoping Helpers ─────────────────────────────────────────────────────

    private function getPartner(): ?Partner
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->partner;
    }

    private function getCurrentCountryId(): ?int
    {
        $partner = $this->getPartner();

        return $partner ? PartnerContext::currentCountryId($partner) : null;
    }

    public function goToStep(int $step): void
    {
        $property = $this->getProperty();

        if ($property && $step <= $property->completed_step + 1) {
            $this->currentStep = $step;
            $this->fillFormForCurrentStep();
        }

        if ($step === 1 && $this->currentStep !== 1) {
            $this->currentStep = 1;
            $this->fillFormForCurrentStep();
        }
    }

    public function nextStep(): void
    {
        $this->saveCurrentStep();
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
            $this->fillFormForCurrentStep();
        }
    }

    public function saveCurrentStep(): void
    {
        match ($this->currentStep) {
            1 => $this->saveStep1(),
            2 => $this->saveStep2(),
            3 => $this->saveStep3(),
            4 => $this->saveStep4(),
            5 => $this->saveStep5(),
            6 => $this->saveStep6(),
            7 => $this->saveStep7(),
            8 => $this->saveStep8(),
            default => null,
        };
    }

    public function submitProperty(): void
    {
        $this->saveCurrentStep();
    }

    public function saveDraft(): void
    {
        $this->isDraftSave = true;
        $this->saveCurrentStep();
        $this->isDraftSave = false;
    }

    // ── Step 1: Basic Details ──────────────────────────────────────────────

    private function saveStep1(): void
    {
        $formData = $this->form->getState();
        $partner = $this->getPartner();
        $service = app(PropertyService::class);

        $propertyData = [
            'partner_id' => $partner?->id,
            'country_id' => $this->getCurrentCountryId(),
            'property_type_id' => $partner?->property_type_id,
            'name' => $formData['name'],
            'description' => $formData['description'],
            'meta_title' => $formData['meta_title'] ?? null,
            'meta_description' => $formData['meta_description'] ?? null,
            'meta_keywords' => $formData['meta_keywords'] ?? null,
            'schema_markup' => $formData['schema_markup'] ?? null,
            'phone' => $formData['phone'],
            'dial_code' => $formData['dial_code'] ?? null,
            'email' => $formData['email'],
            'landline' => $formData['landline'] ?? null,
            'landline_dial_code' => $formData['landline_dial_code'] ?? null,
            'street_address' => $formData['street_address'] ?? null,
            'ref_state_id' => $formData['ref_state_id'] ?? null,
            'ref_city_id' => $formData['ref_city_id'] ?? null,
            'zip_code' => $formData['zip_code'] ?? null,
            'timezone' => $formData['timezone'] ?? null,
            'latitude' => $formData['latitude'] ?? null,
            'longitude' => $formData['longitude'] ?? null,
            'bank_account_holder' => $formData['bank_account_holder'],
            'bank_name' => $formData['bank_name'],
            'bank_account_number' => $formData['bank_account_number'],
            'bank_code' => $formData['bank_code'],
            'total_floors' => $formData['total_floors'] ?? null,
        ];

        $before = null;

        if ($this->propertyId) {
            $property = $this->getProperty();

            $newFloorCount = (int) ($formData['total_floors'] ?? 0);
            $blockedFloors = app(GenerateFloorsAction::class)->getFloorsBlockingReduction($property, $newFloorCount);

            if ($blockedFloors->isNotEmpty()) {
                $names = $blockedFloors->pluck('name')->join(', ');
                Notification::make()
                    ->title(__('admin.cannot_reduce_floors'))
                    ->body(__('admin.floors_have_rooms_body', ['floors' => $names]))
                    ->danger()
                    ->send();

                return;
            }

            $before = $property->getAttributes();
            $property->disableLogging();
            $service->updateBasicDetails($property, $propertyData);
        } else {
            // properties.verification_status defaults to 'approved' at the DB level so
            // single-mode's admin-created properties (PropertyCreate.php) go live without
            // a review step. Partner-created properties must not inherit that default —
            // they start unreviewed until submitForVerification() runs at step 8.
            $propertyData['verification_status'] = PropertyVerificationStatus::Pending;
            $property = $service->createProperty($propertyData);
            $this->propertyId = $property->id;
        }

        app(GenerateFloorsAction::class)->handle($property);

        Notification::make()
            ->title(__('admin.step_saved_successfully'))
            ->success()
            ->send();

        $this->logEditIfApproved($property, $before);

        if ($this->isDraftSave) {
            $property = $this->getProperty();
            if ($property) {
                $this->applyDraftFinalize($property);
            }
            $this->redirect(PartnerPropertiesManage::getUrl());

            return;
        }

        $this->currentStep = 2;
        $this->fillFormForCurrentStep();
    }

    // ── Step 2: Property Facilities ────────────────────────────────────────

    public function toggleFacility(int $facilityId): void
    {
        if (in_array($facilityId, $this->selectedFacilities)) {
            $this->selectedFacilities = array_values(
                array_diff($this->selectedFacilities, [$facilityId])
            );
        } else {
            $this->selectedFacilities[] = $facilityId;
        }
    }

    public function setActiveCategory(int $categoryId): void
    {
        $this->activeCategoryId = $categoryId;
    }

    public function getFacilityCategories(): Collection
    {
        return FacilityCategory::query()
            ->where('status', 'active')
            ->with(['facilities' => fn ($q) => $q->where('status', 'active')->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    public function getSelectedCountForCategory(int $categoryId): int
    {
        $category = FacilityCategory::query()
            ->with(['facilities' => fn ($q) => $q->where('status', 'active')])
            ->find($categoryId);

        if (! $category) {
            return 0;
        }

        return $category->facilities->whereIn('id', $this->selectedFacilities)->count();
    }

    private function fillStep2(Property $property): void
    {
        $this->selectedFacilities = $property->facilities()->pluck('facilities.id')->toArray();

        $categories = $this->getFacilityCategories();
        $this->activeCategoryId = $categories->first()?->id;

        $this->form->fill();
    }

    private function saveStep2(): void
    {
        $property = $this->getProperty();
        $service = app(PropertyService::class);

        // No Property columns change here (only the facility_property pivot
        // via completeStep()'s completed_step bump) — nothing meaningful to
        // diff, just suppress the redundant generic auto-log.
        $property->disableLogging();
        $service->syncFacilities($property, $this->selectedFacilities);

        Notification::make()
            ->title(__('admin.step_saved_successfully'))
            ->success()
            ->send();

        $this->logEditIfApproved($property);

        if ($this->isDraftSave) {
            $this->applyDraftFinalize($property);
            $this->redirect(PartnerPropertiesManage::getUrl());

            return;
        }

        $this->currentStep = 3;
        $this->fillFormForCurrentStep();
    }

    private function getStep2Schema(): array
    {
        return [
            View::make('filament.schemas.components.property-facilities-picker'),
        ];
    }

    // ── Step 3: Rooms & Pricing ────────────────────────────────────────────

    public function getPropertyRooms(): Collection
    {
        $property = $this->getProperty();

        if (! $property) {
            return new Collection;
        }

        return $property->rooms()->with(['roomType.images', 'roomType.facilities'])->get();
    }

    public function hasPropertyRooms(): bool
    {
        $property = $this->getProperty();

        return $property && $property->rooms()->exists();
    }

    public function getCurrencySymbol(): string
    {
        $countryId = $this->getCurrentCountryId();

        return ($countryId ? Country::query()->where('id', $countryId)->value('currency_symbol') : null) ?? '$';
    }

    private function getRoomFormSchema(): array
    {
        $property = $this->getProperty();
        $excludeRoomTypeIds = [];

        if ($property) {
            $query = $property->rooms();

            if ($this->editingRoomId) {
                $query->where('id', '!=', $this->editingRoomId);
            }

            $excludeRoomTypeIds = $query->pluck('room_type_id')->toArray();
        }

        return [
            Section::make(__('admin.room_information'))
                ->icon('heroicon-o-building-office')
                ->schema([
                    Select::make('room_type_id')
                        ->label(__('admin.room_type'))
                        ->options(
                            RoomType::query()
                                ->where('status', 'active')
                                ->whereNotIn('id', $excludeRoomTypeIds)
                                ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->preload()
                        ->live()
                        ->helperText(fn (Get $get): string => $get('room_type_id')
                            ? __('admin.room_type_prefill_warning')
                            : __('admin.room_type_optional_helper'))
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if (! $state) {
                                return;
                            }

                            $roomType = RoomType::query()->with(['images', 'facilities'])->find($state);

                            if (! $roomType) {
                                return;
                            }

                            $set('room_name', $roomType->name);
                            $set('bed_type', $roomType->bed_type);
                            $set('max_guests', $roomType->max_guests);
                            $set('room_description', $roomType->description);
                            $set('room_images', $roomType->images->pluck('image_path')->toArray());
                            $set('room_amenities', $roomType->facilities->where('status', FacilityStatus::Active)->pluck('id')->toArray());
                        }),

                    TextInput::make('room_name')
                        ->label(__('admin.room_name'))
                        ->placeholder('e.g. Deluxe Ocean Suite')
                        ->required()
                        ->maxLength(255)
                        ->disabled(fn (): bool => DemoMode::isActive())
                        ->dehydrated(),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('bed_type')
                                ->label(__('admin.bed_type'))
                                ->placeholder('e.g. 1 King Bed + 1 Sofa Bed')
                                ->required()
                                ->maxLength(255)
                                ->disabled(fn (): bool => DemoMode::isActive())
                                ->dehydrated(),

                            TextInput::make('max_guests')
                                ->label(__('admin.max_guests'))
                                ->placeholder('e.g. 3')
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->disabled(fn (): bool => DemoMode::isActive())
                                ->dehydrated(),
                        ]),

                    RichEditor::make('room_description')
                        ->label(__('admin.description'))
                        ->placeholder(__('admin.brief_description_of_this_property_type'))
                        ->required()
                        ->columnSpanFull()
                        ->disabled(fn (): bool => DemoMode::isActive())
                        ->dehydrated(),
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
                        ]))
                        ->automaticallyResizeImagesMode('contain')
                        ->automaticallyResizeImagesToWidth(1920)
                        ->automaticallyResizeImagesToHeight(1920)
                        ->automaticallyUpscaleImagesWhenResizing(false)
                        ->live()
                        ->extraAttributes([
                            'x-init' => "\$el.addEventListener('FilePond:removefile', () => \$nextTick(() => \$wire.\$refresh()))",
                        ])
                        ->disabled(fn (): bool => DemoMode::isActive())
                        ->dehydrated(),
                ]),

            Section::make(__('admin.room_amenities'))
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
                        ->live()
                        ->disabled(fn (): bool => DemoMode::isActive())
                        ->dehydrated(),
                ]),

            Section::make(__('admin.room_setup'))
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('base_price_per_night')
                                ->label(__('admin.base_price_per_night'))
                                ->helperText(__('admin.base_price_helper'))
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->prefix($this->getCurrencySymbol()),

                            TextInput::make('room_size')
                                ->label(__('admin.room_size').' (sqft)')
                                ->numeric()
                                ->minValue(1)
                                ->placeholder('e.g. 450'),
                        ]),
                ]),
        ];
    }

    public function showAddRoomForm(): void
    {
        $this->roomFormMode = 'create';
        $this->editingRoomId = null;
        $this->roomForm->fill();
    }

    public function showEditRoomForm(int $roomId): void
    {
        $propertyRoom = PropertyRoom::query()
            ->with(['roomType.images', 'roomType.facilities'])
            ->findOrFail($roomId);
        $roomType = $propertyRoom->roomType;

        $this->roomFormMode = 'edit';
        $this->editingRoomId = $roomId;

        $this->roomForm->fill([
            'room_type_id' => $roomType->id,
            'room_name' => $roomType->name,
            'bed_type' => $roomType->bed_type,
            'max_guests' => $roomType->max_guests,
            'room_description' => $roomType->description,
            'room_images' => $roomType->images->pluck('image_path')->toArray(),
            'room_amenities' => $roomType->facilities->where('status', FacilityStatus::Active)->pluck('id')->toArray(),
            'room_size' => $propertyRoom->room_size,
            'base_price_per_night' => $propertyRoom->base_price_per_night,
        ]);
    }

    public function cancelRoomForm(): void
    {
        $this->roomFormMode = null;
        $this->editingRoomId = null;
        $this->roomForm->fill();
    }

    public function saveRoom(): void
    {
        $data = $this->roomForm->getState();
        $this->saveRoomData($data, $this->editingRoomId);
        $this->roomFormMode = null;
        $this->editingRoomId = null;
        $this->roomForm->fill();
    }

    public function deleteRoomAction(): Action
    {
        return Action::make('deleteRoom')
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('gray')
            ->before(function (Action $action): void {
                if (DemoMode::isActive()) {
                    Notification::make()
                        ->title(__('admin.demo_account_action_not_allowed'))
                        ->danger()
                        ->send();

                    $action->cancel();

                    return;
                }

                if ($this->blockDeleteIfDemoPartner()) {
                    $action->cancel();
                }
            })
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalHeading(__('admin.delete_room'))
            ->modalDescription(__('admin.delete_room_warning'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->action(function (array $arguments): void {
                $propertyRoom = PropertyRoom::query()->findOrFail($arguments['room']);
                app(PropertyService::class)->removeRoom($propertyRoom);

                Notification::make()
                    ->title(__('admin.room_deleted'))
                    ->success()
                    ->send();
            });
    }

    private function saveRoomData(array $data, ?int $propertyRoomId = null): void
    {
        $property = $this->getProperty();

        try {
            app(PropertyService::class)->saveRoomWithType($property, $data, $propertyRoomId, $this->getPartner()?->id);
        } catch (UniqueConstraintViolationException) {
            Notification::make()
                ->title(__('admin.room_type_already_exists'))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($propertyRoomId ? __('admin.room_updated') : __('admin.room_added'))
            ->success()
            ->send();
    }

    private function saveStep3(): void
    {
        $property = $this->getProperty();

        if (! $property->rooms()->exists()) {
            Notification::make()
                ->title(__('admin.at_least_one_room_required'))
                ->danger()
                ->send();

            return;
        }

        // Room data was already saved earlier via saveRoomData()/saveRoom() —
        // this call only advances completed_step, nothing Property-level to diff.
        $property->disableLogging();
        app(PropertyService::class)->completeRoomsStep($property);

        Notification::make()
            ->title(__('admin.step_saved_successfully'))
            ->success()
            ->send();

        $this->logEditIfApproved($property);

        if ($this->isDraftSave) {
            $this->applyDraftFinalize($property);
            $this->redirect(PartnerPropertiesManage::getUrl());

            return;
        }

        $this->currentStep = 4;
        $this->fillFormForCurrentStep();
    }

    private function getStep3Schema(): array
    {
        return [
            View::make('filament.schemas.components.property-rooms-manager'),
        ];
    }

    // ── Step 4: Property Rules ─────────────────────────────────────────────

    private function getStep4Schema(): array
    {
        $schema = [];

        $schema[] = Section::make(__('admin.check_in_and_check_out_times'))
            ->icon('heroicon-o-clock')
            ->schema([
                Grid::make(2)
                    ->schema([
                        TimePicker::make('check_in_time')
                            ->label(__('admin.standard_check_in_time'))
                            ->placeholder('e.g. 02:00 PM')
                            ->required()
                            ->seconds(false),

                        TimePicker::make('check_out_time')
                            ->label(__('admin.standard_check_out_time'))
                            ->placeholder('e.g. 11:00 AM')
                            ->required()
                            ->seconds(false),
                    ]),
            ]);

        $schema[] = Section::make(__('admin.pets_allowed'))
            ->icon('heroicon-o-heart')
            ->description(__('admin.pets_allowed_description'))
            ->schema([
                Radio::make('pets_allowed')
                    ->label(__('admin.are_pets_allowed'))
                    ->boolean()
                    ->inline()
                    ->default(false)
                    ->required()
                    ->live(),

                Textarea::make('pet_policy_details')
                    ->label(__('admin.pet_policy_details'))
                    ->placeholder(__('admin.pet_policy_details_placeholder'))
                    ->rows(3)
                    ->visible(fn (Get $get): bool => (bool) $get('pets_allowed')),
            ]);

        $partner = $this->getPartner();
        $countryId = $this->getCurrentCountryId();

        $rules = PropertyRule::query()
            ->where('status', 'active')
            ->applicableTo($countryId, $partner?->property_type_id)
            ->with(['questions' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        foreach ($rules as $rule) {
            $questionFields = [];

            foreach ($rule->questions as $question) {
                $fieldName = 'rule_answer_'.$question->id;

                $field = match ($question->answer_type) {
                    AnswerType::YesNo => Radio::make($fieldName)
                        ->label($question->question_text)
                        ->options(['Yes' => 'Yes', 'No' => 'No'])
                        ->inline()
                        ->required(),

                    AnswerType::SingleSelect => Radio::make($fieldName)
                        ->label($question->question_text)
                        ->options(
                            $question->normalizedOptions()
                                ->mapWithKeys(fn (array $opt) => [$opt['id'] => $opt['label']])
                                ->toArray()
                        )
                        ->inline()
                        ->required(),

                    AnswerType::MultipleSelect => CheckboxList::make($fieldName)
                        ->label($question->question_text)
                        ->options(
                            $question->normalizedOptions()
                                ->mapWithKeys(fn (array $opt) => [$opt['id'] => $opt['label']])
                                ->toArray()
                        )
                        ->columns(2)
                        ->required(),
                };

                $questionFields[] = $field;
            }

            if (count($questionFields) > 0) {
                $section = Section::make($rule->name)
                    ->schema($questionFields);

                if (filled($rule->icon)) {
                    $iconUrl = Storage::disk('public')->url($rule->icon);
                    $section->heading(new HtmlString(
                        '<div class="flex items-center gap-2">'
                            .'<img src="'.e($iconUrl).'" alt="'.e($rule->name).'" class="h-5 w-5 object-contain" />'
                            .'<span>'.e($rule->name).'</span>'
                            .'</div>'
                    ));
                } else {
                    $section->icon('heroicon-o-clipboard-document-list');
                }

                $schema[] = $section;
            }
        }

        return $schema;
    }

    private function fillStep4(Property $property): void
    {
        $formData = [
            'check_in_time' => $property->check_in_time,
            'check_out_time' => $property->check_out_time,
            'pets_allowed' => $property->pets_allowed ?? false,
            'pet_policy_details' => $property->pet_policy_details,
        ];

        $answers = $property->ruleAnswers()->with('question')->get();

        foreach ($answers as $answer) {
            // answer_value is always array-cast on the model regardless of the question's
            // answer type, but SingleSelect's field (Radio) needs a scalar — unwrap
            // defensively in case it was ever stored as a single-element array.
            $formData['rule_answer_'.$answer->property_rule_question_id] = match ($answer->question?->answer_type) {
                AnswerType::YesNo => PropertyRuleQuestion::normalizeYesNoAnswer($answer->answer_value),
                AnswerType::SingleSelect => is_array($answer->answer_value) ? ($answer->answer_value[0] ?? null) : $answer->answer_value,
                default => $answer->answer_value,
            };
        }

        $this->form->fill($formData);
    }

    private function saveStep4(): void
    {
        $formData = $this->form->getState();
        $property = $this->getProperty();
        $service = app(PropertyService::class);
        $partner = $this->getPartner();
        $countryId = $this->getCurrentCountryId();

        $answers = [];
        $rules = PropertyRule::query()
            ->where('status', 'active')
            ->applicableTo($countryId, $partner?->property_type_id)
            ->with('questions')
            ->get();

        foreach ($rules as $rule) {
            foreach ($rule->questions as $question) {
                $fieldName = 'rule_answer_'.$question->id;

                if (array_key_exists($fieldName, $formData)) {
                    $answers[$question->id] = $formData[$fieldName];
                }
            }
        }

        $before = $property->getAttributes();
        $property->disableLogging();
        $service->saveRuleAnswers($property, [
            'check_in_time' => $formData['check_in_time'],
            'check_out_time' => $formData['check_out_time'],
            'pets_allowed' => $formData['pets_allowed'] ?? false,
            'pet_policy_details' => $formData['pet_policy_details'] ?? null,
            'answers' => $answers,
        ]);

        Notification::make()
            ->title(__('admin.step_saved_successfully'))
            ->success()
            ->send();

        $this->logEditIfApproved($property, $before);

        if ($this->isDraftSave) {
            $this->applyDraftFinalize($property);
            $this->redirect(PartnerPropertiesManage::getUrl());

            return;
        }

        $this->currentStep = 5;
        $this->fillFormForCurrentStep();
    }

    // ── Step 5: Cancellation Policy ───────────────────────────────────────
    // Policy Source choice is stored per-property (properties.cancellation_policy_source).
    // "Custom" edits the SAME partner+country CancellationPolicy record that the
    // standalone PartnerCancellationPolicyManage page manages — not a separate
    // per-property policy. See saas-multimodule-plan.md Section 12.6 / Phase 4 notes.

    private function getActivePartnerPolicy(): CancellationPolicy
    {
        $partner = $this->getPartner();
        $countryId = $this->getCurrentCountryId();

        return app(CancellationPolicyService::class)->getActivePolicyForPartner($partner, $countryId);
    }

    public function getCancellationPolicy(): ?CancellationPolicy
    {
        return $this->getActivePartnerPolicy()->load('rules');
    }

    /**
     * The admin's Country + Property Type default policy — shown read-only so the
     * partner can see exactly what applies when they pick "Admin Default Policy".
     */
    public function getAdminDefaultCancellationPolicy(): ?CancellationPolicy
    {
        $partner = $this->getPartner();
        $countryId = $this->getCurrentCountryId();

        if (! $partner || ! $countryId) {
            return null;
        }

        return app(CancellationPolicyService::class)
            ->getAdminDefaultPolicy($countryId, $partner->property_type_id)
            ->load('rules');
    }

    public function loadCancellationRules(): void
    {
        $this->cancellationRulesList = app(CancellationPolicyService::class)->getRules($this->getActivePartnerPolicy());
    }

    public function saveCancellationCutoffTime(): void
    {
        $data = $this->cancellationCutoffForm->getState();

        app(CancellationPolicyService::class)->saveCutoffTime($this->getActivePartnerPolicy(), $data['cutoff_time']);

        Notification::make()->title(__('admin.cutoff_time_saved_successfully'))->success()->send();
    }

    public function addNewCancellationRule(): void
    {
        $this->editingCancellationRuleId = null;
        $this->cancellationRuleForm->fill([
            'days_before' => null,
            'is_refundable' => 'refundable',
            'refund_percent' => 100,
        ]);
        $this->showCancellationRuleForm = true;
    }

    public function editCancellationRule(int $ruleId): void
    {
        $rule = collect($this->cancellationRulesList)->firstWhere('id', $ruleId);

        if ($rule) {
            $this->editingCancellationRuleId = $rule['id'];
            $this->cancellationRuleForm->fill([
                'days_before' => $rule['days_before_checkin'],
                'is_refundable' => $rule['refund_percentage'] > 0 ? 'refundable' : 'non_refundable',
                'refund_percent' => $rule['refund_percentage'],
            ]);
            $this->showCancellationRuleForm = true;
        }
    }

    public function saveCancellationRule(): void
    {
        $data = $this->cancellationRuleForm->getState();
        $refundPercent = $data['is_refundable'] === 'non_refundable' ? 0 : ($data['refund_percent'] ?? 0);

        $saved = app(CancellationPolicyService::class)->saveRule(
            $this->getActivePartnerPolicy(),
            [
                'days_before_checkin' => $data['days_before'],
                'refund_percentage' => $refundPercent,
            ],
            $this->editingCancellationRuleId,
        );

        if (! $saved) {
            $this->addError('cancellationRuleData.days_before', __('admin.a_rule_for_this_many_days_already_exists'));

            return;
        }

        $this->showCancellationRuleForm = false;
        $this->loadCancellationRules();

        Notification::make()->title(__('admin.rule_saved_successfully'))->success()->send();
    }

    public function cancelCancellationRule(): void
    {
        $this->showCancellationRuleForm = false;
    }

    public function deleteCancellationRuleAction(): Action
    {
        return Action::make('deleteCancellationRule')
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('gray')
            ->before(function (Action $action): void {
                if (DemoMode::isActive()) {
                    Notification::make()
                        ->title(__('admin.demo_account_action_not_allowed'))
                        ->danger()
                        ->send();

                    $action->cancel();

                    return;
                }

                if ($this->blockDeleteIfDemoPartner()) {
                    $action->cancel();
                }
            })
            ->tooltip(__('admin.delete'))
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalHeading(__('admin.delete_cancellation_policy_rule'))
            ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_rule_will_no_longer_apply_to_any_bookings'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->action(function (array $arguments): void {
                $deleted = app(CancellationPolicyService::class)->deleteRule($arguments['ruleId']);

                if (! $deleted) {
                    Notification::make()->title(__('admin.cannot_delete_the_mandatory_0_day_fallback_rule'))->danger()->send();

                    return;
                }

                $this->loadCancellationRules();
                Notification::make()->title(__('admin.rule_deleted'))->success()->send();
            });
    }

    private function getStep5Schema(): array
    {
        return [
            View::make('filament.schemas.components.property-cancellation-policy-editor'),
        ];
    }

    private function fillStep5(Property $property): void
    {
        $policy = $this->getActivePartnerPolicy();
        $this->cancellationCutoffForm->fill([
            'cutoff_time' => $policy->cancellation_cutoff_time ? substr($policy->cancellation_cutoff_time, 0, 5) : '14:00',
        ]);
        $this->loadCancellationRules();
        $this->showCancellationRuleForm = false;
        $this->editingCancellationRuleId = null;
        $this->cancellationPolicySource = $property->cancellation_policy_source?->value ?? 'admin_default';

        // If admin_default was saved but the admin has since removed their policy, fall back to custom.
        if ($this->cancellationPolicySource === 'admin_default') {
            $adminPolicy = $this->getAdminDefaultCancellationPolicy();
            if (! $adminPolicy || $adminPolicy->rules->isEmpty()) {
                $this->cancellationPolicySource = 'custom';
            }
        }

        $this->form->fill();
    }

    private function saveStep5(): void
    {
        if ($this->cancellationPolicySource === 'custom' && empty($this->cancellationRulesList)) {
            Notification::make()
                ->title(__('admin.custom_policy_requires_at_least_one_rule'))
                ->danger()
                ->send();

            return;
        }

        $property = $this->getProperty();

        $before = $property->getAttributes();
        $property->disableLogging();
        $property->update([
            'cancellation_policy_source' => $this->cancellationPolicySource,
        ]);

        app(PropertyService::class)->completeCancellationPolicyStep($property);

        Notification::make()
            ->title(__('admin.step_saved_successfully'))
            ->success()
            ->send();

        $this->logEditIfApproved($property, $before);

        if ($this->isDraftSave) {
            $this->applyDraftFinalize($property);
            $this->redirect(PartnerPropertiesManage::getUrl());

            return;
        }

        $this->currentStep = 6;
        $this->fillFormForCurrentStep();
    }

    // ── Step 6: Payment Configuration ──────────────────────────────────────

    private function getStep6Schema(): array
    {
        return [
            Section::make(__('admin.pay_at_property'))
                ->icon('heroicon-o-banknotes')
                ->description(__('admin.pay_at_property_description'))
                ->schema([
                    Toggle::make('pay_at_property')
                        ->label(__('admin.active'))
                        ->live()
                        ->onColor('primary'),

                    TextInput::make('advance_percentage')
                        ->label(__('admin.advance_percentage_to_collect_online'))
                        ->placeholder('e.g. 20%')
                        ->helperText(__('admin.advance_percentage_helper'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(99)
                        ->suffix('%')
                        ->extraInputAttributes(['min' => 0, 'max' => 99, 'maxlength' => 2, 'oninput' => 'this.value = this.value.slice(0, 2)'])
                        ->required(fn (Get $get): bool => (bool) $get('pay_at_property'))
                        ->visible(fn (Get $get): bool => (bool) $get('pay_at_property'))
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if ($state === null || $state === '') {
                                return;
                            }

                            $value = (int) $state;
                            $set('advance_percentage', (string) max(0, min(99, $value)));
                        })
                        ->rules([
                            fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                if ($value === null || $value === '') {
                                    return;
                                }

                                $property = $this->getProperty();

                                if (! $property) {
                                    return;
                                }

                                $rate = app(CommissionService::class)->resolveRate(
                                    (int) $property->country_id,
                                    $property->partner_id,
                                    (int) $property->property_type_id,
                                );

                                if ((float) $value < $rate) {
                                    $fail(__('admin.advance_percentage_below_commission', ['rate' => rtrim(rtrim(number_format($rate, 2), '0'), '.')]));
                                }
                            },
                        ]),

                    View::make('filament.schemas.components.payment-config-preview'),
                ]),
        ];
    }

    private function fillStep6(Property $property): void
    {
        $this->form->fill([
            'pay_at_property' => $property->pay_at_property ?? false,
            'advance_percentage' => $property->advance_percentage,
        ]);
    }

    private function saveStep6(): void
    {
        $formData = $this->form->getState();
        $property = $this->getProperty();

        $payAtProperty = (bool) ($formData['pay_at_property'] ?? false);

        $before = $property->getAttributes();
        $property->disableLogging();
        app(PropertyService::class)->savePaymentConfig($property, [
            'pay_at_property' => $payAtProperty,
            'advance_percentage' => $payAtProperty ? $formData['advance_percentage'] : null,
        ]);

        Notification::make()
            ->title(__('admin.step_saved_successfully'))
            ->success()
            ->send();

        $this->logEditIfApproved($property, $before);

        if ($this->isDraftSave) {
            $this->applyDraftFinalize($property);
            $this->redirect(PartnerPropertiesManage::getUrl());

            return;
        }

        $this->currentStep = 7;
        $this->fillFormForCurrentStep();
    }

    // ── Step 7: Property Images ────────────────────────────────────────────

    private function getStep7Schema(): array
    {
        return [
            Section::make(__('admin.primary_showcase_media'))
                ->icon('heroicon-o-photo')
                ->description(function (Get $get): string {
                    $imageCount = count($get('primary_images') ?? []);
                    $hasVideo = ! empty($get('primary_video'));
                    $total = $imageCount + ($hasVideo ? 1 : 0);

                    return $total.' / 5 '.__('admin.required').($hasVideo ? ' (1 video + '.$imageCount.' images)' : ' ('.$imageCount.' images)');
                })
                ->schema([
                    FileUpload::make('primary_video')
                        ->label(__('admin.upload_video'))
                        ->helperText(function (): string {
                            $maxKb = $this->getPhpMaxUploadSizeInKb();
                            $sizeLabel = $this->formatFileSize($maxKb);

                            return "Upload 1 video (MP4, MOV, WebM) up to {$sizeLabel}, max 2 minutes. If a video is uploaded, 4 images are required instead of 5.";
                        })
                        ->acceptedFileTypes(['video/mp4', 'video/quicktime', 'video/webm', 'video/x-msvideo'])
                        ->maxFiles(1)
                        ->maxSize($this->getPhpMaxUploadSizeInKb())
                        ->directory('properties/videos')
                        ->disk('public')
                        ->live()
                        ->nullable(),

                    FileUpload::make('primary_images')
                        ->label(__('admin.upload_images'))
                        ->image()
                        ->multiple()
                        ->minFiles(1)
                        ->maxFiles(fn (Get $get): int => empty($get('primary_video')) ? 5 : 4)
                        ->maxSize(config('services.properties.max_image_size_mb') * 1024)
                        ->directory('properties/primary')
                        ->disk('public')
                        ->reorderable()
                        ->panelLayout('grid')
                        ->acceptedFileTypes(['image/jpeg', 'image/png'])
                        ->helperText(__('admin.property_images_upload_hint', [
                            'size' => config('services.properties.max_image_size_mb'),
                        ]))
                        ->automaticallyResizeImagesMode('contain')
                        ->automaticallyResizeImagesToWidth(1920)
                        ->automaticallyResizeImagesToHeight(1920)
                        ->automaticallyUpscaleImagesWhenResizing(false)
                        ->live()
                        ->extraAttributes([
                            'x-init' => "\$el.addEventListener('FilePond:removefile', () => \$nextTick(() => \$wire.\$refresh()))",
                        ])
                        ->required(),
                ]),

            Section::make(__('admin.additional_gallery_photos'))
                ->icon('heroicon-o-photo')
                ->schema([
                    Repeater::make('gallery_groups')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('group_name')
                                ->label(__('admin.group_name'))
                                ->placeholder(__('admin.enter_group_name'))
                                ->required()
                                ->maxLength(100),

                            FileUpload::make('images')
                                ->label(__('admin.upload_images'))
                                ->image()
                                ->multiple()
                                ->maxFiles(5)
                                ->maxSize(config('services.properties.max_image_size_mb') * 1024)
                                ->directory('properties/gallery')
                                ->disk('public')
                                ->reorderable()
                                ->panelLayout('grid')
                                ->acceptedFileTypes(['image/jpeg', 'image/png'])
                                ->helperText(__('admin.property_gallery_images_upload_hint', [
                                    'size' => config('services.properties.max_image_size_mb'),
                                    'max' => 5,
                                ]))
                                ->automaticallyResizeImagesMode('contain')
                                ->automaticallyResizeImagesToWidth(1920)
                                ->automaticallyResizeImagesToHeight(1920)
                                ->automaticallyUpscaleImagesWhenResizing(false)
                                ->required(),
                        ])
                        ->addActionLabel(__('admin.add_photos'))
                        ->defaultItems(0)
                        ->reorderableWithButtons(),
                ]),
        ];
    }

    private function fillStep7(Property $property): void
    {
        $primaryMedia = $property->primaryImages()->orderBy('sort_order')->get();

        $primaryVideo = $primaryMedia->firstWhere('media_type', 'video')?->image_path;
        $primaryImages = $primaryMedia->where('media_type', 'image')->pluck('image_path')->toArray();

        $galleryGroups = $property->galleryImages()
            ->get()
            ->groupBy('group_name')
            ->map(fn ($images, $groupName) => [
                'group_name' => $groupName,
                'images' => $images->pluck('image_path')->toArray(),
            ])
            ->values()
            ->toArray();

        $this->form->fill([
            'primary_video' => $primaryVideo,
            'primary_images' => $primaryImages,
            'gallery_groups' => $galleryGroups,
        ]);
    }

    private function saveStep7(): void
    {
        $formData = $this->form->getState();
        $property = $this->getProperty();

        $primaryImages = array_values($formData['primary_images'] ?? []);
        $primaryVideo = $formData['primary_video'] ?? null;
        $hasVideo = ! empty($primaryVideo);
        $requiredImages = $hasVideo ? 4 : 5;

        if (count($primaryImages) < $requiredImages) {
            Notification::make()
                ->title(
                    $hasVideo
                        ? __('admin.minimum_images_with_video_required', ['count' => $requiredImages])
                        : __('admin.minimum_images_required', ['count' => $requiredImages])
                )
                ->danger()
                ->send();

            return;
        }

        $galleryGroups = collect($formData['gallery_groups'] ?? [])
            ->map(fn (array $group) => [
                'name' => $group['group_name'],
                'images' => array_values($group['images'] ?? []),
            ])
            ->toArray();

        // Only PropertyImage rows change here (plus completed_step) — no
        // Property column diff possible, just suppress the redundant auto-log.
        $property->disableLogging();
        app(PropertyService::class)->saveImages($property, $primaryImages, $galleryGroups, $primaryVideo ?: null);

        Notification::make()
            ->title(__('admin.step_saved_successfully'))
            ->success()
            ->send();

        $this->logEditIfApproved($property);

        if ($this->isDraftSave) {
            $this->applyDraftFinalize($property);
            $this->redirect(PartnerPropertiesManage::getUrl());

            return;
        }

        $this->currentStep = 8;
        $this->fillFormForCurrentStep();
    }

    // ── Step 8: Legal & Compliance ─────────────────────────────────────────

    private function getRegistrationFields(): Collection
    {
        $partner = $this->getPartner();
        $countryId = $this->getCurrentCountryId();

        if (! $countryId) {
            return new Collection;
        }

        return RegistrationField::query()
            ->applicableTo($countryId, $partner?->property_type_id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();
    }

    /**
     * Finalizes the property as draft only when it is not already approved.
     * Approved properties must never be downgraded to draft via a mid-wizard save.
     */
    private function applyDraftFinalize(Property $property): void
    {
        if ($property->verification_status === PropertyVerificationStatus::Approved) {
            return;
        }

        app(PropertyService::class)->finalizeProperty($property, 'draft');
    }

    /**
     * @param  ?array<string, mixed>  $before  Raw attribute snapshot (getAttributes())
     *                                         taken before this step's update logic ran.
     *                                         Omit for steps that don't touch Property's
     *                                         own columns (facilities pivot, rooms, images)
     *                                         — there's nothing meaningful to diff there.
     */
    private function logEditIfApproved(Property $property, ?array $before = null): void
    {
        if ($property->verification_status !== PropertyVerificationStatus::Approved) {
            return;
        }

        $activity = activity()
            ->performedOn($property)
            ->causedBy(auth()->user())
            ->event('updated');

        if ($before !== null) {
            $after = $property->refresh()->getAttributes();
            $old = [];
            $new = [];

            foreach ($before as $key => $oldValue) {
                if (in_array($key, ['updated_at', 'created_at'], true)) {
                    continue;
                }

                $newValue = $after[$key] ?? null;

                if ($newValue != $oldValue) {
                    $old[$key] = $oldValue;
                    $new[$key] = $newValue;
                }
            }

            if (! empty($new)) {
                $activity->withProperties(['old' => $old, 'attributes' => $new]);
            }
        }

        $activity->log('Partner updated property (step '.$this->currentStep.')');
    }

    private function getStep8Schema(): array
    {
        $fields = $this->getRegistrationFields();

        $statusSection = Section::make(__('admin.property_status'))
            ->icon('heroicon-o-signal')
            ->description(__('admin.property_status_description'))
            ->schema([
                Radio::make('property_status')
                    ->label(__('admin.status'))
                    ->options(['inactive' => __('admin.inactive'), 'active' => __('admin.active')])
                    ->descriptions(['inactive' => __('admin.inactive_status_description'), 'active' => __('admin.active_status_description')])
                    ->default('active')
                    ->required(),
            ]);

        if ($fields->isEmpty()) {
            return [$statusSection];
        }

        $schema = [];

        foreach ($fields as $field) {
            $fieldName = 'reg_field_'.$field->id;

            $component = match ($field->field_type) {
                RegistrationFieldType::NumberInput => TextInput::make($fieldName)
                    ->label($field->name)
                    ->numeric()
                    ->minLength($field->min_number)
                    ->maxLength($field->max_number)
                    ->helperText($this->digitsHelperText($field->min_number, $field->max_number)),

                RegistrationFieldType::TextField => TextInput::make($fieldName)
                    ->label($field->name)
                    ->maxLength($field->max_length)
                    ->helperText($field->max_length ? __('admin.field_characters_max', ['max' => $field->max_length]) : null),

                RegistrationFieldType::TextArea => Textarea::make($fieldName)
                    ->label($field->name)
                    ->maxLength($field->max_length)
                    ->rows(4)
                    ->helperText($field->max_length ? __('admin.field_characters_max', ['max' => $field->max_length]) : null),

                RegistrationFieldType::Checkboxes => CheckboxList::make($fieldName)
                    ->label($field->name)
                    ->options(
                        collect($field->options)
                            ->mapWithKeys(fn (string $opt) => [$opt => $opt])
                            ->toArray()
                    )
                    ->columns(2),

                RegistrationFieldType::Date => DatePicker::make($fieldName)
                    ->label($field->name),

                RegistrationFieldType::Dropdown => Select::make($fieldName)
                    ->label($field->name)
                    ->options(
                        collect($field->options)
                            ->mapWithKeys(fn (string $opt) => [$opt => $opt])
                            ->toArray()
                    )
                    ->searchable(),

                RegistrationFieldType::FileUpload => FileUpload::make($fieldName)
                    ->label($field->name)
                    ->maxSize($field->max_file_size ? $field->max_file_size * 1024 : 2048)
                    ->directory('properties/registration')
                    ->disk('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png'])
                    ->helperText(__('admin.maximum_size_supported_files', [
                        'size' => ($field->max_file_size ?: 5).'MB',
                        'types' => 'JPG/PNG',
                    ])),
            };

            if ($field->is_mandatory) {
                $component = $component->required();
            }

            if (in_array($field->field_type, [RegistrationFieldType::FileUpload])) {
                $sectionHeading = $field->is_mandatory
                    ? new HtmlString(e($field->name).'<span class="text-red-600 ms-0.5">*</span>')
                    : $field->name;

                $schema[] = Section::make($sectionHeading)
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        $component->hiddenLabel(),
                    ]);
            } else {
                $schema[] = $component;
            }
        }

        $schema[] = $statusSection;

        return $schema;
    }

    private function digitsHelperText(?int $min, ?int $max): ?string
    {
        return match (true) {
            $min && $max && $min === $max => __('admin.field_digits_exact', ['count' => $max]),
            $min && $max => __('admin.field_digits_between', ['min' => $min, 'max' => $max]),
            (bool) $max => __('admin.field_digits_max', ['max' => $max]),
            (bool) $min => __('admin.field_digits_min', ['min' => $min]),
            default => null,
        };
    }

    private function fillStep8(Property $property): void
    {
        $existingValues = $property->registrationValues()->get();
        $formData = [];

        $formData['property_status'] = $property->status?->value ?? 'active';

        foreach ($existingValues as $regValue) {
            $formData['reg_field_'.$regValue->registration_field_id] = $regValue->value;
        }

        $this->form->fill($formData);
    }

    private function saveStep8(): void
    {
        $formData = $this->form->getState();
        $property = $this->getProperty();
        $fields = $this->getRegistrationFields();

        $values = [];
        foreach ($fields as $field) {
            $fieldName = 'reg_field_'.$field->id;

            if (array_key_exists($fieldName, $formData)) {
                $values[$field->id] = $formData[$fieldName];
            }
        }

        $existingValues = PropertyRegistrationValue::query()
            ->where('property_id', $property->id)
            ->pluck('value', 'registration_field_id');

        $before = $property->getAttributes();
        $property->disableLogging();

        $service = app(PropertyService::class);
        $service->saveRegistrationValues($property, $values);

        $isApproved = $property?->verification_status === PropertyVerificationStatus::Approved;

        if ($isApproved) {
            $oldProps = [];
            $newProps = [];
            $fileFields = [];
            $formatScalar = fn (mixed $v): string => is_float($v) ? sprintf('%.0f', $v) : (string) $v;

            foreach ($fields as $field) {
                if (! array_key_exists($field->id, $values)) {
                    continue;
                }
                $oldRaw = $existingValues->get($field->id);
                $newRaw = $values[$field->id];
                $newNormalized = is_array($newRaw) ? $newRaw : [$newRaw];
                if ($oldRaw == $newNormalized) {
                    continue;
                }
                $label = ucwords($field->name);
                if ($field->field_type === RegistrationFieldType::FileUpload) {
                    $fileFields[] = $label;
                    $oldPaths = array_values(array_filter((array) $oldRaw));
                    $newPaths = array_values(array_filter((array) $newRaw));
                    $oldProps[$label] = $oldPaths ? implode('|', $oldPaths) : '—';
                    $newProps[$label] = $newPaths ? implode('|', $newPaths) : '—';
                } else {
                    $oldProps[$label] = implode(', ', array_map($formatScalar, array_filter((array) $oldRaw))) ?: '—';
                    $newProps[$label] = implode(', ', array_map($formatScalar, array_filter((array) $newNormalized))) ?: '—';
                }
            }

            if (! empty($newProps)) {
                $props = ['old' => $oldProps, 'attributes' => $newProps];
                if (! empty($fileFields)) {
                    $props['file_fields'] = $fileFields;
                }
                activity()
                    ->performedOn($property)
                    ->causedBy(auth()->user())
                    ->event('registration_fields_updated')
                    ->withProperties($props)
                    ->log('Partner updated property registration details');
            }

            if (! $this->isDraftSave) {
                $service->finalizeProperty($property, $formData['property_status'] ?? $property->status?->value ?? 'active');
            }

            $this->logEditIfApproved($property, $before);
        } elseif ($this->isDraftSave) {
            $service->finalizeProperty($property, $formData['property_status'] ?? 'active');
        } else {
            $service->finalizeProperty($property, $formData['property_status'] ?? 'active');
            $service->submitForVerification($property);
        }

        Notification::make()
            ->title($isApproved ? __('admin.changes_saved') : __('admin.property_submitted'))
            ->success()
            ->send();

        $this->redirect(PartnerPropertiesManage::getUrl());
    }

    /**
     * Dial-code dropdown options keyed by "+<code>" with flag emoji labels.
     *
     * @return array<string, string>
     */
    private function getDialCodeOptions(): array
    {
        return RefCountry::query()
            ->where('flag', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (RefCountry $country): array => [
                "+{$country->phonecode}_{$country->id}" => "{$country->emoji} +{$country->phonecode} <span class='dial-country-name'>{$country->name}</span>",
            ])
            ->all();
    }

    private function getDefaultDialCode(): ?string
    {
        $countryId = $this->getCurrentCountryId();
        $phoneCode = $countryId
            ? ltrim((string) (Country::query()->where('id', $countryId)->value('phone_code') ?? ''), '+')
            : '';

        if (! $phoneCode) {
            return null;
        }
        $country = RefCountry::where('phonecode', $phoneCode)->where('flag', true)->orderBy('name')->first();

        return $country ? "+{$country->phonecode}_{$country->id}" : null;
    }

    private function dialCodeToSelectValue(?string $dialCode): ?string
    {
        if (! $dialCode) {
            return null;
        }
        $code = ltrim($dialCode, '+');
        $country = RefCountry::where('phonecode', $code)->where('flag', true)->orderBy('name')->first();

        return $country ? "+{$country->phonecode}_{$country->id}" : null;
    }

    private function getStep1Schema(): array
    {
        $countryId = $this->getCurrentCountryId();

        $refCountry = Country::query()
            ->with('refCountry')
            ->find($countryId)
            ?->refCountry;

        $timezones = $refCountry?->timezones ?? [];

        $timezoneOptions = collect($timezones)
            ->mapWithKeys(fn (array $tz): array => [
                $tz['zoneName'] => '('.$tz['gmtOffsetName'].') '.$tz['zoneName'].' — '.$tz['tzName'],
            ])
            ->toArray();

        $singleTimezone = count($timezones) === 1 ? $timezones[0]['zoneName'] : null;

        return [
            Section::make(__('admin.general_information'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('admin.property_name'))
                        ->placeholder(__('admin.enter_property_name'))
                        ->required()
                        ->maxLength(255),

                    RichEditor::make('description')
                        ->label(__('admin.description'))
                        ->placeholder(__('admin.enter_property_description'))
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('total_floors')
                        ->label(__('admin.total_floors'))
                        ->placeholder('e.g, 4')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(255),
                ]),

            Section::make(__('admin.meta_data'))
                ->schema([
                    TextInput::make('meta_title')
                        ->label(__('admin.meta_title'))
                        ->placeholder(__('admin.enter_meta_title'))
                        ->maxLength(255),

                    Textarea::make('meta_description')
                        ->label(__('admin.meta_description'))
                        ->placeholder(__('admin.enter_meta_description'))
                        ->helperText(__('admin.meta_description_helper'))
                        ->rows(3)
                        ->maxLength(500),

                    Textarea::make('meta_keywords')
                        ->label(__('admin.meta_keywords'))
                        ->placeholder(__('admin.enter_meta_keywords'))
                        ->helperText(__('admin.meta_keywords_helper'))
                        ->rows(2),

                    Textarea::make('schema_markup')
                        ->label(__('admin.schema_markup'))
                        ->placeholder(__('admin.enter_schema_markup'))
                        ->helperText(__('admin.schema_markup_helper'))
                        ->rows(4),
                ]),

            Section::make(__('admin.property_contact_info'))
                ->schema([
                    Grid::make(12)
                        ->schema([
                            Select::make('dial_code')
                                ->label(__('admin.dial_code'))
                                ->options($this->getDialCodeOptions())
                                ->allowHtml()
                                ->searchable()
                                ->selectablePlaceholder(false)
                                ->required()
                                ->default(fn () => $this->getDefaultDialCode())
                                ->dehydrateStateUsing(fn (?string $state): ?string => $state ? explode('_', $state, 2)[0] : null)
                                ->columnSpan(2),

                            TextInput::make('phone')
                                ->label(__('admin.phone_number'))
                                ->placeholder(__('admin.enter_phone_number'))
                                ->tel()
                                ->required()
                                ->maxLength(50)
                                ->mask(RawJs::make('$input.replace(/[^0-9]/g, "").slice(0, 15)'))
                                ->rules(['regex:/^[0-9]{7,15}$/'])
                                ->validationMessages(['regex' => __('admin.validation_phone_format')])
                                ->default(fn () => auth()->user()?->phone)
                                ->columnSpan(4),

                            Select::make('landline_dial_code')
                                ->label(__('admin.dial_code'))
                                ->options($this->getDialCodeOptions())
                                ->allowHtml()
                                ->searchable()
                                ->selectablePlaceholder(false)
                                ->default(fn () => $this->getDefaultDialCode())
                                ->dehydrateStateUsing(fn (?string $state): ?string => $state ? explode('_', $state, 2)[0] : null)
                                ->columnSpan(2),

                            TextInput::make('landline')
                                ->label(__('admin.landline'))
                                ->placeholder(__('admin.enter_landline'))
                                ->tel()
                                ->maxLength(50)
                                ->columnSpan(4),

                            TextInput::make('email')
                                ->label(__('admin.email_address'))
                                ->placeholder(__('admin.enter_email_address'))
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->default(fn () => auth()->user()?->email)
                                ->columnSpan(6),
                        ]),
                ]),

            Section::make(__('admin.property_address'))
                ->schema([
                    View::make($this->getMapPickerView()),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('street_address')
                                ->label(__('admin.street_address'))
                                ->placeholder(__('admin.enter_street_address'))
                                ->required()
                                ->columnSpanFull(),

                            Select::make('ref_state_id')
                                ->label(__('admin.state'))
                                ->options(function () use ($countryId): array {
                                    $refCountryId = Country::query()
                                        ->where('id', $countryId)
                                        ->value('ref_country_id');

                                    if (! $refCountryId) {
                                        return [];
                                    }

                                    return RefState::query()
                                        ->where('country_id', $refCountryId)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required()
                                ->afterStateUpdated(fn (Set $set) => $set('ref_city_id', null)),

                            Select::make('ref_city_id')
                                ->label(__('admin.city'))
                                ->options(function (Get $get): array {
                                    $stateId = $get('ref_state_id');
                                    if (! $stateId) {
                                        return [];
                                    }

                                    return RefCity::query()
                                        ->where('state_id', $stateId)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->required(),

                            TextInput::make('zip_code')
                                ->label(__('admin.zip_code'))
                                ->placeholder(__('admin.enter_zip_code'))
                                ->maxLength(20)
                                ->required(),

                            Grid::make(2)
                                ->schema([
                                    TextInput::make('latitude')
                                        ->label(__('admin.latitude'))
                                        ->placeholder(__('admin.enter_latitude'))
                                        ->numeric()
                                        ->required()
                                        ->live(onBlur: true),

                                    TextInput::make('longitude')
                                        ->label(__('admin.longitude'))
                                        ->placeholder(__('admin.enter_longitude'))
                                        ->numeric()
                                        ->required()
                                        ->live(onBlur: true),
                                ]),

                            Select::make('timezone')
                                ->label(__('admin.property_timezone'))
                                ->options($timezoneOptions)
                                ->default($singleTimezone)
                                ->disabled($singleTimezone !== null)
                                ->dehydrated(true)
                                ->required(fn (): bool => ! empty($timezoneOptions))
                                ->hidden(empty($timezoneOptions))
                                ->searchable()
                                ->helperText($singleTimezone !== null
                                    ? __('admin.timezone_auto_selected')
                                    : __('admin.timezone_select_hint'))
                                ->columnSpanFull(),
                        ]),
                ]),

            Section::make(__('admin.bank_details'))
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('bank_account_holder')
                                ->label(__('admin.account_holder_name'))
                                ->placeholder(__('admin.enter_account_holder_name'))
                                ->required()
                                ->maxLength(255),

                            TextInput::make('bank_name')
                                ->label(__('admin.bank_name'))
                                ->placeholder(__('admin.enter_bank_name'))
                                ->required()
                                ->maxLength(255),

                            TextInput::make('bank_account_number')
                                ->label(__('admin.account_number'))
                                ->placeholder(__('admin.enter_account_number'))
                                ->required()
                                ->maxLength(255),

                            TextInput::make('bank_code')
                                ->label(__('admin.bank_code'))
                                ->placeholder(__('admin.enter_bank_code'))
                                ->helperText(__('admin.bank_code_helper'))
                                ->required()
                                ->maxLength(100),
                        ]),
                ]),
        ];
    }

    // ── Step Form Routing ──────────────────────────────────────────────────

    private function fillFormForCurrentStep(): void
    {
        $property = $this->getProperty();

        if (! $property) {
            if ($this->currentStep === 2) {
                $categories = $this->getFacilityCategories();
                $this->activeCategoryId = $categories->first()?->id;
            }
            $this->form->fill();

            return;
        }

        match ($this->currentStep) {
            1 => $this->form->fill([
                'name' => $property->name,
                'description' => $property->description,
                'meta_title' => $property->meta_title,
                'meta_description' => $property->meta_description,
                'meta_keywords' => $property->meta_keywords,
                'schema_markup' => $property->schema_markup,
                'phone' => $property->phone,
                'dial_code' => $this->dialCodeToSelectValue($property->dial_code),
                'email' => $property->email,
                'landline' => $property->landline,
                'landline_dial_code' => $this->dialCodeToSelectValue($property->landline_dial_code),
                'street_address' => $property->street_address,
                'ref_state_id' => $property->ref_state_id,
                'ref_city_id' => $property->ref_city_id,
                'zip_code' => $property->zip_code,
                'timezone' => $property->timezone,
                'latitude' => $property->latitude,
                'longitude' => $property->longitude,
                'bank_account_holder' => $property->bank_account_holder,
                'bank_name' => $property->bank_name,
                'bank_account_number' => $property->bank_account_number,
                'bank_code' => $property->bank_code,
                'total_floors' => $property->total_floors,
            ]),
            2 => $this->fillStep2($property),
            3 => $this->form->fill(),
            4 => $this->fillStep4($property),
            5 => $this->fillStep5($property),
            6 => $this->fillStep6($property),
            7 => $this->fillStep7($property),
            8 => $this->fillStep8($property),
            default => $this->form->fill(),
        };
    }

    // ── Step Labels & Status ───────────────────────────────────────────────

    public function getStepLabel(int $step): string
    {
        return match ($step) {
            1 => __('admin.wizard_step_basic_details'),
            2 => __('admin.wizard_step_property_facilities'),
            3 => __('admin.wizard_step_rooms_pricing'),
            4 => __('admin.wizard_step_property_rules'),
            5 => __('admin.wizard_step_cancellation_policy'),
            6 => __('admin.wizard_step_payment_configuration'),
            7 => __('admin.wizard_step_property_images'),
            8 => __('admin.wizard_step_legal_compliance'),
            default => '',
        };
    }

    public function getStepStatus(int $step): string
    {
        $property = $this->getProperty();
        $completedStep = $property?->completed_step ?? 0;

        if ($step <= $completedStep) {
            return 'completed';
        }

        if ($step === $this->currentStep) {
            return 'current';
        }

        return 'upcoming';
    }

    public function isPropertyApproved(): bool
    {
        return $this->getProperty()?->verification_status === PropertyVerificationStatus::Approved;
    }

    public function setStateAndCityFromPlace(string $stateName, string $cityName): void
    {
        $countryId = $this->getCurrentCountryId();

        $refCountryId = Country::query()
            ->where('id', $countryId)
            ->value('ref_country_id');

        if (! $refCountryId) {
            return;
        }

        $stateId = RefState::query()
            ->where('country_id', $refCountryId)
            ->where('name', 'LIKE', '%'.$stateName.'%')
            ->value('id');

        if (! $stateId) {
            return;
        }

        $this->data['ref_state_id'] = $stateId;
        $this->data['ref_city_id'] = null;

        if ($cityName !== '') {
            $cityId = RefCity::query()
                ->where('state_id', $stateId)
                ->where('name', 'LIKE', '%'.$cityName.'%')
                ->value('id');

            if ($cityId) {
                $this->data['ref_city_id'] = $cityId;
            }
        }
    }

    public function getGoogleMapsApiKey(): ?string
    {
        return Setting::get('google_maps_api_key');
    }

    public function getMapPickerView(): string
    {
        return MapProvider::isOsm()
            ? 'filament.schemas.components.osm-map-picker'
            : 'filament.schemas.components.google-map-picker';
    }

    /**
     * Lowercase 2-letter ISO code for the partner's currently selected country
     * (e.g. "in", "au", "nl"). Used to restrict Google Places autocomplete.
     */
    public function getCurrentCountryIsoCode(): ?string
    {
        return Country::query()
            ->where('id', $this->getCurrentCountryId())
            ->value('iso_code');
    }

    /**
     * Display name of the partner's currently selected country (for UI notes).
     */
    public function getCurrentCountryName(): ?string
    {
        return Country::query()
            ->where('id', $this->getCurrentCountryId())
            ->value('name');
    }

    private function getPhpMaxUploadSizeInKb(): int
    {
        $parseSize = static function (string $size): int {
            $unit = strtoupper(substr(trim($size), -1));
            $value = (int) $size;

            return match ($unit) {
                'G' => $value * 1024 * 1024,
                'M' => $value * 1024,
                default => (int) ceil($value / 1024),
            };
        };

        $uploadMax = $parseSize(ini_get('upload_max_filesize'));
        $postMax = $parseSize(ini_get('post_max_size'));

        $livewireRules = config('livewire.temporary_file_upload.rules', []);
        $livewireMax = PHP_INT_MAX;
        foreach ($livewireRules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'max:')) {
                $livewireMax = (int) substr($rule, 4);
                break;
            }
        }

        return min($uploadMax, $postMax, $livewireMax);
    }

    private function formatFileSize(int $kb): string
    {
        if ($kb >= 1024 * 1024) {
            return round($kb / (1024 * 1024), 1).'GB';
        }

        return round($kb / 1024, 1).'MB';
    }
}
