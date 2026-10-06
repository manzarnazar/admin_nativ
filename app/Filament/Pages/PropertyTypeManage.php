<?php

namespace App\Filament\Pages;

use App\Enums\SetupTask;
use App\Enums\TaxType;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\CountrySetupTask;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Tax;
use App\Models\User;
use App\Services\PropertyTypeService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class PropertyTypeManage extends Page implements DeclaresTopbarControls
{
    use HasPagePermission;

    protected static ?string $slug = 'property-type';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public static function canAccess(): bool
    {
        return SystemMode::isSingle() && auth()->check();
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.property_type');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.property_type');
    }

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.property-type-manage';

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::PropertyManagement);
    }

    public function mount(): void
    {
        CountrySetupTask::markComplete(SetupTask::PropertyTypeSetup);
    }

    public function getSubheading(): ?string
    {
        return __('admin.property_type_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getPropertyType(): ?PropertyType
    {
        return PropertyType::query()
            ->where('is_active', true)
            ->first();
    }

    public function getPropertyTypeTaxes(): Collection
    {
        $propertyType = $this->getPropertyType();
        $user = auth()->user();

        if (! $propertyType || ! $user->current_country_id) {
            return collect();
        }

        return $propertyType->taxes()
            ->where('country_id', $user->current_country_id)
            ->get();
    }

    public function getPropertyCount(): int
    {
        $propertyType = $this->getPropertyType();

        if (! $propertyType) {
            return 0;
        }

        /** @var User $user */
        $user = auth()->user();

        return Property::query()
            ->where('property_type_id', $propertyType->id)
            ->where('country_id', $user->current_country_id)
            ->count();
    }

    public function getTaxBadgeLabel(Tax $tax): string
    {
        if ($tax->type === TaxType::Percentage) {
            return $tax->name.'-'.rtrim(rtrim(number_format($tax->value, 2), '0'), '.').'%';
        }

        $user = auth()->user();
        $currencySymbol = $user->currentCountry?->currency_symbol ?? '$';

        return $tax->name.'-'.$currencySymbol.rtrim(rtrim(number_format($tax->value, 2), '0'), '.');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function editPropertyTypeAction(): Action
    {
        return Action::make('editPropertyType')
            ->label(__('admin.edit'))
            ->icon('phosphor-pencil-simple-line')
            ->color('gray')
            ->disabled(static::disabledUnlessCanEdit())
            ->outlined()
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading(__('admin.edit_property_type'))
            ->modalSubmitActionLabel(__('admin.save_property_type'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->fillForm(function (): array {
                $propertyType = $this->getPropertyType();
                $user = auth()->user();

                app(PropertyTypeService::class)->migrateSeededIcon($propertyType);

                $linkedTaxIds = $propertyType->taxes()->where('country_id', $user->current_country_id)->pluck('taxes.id')->toArray();

                return [
                    'icon' => $propertyType->icon,
                    'name' => $propertyType->name,
                    'description' => $propertyType->description,
                    'tax_ids' => $linkedTaxIds,
                ];
            })
            ->modalWidth('md')
            ->schema($this->getEditFormSchema())
            ->action(function (array $data): void {
                $propertyType = $this->getPropertyType();
                $user = auth()->user();
                $service = app(PropertyTypeService::class);

                $service->updatePropertyType($propertyType, [
                    'icon' => $data['icon'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                ]);

                $service->syncTaxes($propertyType, $data['tax_ids'] ?? [], $user->current_country_id);

                Notification::make()
                    ->title(__('admin.property_type_updated'))
                    ->success()
                    ->send();
            });
    }

    private function getEditFormSchema(): array
    {
        $user = auth()->user();

        return [
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

            Section::make(__('admin.configure_tax'))
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
                                        ->minValue(0)
                                        ->maxValue(fn (Get $get) => $get('new_tax_type') === TaxType::Percentage->value ? 100 : null)
                                        ->required()
                                        ->suffix(fn (Get $get): string => $get('new_tax_type') === TaxType::Percentage->value ? '%' : ''),
                                ]),
                        ])
                        ->action(function (array $data, Set $set, Get $get): void {
                            $propertyType = $this->getPropertyType();
                            $user = auth()->user();

                            $tax = app(PropertyTypeService::class)->createAndAssociateTax(
                                $propertyType,
                                [
                                    'name' => $data['new_tax_name'],
                                    'description' => $data['new_tax_description'],
                                    'type' => $data['new_tax_type'],
                                    'value' => $data['new_tax_value'],
                                    'status' => 'active',
                                ],
                                $user->current_country_id,
                            );

                            // Add newly created tax to the selected list
                            $currentTaxIds = $get('tax_ids') ?? [];
                            $currentTaxIds[] = $tax->id;
                            $set('tax_ids', $currentTaxIds);

                            Notification::make()
                                ->title(__('admin.tax_created_and_linked'))
                                ->success()
                                ->send();
                        }),
                ])
                ->schema([
                    Select::make('tax_ids')
                        ->label(__('admin.tax'))
                        ->multiple()
                        ->options(function () use ($user): array {
                            return Tax::query()
                                ->where('country_id', $user->current_country_id)
                                ->where('status', 'active')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->preload(),
                ]),
        ];
    }
}
