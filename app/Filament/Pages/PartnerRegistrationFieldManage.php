<?php

namespace App\Filament\Pages;

use App\Enums\RegistrationFieldScope;
use App\Enums\RegistrationFieldType;
use App\Enums\Status;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\RegistrationField;
use App\Models\User;
use App\Services\RegistrationFieldService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class PartnerRegistrationFieldManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithTable;

    protected static ?string $slug = 'partner-registration-fields';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.registration-field-manage';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Partners);
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.partner_registration_fields');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.registration_fields');
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.partner_registration_fields');
    }

    public function getSubheading(): ?string
    {
        /** @var User $user */
        $user = auth()->user();
        $countryName = $user->currentCountry?->name ?? '';

        return __('admin.partner_registration_fields_subheading', ['country' => $countryName]);
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateAction(),
        ];
    }

    public function hasRegistrationFields(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return RegistrationField::query()
            ->forCountry($user->current_country_id)
            ->forScope(RegistrationFieldScope::Partner)
            ->exists();
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->query(
                RegistrationField::query()
                    ->forCountry($user->current_country_id)
                    ->forScope(RegistrationFieldScope::Partner)
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.registration_fields'))
                    ->searchable()
                    ->limit(40)
                    ->wrap(),
                TextColumn::make('field_type')
                    ->label(__('admin.input_type'))
                    ->badge()
                    ->formatStateUsing(fn (RegistrationFieldType $state): string => $state->label())
                    ->color('info'),
                TextColumn::make('is_mandatory')
                    ->label(__('admin.validation'))
                    ->formatStateUsing(fn (bool $state): string => $state ? __('admin.mandatory') : __('admin.optional')),
                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (Status $state): string => $state->label())
                    ->color(fn (Status $state): string => match ($state) {
                        Status::Active => 'success',
                        Status::Inactive => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('edit')->label(__('admin.edit'))
                    ->icon('phosphor-pencil-simple-line')
                    ->iconButton()
                    ->color('gray')
                    ->disabled(static::disabledUnlessCanEdit())
                    ->tooltip(__('admin.edit'))
                    ->modalHeading(__('admin.edit_registration_field'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('md')
                    ->modalSubmitActionLabel(__('admin.save_field'))
                    ->modalFooterActionsAlignment(Alignment::End)

                    ->mountUsing(function (Schema $form, RegistrationField $record): void {
                        $form->fill([
                            'name' => $record->name,
                            'field_type' => $record->field_type->value,
                            'is_mandatory' => $record->is_mandatory ? 'yes' : 'no',
                            'status' => $record->status->value,
                            'min_number' => $record->min_number,
                            'max_number' => $record->max_number,
                            'max_length' => $record->max_length,
                            'max_file_size' => $record->max_file_size,
                            'options' => $record->options ?? [],
                        ]);
                    })
                    ->schema($this->getFieldFormSchema())
                    ->action(function (RegistrationField $record, array $data): void {
                        $data['is_mandatory'] = $data['is_mandatory'] === 'yes';

                        app(RegistrationFieldService::class)->updateField($record, $data);

                        Notification::make()
                            ->title(__('admin.registration_field_updated'))
                            ->success()
                            ->send();
                    }),
                Action::make('delete')->label(__('admin.delete'))
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->icon('phosphor-trash')
                    ->iconButton()
                    ->color('gray')
                    ->before(static::enforceDeletePermission())
                    ->tooltip(__('admin.delete'))
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_registration_field'))
                    ->modalDescription(__('admin.delete_registration_field_warning'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->modalFooterActionsAlignment(Alignment::Center)

                    ->action(function (RegistrationField $record): void {
                        app(RegistrationFieldService::class)->deleteField($record);

                        Notification::make()
                            ->title(__('admin.registration_field_deleted'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('partner-registration-fields')
                    ->exports([
                        'name' => 'Field Name',
                        'field_type' => ['label' => 'Input Type', 'formatter' => fn (RegistrationField $record): string => $record->field_type->label()],
                        'is_mandatory' => ['label' => 'Validation', 'formatter' => fn (RegistrationField $record): string => $record->is_mandatory ? 'Mandatory' : 'Optional'],
                        'status' => ['label' => 'Status', 'formatter' => fn (RegistrationField $record): string => $record->status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_registration_fields_added'))
            ->emptyStateDescription(__('admin.no_partner_registration_fields_description'))
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->defaultPaginationPageOption(10);
    }

    private function getCreateAction(): Action
    {
        return Action::make('addField')
            ->label(__('admin.add_new_field'))
            ->modalHeading(__('admin.add_new_registration_field'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('md')
            ->modalSubmitActionLabel(__('admin.create_field'))
            ->modalFooterActionsAlignment(Alignment::End)

            ->disabled(static::disabledUnlessCanCreate())
            ->schema($this->getFieldFormSchema())
            ->action(function (array $data): void {
                /** @var User $user */
                $user = auth()->user();

                $data['is_mandatory'] = $data['is_mandatory'] === 'yes';
                $data['scope'] = RegistrationFieldScope::Partner->value;
                $data['property_type_id'] = null;

                app(RegistrationFieldService::class)->createField($data, $user->current_country_id);

                Notification::make()
                    ->title(__('admin.registration_field_created'))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, Component|\Filament\Schemas\Components\Component>
     */
    private function getFieldFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label(__('admin.field_name'))
                ->placeholder(__('admin.enter_field_name'))
                ->maxLength(255)
                ->required(),
            Select::make('field_type')
                ->label(__('admin.field_type'))
                ->placeholder(__('admin.select_field_type'))
                ->options(collect(RegistrationFieldType::cases())->mapWithKeys(
                    fn (RegistrationFieldType $type): array => [$type->value => $type->label()]
                ))
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('options', [])),
            Grid::make(2)
                ->schema([
                    TextInput::make('min_number')
                        ->label(__('admin.min_number'))
                        ->helperText(__('admin.set_min_number'))
                        ->integer()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('max_number')
                        ->label(__('admin.max_number'))
                        ->helperText(__('admin.set_max_number'))
                        ->integer()
                        ->minValue(1)
                        ->gte('min_number')
                        ->required(),
                ])
                ->visible(fn (Get $get): bool => $get('field_type') === RegistrationFieldType::NumberInput->value),
            TextInput::make('max_length')
                ->label(__('admin.max_length'))
                ->helperText(__('admin.set_max_length'))
                ->integer()
                ->minValue(1)
                ->required()
                ->visible(fn (Get $get): bool => in_array($get('field_type'), [
                    RegistrationFieldType::TextField->value,
                    RegistrationFieldType::TextArea->value,
                ])),
            TagsInput::make('options')
                ->label(__('admin.dropdown_options'))
                ->placeholder(__('admin.options_placeholder'))
                ->reorderable()
                ->required(fn (Get $get): bool => in_array($get('field_type'), [
                    RegistrationFieldType::Dropdown->value,
                    RegistrationFieldType::Checkboxes->value,
                ]))
                ->visible(fn (Get $get): bool => in_array($get('field_type'), [
                    RegistrationFieldType::Dropdown->value,
                    RegistrationFieldType::Checkboxes->value,
                ])),
            TextInput::make('max_file_size')
                ->label(__('admin.max_file_size'))
                ->placeholder(__('admin.enter_file_size'))
                ->helperText(__('admin.file_size_helper'))
                ->integer()
                ->minValue(1)
                ->required()
                ->visible(fn (Get $get): bool => $get('field_type') === RegistrationFieldType::FileUpload->value),
            Radio::make('is_mandatory')
                ->label(__('admin.is_mandatory'))
                ->options([
                    'yes' => __('admin.yes'),
                    'no' => __('admin.no'),
                ])
                ->default('yes')
                ->required()
                ->inline(),
            Radio::make('status')
                ->label(__('admin.status'))
                ->options([
                    'active' => __('admin.active'),
                    'inactive' => __('admin.inactive'),
                ])
                ->default('active')
                ->required()
                ->inline(),
        ];
    }
}
