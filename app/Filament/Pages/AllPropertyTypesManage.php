<?php

namespace App\Filament\Pages;

use App\Enums\TaxType;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\CommissionRate;
use App\Models\PropertyType;
use App\Models\Tax;
use App\Models\User;
use App\Services\PropertyTypeService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AllPropertyTypesManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithTable;

    protected static ?string $slug = 'property-types';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.all-property-types-manage';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.property_type');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::PropertyManagement);
    }

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.property_type');
    }

    public function getSubheading(): ?string
    {
        return __('admin.property_type_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    private function countryId(): int
    {
        /** @var User $user */
        $user = Auth::user();

        return (int) $user->current_country_id;
    }

    private function getDefaultCommissionRate(): float
    {
        $rate = CommissionRate::query()
            ->where('country_id', $this->countryId())
            ->whereNull('property_type_id')
            ->value('rate');

        return $rate !== null ? (float) $rate : 0.0;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createPropertyTypeAction(),
        ];
    }

    public function table(Table $table): Table
    {
        $countryId = $this->countryId();
        $service = app(PropertyTypeService::class);

        return $table
            ->query(
                PropertyType::query()
                    ->whereHas('countries', fn (Builder $q) => $q->where('countries.id', $countryId))
                    ->with('countries')
                    ->withCount(['properties as active_properties_count' => fn (Builder $q) => $q->where('country_id', $countryId)])
                    ->orderBy('name')
            )
            ->columns([
                TextColumn::make('type_info')
                    ->label(__('admin.property_type_info'))
                    ->html()
                    ->searchable(['name', 'id'])
                    ->state(function (PropertyType $record): string {
                        $iconUrl = $record->icon_url;
                        $iconHtml = $iconUrl
                            ? '<img src="'.e($iconUrl).'" class="h-9 w-9 flex-shrink-0 rounded-lg object-contain bg-gray-100 dark:bg-gray-800 p-1.5" alt="">'
                            : '<div class="h-9 w-9 flex-shrink-0 rounded-lg bg-gray-100 dark:bg-gray-800"></div>';

                        return '<div class="flex items-center gap-3">
                            '.$iconHtml.'
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">'.e($record->name).'</p>
                                <p class="text-xs text-primary-600 dark:text-primary-400">ID: '.$record->id.'</p>
                            </div>
                        </div>';
                    }),

                TextColumn::make('description')
                    ->label(__('admin.description'))
                    ->limit(60)
                    ->wrap(),

                TextColumn::make('active_properties_count')
                    ->label(__('admin.active_properties'))
                    ->alignCenter(),

                TextColumn::make('tax')
                    ->label(__('admin.tax'))
                    ->html()
                    ->state(function (PropertyType $record) use ($countryId): string {
                        $taxes = $record->taxes()->where('country_id', $countryId)->get();

                        if ($taxes->isEmpty()) {
                            return '<span class="text-gray-400">—</span>';
                        }

                        return $taxes->map(function (Tax $tax): string {
                            $value = $tax->type === TaxType::Percentage
                                ? rtrim(rtrim(number_format((float) $tax->value, 2), '0'), '.').'%'
                                : number_format((float) $tax->value, 2);

                            return '<span class="text-sm text-gray-700 dark:text-gray-300">'.e($tax->name).'('.$value.')</span>';
                        })->implode('<span class="text-gray-400">, </span>');
                    }),

                TextColumn::make('commission')
                    ->label(__('admin.commission'))
                    ->html()
                    ->state(function (PropertyType $record) use ($countryId): string {
                        $overrideRate = CommissionRate::query()
                            ->where('country_id', $countryId)
                            ->where('property_type_id', $record->id)
                            ->value('rate');

                        $isOverridden = $overrideRate !== null;
                        $rate = $isOverridden ? (float) $overrideRate : $this->getDefaultCommissionRate();
                        $badge = $isOverridden ? __('admin.overridden') : __('admin.default');

                        return '<div>
                            <p class="font-semibold text-gray-900 dark:text-white">'.rtrim(rtrim(number_format($rate, 2), '0'), '.').'%</p>
                            <p class="text-xs text-gray-400">'.e($badge).'</p>
                        </div>';
                    }),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->state(fn (PropertyType $record): string => $service->isEnabledForCountry($record, $countryId)
                        ? __('admin.active')
                        : __('admin.inactive'))
                    ->color(fn (PropertyType $record): string => $service->isEnabledForCountry($record, $countryId)
                        ? 'success'
                        : 'danger'),
            ])
            ->recordActions([
                $this->editPropertyTypeAction(),
                $this->deletePropertyTypeAction(),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('property-types')
                    ->exports([
                        'name' => __('admin.property_type_name'),
                        'description' => __('admin.description'),
                        'active_properties_count' => __('admin.active_properties'),
                    ])
                    ->toActionGroup(),
            ])
            ->searchPlaceholder(__('admin.search_property_types'))
            ->emptyStateHeading(__('admin.no_property_types'))
            ->emptyStateDescription(__('admin.no_property_types_description'));
    }

    private function taxSectionSchema(): Section
    {
        $countryId = $this->countryId();

        return Section::make(__('admin.configure_tax'))
            ->afterHeader([
                Action::make('addNewTaxInline')
                    ->label(__('admin.add_new_tax'))
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->link()
                    ->size('sm')
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalHeading(__('admin.add_new_tax'))
                    ->modalSubmitActionLabel(__('admin.add_tax'))
                    ->modalWidth('lg')
                    ->schema([
                        TextInput::make('new_tax_name')
                            ->label(__('admin.tax_name'))
                            ->placeholder(__('admin.enter_tax_name'))
                            ->required()
                            ->maxLength(255),

                        Textarea::make('new_tax_description')
                            ->label(__('admin.description'))
                            ->placeholder(__('admin.tax_description_placeholder'))
                            ->rows(2),

                        Grid::make(2)
                            ->schema([
                                Select::make('new_tax_type')
                                    ->label(__('admin.calculation_type'))
                                    ->options(collect(TaxType::cases())->mapWithKeys(
                                        fn (TaxType $type) => [$type->value => $type->label()]
                                    ))
                                    ->default(TaxType::Percentage->value)
                                    ->required()
                                    ->live(),

                                TextInput::make('new_tax_value')
                                    ->label(__('admin.rate_value'))
                                    ->placeholder(fn (Get $get): string => $get('new_tax_type') === TaxType::Percentage->value ? 'e.g. 18%' : 'e.g. 12')
                                    ->numeric()
                                    ->required()
                                    ->suffix(fn (Get $get): string => $get('new_tax_type') === TaxType::Percentage->value ? '%' : ''),
                            ]),
                    ])
                    ->action(function (array $data, Set $set, Get $get) use ($countryId): void {
                        $tax = Tax::query()->create([
                            'country_id' => $countryId,
                            'name' => $data['new_tax_name'],
                            'description' => $data['new_tax_description'],
                            'type' => $data['new_tax_type'],
                            'value' => $data['new_tax_value'],
                            'status' => 'active',
                        ]);

                        $currentTaxIds = $get('tax_ids') ?? [];
                        $currentTaxIds[] = $tax->id;
                        $set('tax_ids', $currentTaxIds);

                        Notification::make()
                            ->title(__('admin.tax_created_successfully'))
                            ->success()
                            ->send();
                    }),
            ])
            ->schema([
                Select::make('tax_ids')
                    ->label(__('admin.tax'))
                    ->multiple()
                    ->options(fn (): array => Tax::query()
                        ->where('country_id', $countryId)
                        ->where('status', 'active')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable()
                    ->preload(),
            ]);
    }

    public function createPropertyTypeAction(): Action
    {
        return Action::make('createPropertyType')
            ->label(__('admin.add_property_type'))
            ->icon('heroicon-o-plus')
            ->modalHeading(__('admin.add_new_property_type'))
            ->modalSubmitActionLabel(__('admin.create_property_type'))
            ->modalWidth('lg')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema([
                FileUpload::make('icon')
                    ->label(__('admin.property_type_icon'))
                    ->image()
                    ->disk('public')
                    ->directory('property-types')
                    ->acceptedFileTypes(['image/png', 'image/svg+xml'])
                    ->maxSize(5120)
                    ->helperText(__('admin.maximum_size_5mb_supported_files_pngsvg'))
                    ->required(),

                TextInput::make('name')
                    ->label(__('admin.property_type_name'))
                    ->placeholder(__('admin.enter_property_type_name'))
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label(__('admin.description'))
                    ->placeholder(__('admin.property_type_description_placeholder'))
                    ->required()
                    ->rows(4),

                Grid::make(2)
                    ->schema([
                        Radio::make('commission_mode')
                            ->label(__('admin.commission'))
                            ->options(fn (): array => [
                                'default' => __('admin.default_commission_pct', ['rate' => rtrim(rtrim(number_format($this->getDefaultCommissionRate(), 2), '0'), '.')]),
                                'override' => __('admin.override_commission'),
                            ])
                            ->default('default')
                            ->inline()
                            ->live()
                            ->required(),

                        TextInput::make('commission_rate')
                            ->label(__('admin.commission_percentage'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->rules(['numeric', 'between:0,100'])
                            ->visible(fn (Get $get): bool => $get('commission_mode') === 'override')
                            ->required(fn (Get $get): bool => $get('commission_mode') === 'override'),
                    ]),

                $this->taxSectionSchema(),

                Radio::make('is_active')
                    ->label(__('admin.status'))
                    ->boolean(
                        trueLabel: __('admin.active'),
                        falseLabel: __('admin.inactive'),
                    )
                    ->default(true)
                    ->required()
                    ->inline(),
            ])
            ->action(function (array $data): void {
                app(PropertyTypeService::class)->createPropertyType([
                    'icon' => $data['icon'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'tax_ids' => $data['tax_ids'] ?? [],
                    'is_active' => (bool) $data['is_active'],
                    'commission_mode' => $data['commission_mode'],
                    'commission_rate' => $data['commission_rate'] ?? null,
                ], $this->countryId());

                Notification::make()
                    ->title(__('admin.property_type_created'))
                    ->success()
                    ->send();
            });
    }

    public function editPropertyTypeAction(): Action
    {
        $countryId = $this->countryId();

        return Action::make('edit')
            ->iconButton()
            ->icon('phosphor-pencil-simple-line')
            ->color('gray')
            ->modalHeading(__('admin.edit_property_type'))
            ->modalSubmitActionLabel(__('admin.save_changes'))
            ->modalWidth('lg')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalFooterActionsAlignment(Alignment::End)
            ->fillForm(function (PropertyType $record) use ($countryId): array {
                $service = app(PropertyTypeService::class);
                $service->migrateSeededIcon($record);

                $linkedTaxIds = $record->taxes()->where('country_id', $countryId)->pluck('taxes.id')->toArray();

                return [
                    'icon' => $record->icon,
                    'name' => $record->name,
                    'description' => $record->description,
                    'tax_ids' => $linkedTaxIds,
                    'is_active' => app(PropertyTypeService::class)->isEnabledForCountry($record, $countryId),
                ];
            })
            ->schema([
                Callout::make(__('admin.edit_property_type_warning_title'))
                    ->description(__('admin.edit_property_type_warning_desc'))
                    ->danger(),

                FileUpload::make('icon')
                    ->label(__('admin.property_type_icon'))
                    ->image()
                    ->disk('public')
                    ->directory('property-types')
                    ->acceptedFileTypes(['image/png', 'image/svg+xml'])
                    ->maxSize(5120)
                    ->helperText(__('admin.maximum_size_5mb_supported_files_pngsvg'))
                    ->required(),

                TextInput::make('name')
                    ->label(__('admin.property_type_name'))
                    ->placeholder(__('admin.enter_property_type_name'))
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label(__('admin.description'))
                    ->placeholder(__('admin.property_type_description_placeholder'))
                    ->required()
                    ->rows(4),

                $this->taxSectionSchema(),

                Radio::make('is_active')
                    ->label(__('admin.status'))
                    ->boolean(
                        trueLabel: __('admin.active'),
                        falseLabel: __('admin.inactive'),
                    )
                    ->required()
                    ->inline(),
            ])
            ->action(function (PropertyType $record, array $data) use ($countryId): void {
                $service = app(PropertyTypeService::class);

                $service->updatePropertyType($record, [
                    'icon' => $data['icon'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                ]);

                $service->syncTaxes($record, $data['tax_ids'] ?? [], $countryId);
                $service->toggleCountryStatus($record, $countryId, (bool) $data['is_active']);

                Notification::make()
                    ->title(__('admin.property_type_updated'))
                    ->success()
                    ->send();
            });
    }

    public function deletePropertyTypeAction(): Action
    {
        return Action::make('delete')
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('admin.delete_property_type'))
            ->modalDescription(__('admin.delete_property_type_description'))
            ->modalAlignment(Alignment::Center)
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->action(function (PropertyType $record): void {
                $deleted = app(PropertyTypeService::class)->deletePropertyType($record, $this->countryId());

                if (! $deleted) {
                    Notification::make()
                        ->title(__('admin.cannot_delete_property_type_in_use'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('admin.property_type_deleted'))
                    ->success()
                    ->send();
            });
    }
}
