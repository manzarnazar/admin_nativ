<?php

namespace App\Filament\Pages;

use App\Enums\TaxStatus;
use App\Enums\TaxType;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasAdminDemoGuard;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Tax;
use App\Models\User;
use App\Services\TaxService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class TaxManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasAdminDemoGuard;
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'taxes';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.tax_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.tax_manage');
    }

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.tax-manage';

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::LocationPolicies);
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.tax_management');
    }

    public function getSubheading(): ?string
    {
        return __('admin.configure_taxes_based_on_applicable_regulations');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addTax')
                ->label(__('admin.add_new_tax'))
                ->disabled(static::disabledUnlessCanCreate())
                ->modalHeading(__('admin.add_new_tax'))
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->modalWidth('md')
                ->modalSubmitActionLabel(__('admin.save_tax'))
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema($this->getTaxFormSchema())
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();

                    app(TaxService::class)->createTax($data, $user->current_country_id);

                    Notification::make()
                        ->title(__('admin.tax_created_successfully'))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->query(
                Tax::query()->forCountry($user->current_country_id)->with('propertyTypes')
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.tax_name'))
                    ->description(fn (Tax $record): string => 'ID - '.str_pad((string) $record->id, 2, '0', STR_PAD_LEFT))
                    ->searchable(),
                TextColumn::make('description')
                    ->label(__('admin.description'))
                    ->limit(40)
                    ->tooltip(fn (Tax $record): string => $record->description ?? ''),
                TextColumn::make('type')
                    ->label(__('admin.type'))
                    ->badge()
                    ->formatStateUsing(fn (TaxType $state): string => $state->label())
                    ->color(fn (TaxType $state): string => match ($state) {
                        TaxType::Percentage => 'info',
                        TaxType::Fixed => 'warning',
                    }),
                TextColumn::make('value')
                    ->label(__('admin.value'))
                    ->formatStateUsing(function (Tax $record): string {
                        if ($record->type === TaxType::Percentage) {
                            return rtrim(rtrim(number_format((float) $record->value, 4), '0'), '.').'%';
                        }

                        $symbol = $record->country?->currency_symbol ?? '$';

                        return $symbol.rtrim(rtrim(number_format((float) $record->value, 4), '0'), '.');
                    }),
                TextColumn::make('property_types_list')
                    ->label(__('admin.property_type'))
                    ->state(function (Tax $record): string {
                        $names = $record->propertyTypes->pluck('name');
                        if ($names->isEmpty()) {
                            return '—';
                        }
                        if ($names->count() <= 2) {
                            return $names->join(', ');
                        }

                        return $names->take(2)->join(', ').', +'.($names->count() - 2).' more';
                    })
                    ->tooltip(fn (Tax $record): ?string => $record->propertyTypes->count() > 2
                        ? $record->propertyTypes->pluck('name')->join(', ')
                        : null
                    )
                    ->visible(fn (): bool => SystemMode::isMulti()),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (TaxStatus $state): string => $state->label())
                    ->color(fn (TaxStatus $state): string => match ($state) {
                        TaxStatus::Active => 'success',
                        TaxStatus::Inactive => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        'active' => __('admin.active'),
                        'inactive' => __('admin.inactive'),
                    ]),
            ])
            ->recordActions([
                Action::make('edit')
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->disabled(static::disabledUnlessCanEdit())
                    ->before($this->enforceRestrictedActionGuard())
                    ->modalHeading(__('admin.edit_tax'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('md')
                    ->modalSubmitActionLabel(__('admin.save_tax'))
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (Tax $record): array => [
                        'name' => $record->name,
                        'description' => $record->description,
                        'type' => $record->type->value,
                        'value' => $record->value,
                        'status' => $record->status->value,
                    ])
                    ->schema($this->getTaxFormSchema())
                    ->action(function (Tax $record, array $data): void {
                        app(TaxService::class)->updateTax($record, $data);

                        Notification::make()
                            ->title(__('admin.tax_updated_successfully'))
                            ->success()
                            ->send();
                    }),
                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->before(static::enforceDeletePermission())
                    ->disabled(function (): bool {
                        /** @var User $user */
                        $user = auth()->user();

                        return Tax::query()->forCountry($user->current_country_id)->count() <= 1;
                    })
                    ->tooltip(function (): ?string {
                        /** @var User $user */
                        $user = auth()->user();

                        if (Tax::query()->forCountry($user->current_country_id)->count() <= 1) {
                            return __('admin.at_least_one_tax_is_required');
                        }

                        return null;
                    })
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_tax'))
                    ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_tax_will_no_longer_be_applied_to_future_bookings'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
                    ->action(function (Tax $record): void {
                        app(TaxService::class)->deleteTax($record);

                        Notification::make()
                            ->title(__('admin.tax_deleted_successfully'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('taxes')
                    ->exports([
                        'name' => 'Tax Name',
                        'description' => 'Description',
                        'type' => ['label' => 'Type', 'formatter' => fn (Tax $record): string => $record->type->label()],
                        'value' => ['label' => 'Value', 'formatter' => function (Tax $record): string {
                            if ($record->type === TaxType::Percentage) {
                                return rtrim(rtrim(number_format((float) $record->value, 4), '0'), '.').'%';
                            }

                            $symbol = $record->country?->currency_symbol ?? '$';

                            return $symbol.rtrim(rtrim(number_format((float) $record->value, 4), '0'), '.');
                        }],
                        'status' => ['label' => 'Status', 'formatter' => fn (Tax $record): string => $record->status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->defaultPaginationPageOption(4);
    }

    /**
     * @return array<int, Component>
     */
    private function getTaxFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label(__('admin.tax_name'))
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label(__('admin.description'))
                ->required()
                ->maxLength(1000),
            Select::make('type')
                ->label(__('admin.calculation_type'))
                ->options([
                    'percentage' => 'Percentage',
                    'fixed' => 'Fixed',
                ])
                ->required()
                ->live(),
            TextInput::make('value')
                ->label(__('admin.rate_value'))
                ->required()
                ->numeric()
                ->minValue(0)
                ->maxValue(fn (Get $get): ?float => $get('type') === 'percentage' ? 100 : null)
                ->suffix(function (Get $get): ?string {
                    $type = $get('type');

                    if ($type === 'percentage') {
                        return '%';
                    }

                    if ($type === 'fixed') {
                        /** @var User $user */
                        $user = auth()->user();

                        return $user->currentCountry?->currency_symbol ?? '$';
                    }

                    return null;
                }),
            Radio::make('status')
                ->label(__('admin.status'))
                ->options([
                    'active' => __('admin.active'),
                    'inactive' => __('admin.inactive'),
                ])
                ->default('active')
                ->required(),
        ];
    }
}
