<?php

namespace App\Filament\Pages;

use App\Enums\SeoPageType;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\SeoPage;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class SeoManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'seo-settings';

    protected static string $permissionSlug = 'general-settings';

    protected string $view = 'filament.pages.seo-manage';

    protected static ?int $navigationSort = 11;

    protected static bool $shouldRegisterNavigation = true;

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.seo_settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.seo_settings');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Settings);
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.seo_settings');
    }

    public function getSubheading(): ?string
    {
        return __('admin.seo_settings_description');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getAddAction(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SeoPage::query())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('page_type')
                    ->label(__('admin.page_type'))
                    ->badge()
                    ->formatStateUsing(fn (SeoPageType $state): string => $state->getLabel())
                    ->searchable()
                    ->sortable(),
                ImageColumn::make('og_image')
                    ->label(__('admin.og_image'))
                    ->disk('public')
                    ->size(40),
                TextColumn::make('meta_title')
                    ->label(__('admin.meta_title'))
                    ->limit(50)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('admin.created_at'))
                    ->dateTime('j M Y')
                    ->sortable(),
            ])
            ->recordActions([
                $this->getEditAction(),
                $this->getDeleteAction(),
            ])
            ->emptyStateHeading(__('admin.no_seo_configurations'))
            ->emptyStateDescription(__('admin.no_seo_configurations_description'))
            ->emptyStateIcon('heroicon-o-magnifying-glass')
            ->defaultPaginationPageOption(10);
    }

    /**
     * Rooms/Gallery have no dedicated frontend page in multi-mode; List
     * Property is the multi-mode partner-signup landing page and doesn't
     * exist in single-mode. This only narrows what's offered when creating
     * a NEW config — getEditAction() below deliberately keeps the full,
     * unfiltered case list so an existing config (created before a mode
     * switch, or before this filter existed) still renders its current
     * page_type label correctly in its disabled Select.
     *
     * @return array<int, SeoPageType>
     */
    private function availablePageTypesForCreate(): array
    {
        return collect(SeoPageType::cases())
            ->reject(fn (SeoPageType $type): bool => in_array($type, [SeoPageType::Rooms, SeoPageType::Gallery], true) && SystemMode::isMulti())
            ->reject(fn (SeoPageType $type): bool => $type === SeoPageType::ListProperty && SystemMode::isSingle())
            ->values()
            ->all();
    }

    private function getAddAction(): Action
    {
        return Action::make('addSeoConfig')
            ->label(__('admin.add_seo_configuration'))
            ->disabled(static::disabledUnlessCanCreate())
            ->modalHeading(__('admin.add_seo_configuration'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('2xl')
            ->modalSubmitActionLabel(__('admin.save'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema([
                Select::make('page_type')
                    ->label(__('admin.page_type'))
                    ->options(collect($this->availablePageTypesForCreate())->mapWithKeys(fn ($type) => [$type->value => $type->getLabel()]))
                    ->required()
                    ->unique('seo_pages', 'page_type'),
                FileUpload::make('og_image')
                    ->label(__('admin.og_image'))
                    ->disk('public')
                    ->directory('seo')
                    ->image()
                    ->maxSize(1024)
                    ->helperText(__('admin.maximum_size_1mb'))
                    ->required(),
                TextInput::make('meta_title')
                    ->label(__('admin.meta_title'))
                    ->maxLength(255)
                    ->required(),
                Textarea::make('meta_description')
                    ->label(__('admin.meta_description'))
                    ->rows(3)
                    ->required(),
                Textarea::make('meta_keyword')
                    ->label(__('admin.meta_keywords'))
                    ->placeholder(__('admin.eg_hotel_travel_luxury'))
                    ->rows(2)
                    ->required(),
                Textarea::make('schema_markup')
                    ->label(__('admin.schema_markup'))
                    ->placeholder(__('admin.enter_schema_markup'))
                    ->helperText(new HtmlString(__('admin.schema_markup_helper')))
                    ->rows(5),
            ])
            ->action(function (array $data): void {
                SeoPage::create($data);

                Notification::make()
                    ->title(__('admin.seo_configuration_saved'))
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
            ->disabled(static::disabledUnlessCanEdit())
            ->modalHeading(__('admin.edit_seo_configuration'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('2xl')
            ->modalSubmitActionLabel(__('admin.save_changes'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->fillForm(fn (SeoPage $record): array => $record->toArray())
            ->schema([
                Select::make('page_type')
                    ->label(__('admin.page_type'))
                    ->options(collect(SeoPageType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->getLabel()]))
                    ->required()
                    ->disabled(),
                FileUpload::make('og_image')
                    ->label(__('admin.og_image'))
                    ->disk('public')
                    ->directory('seo')
                    ->image()
                    ->maxSize(1024)
                    ->helperText(__('admin.maximum_size_1mb'))
                    ->required(),
                TextInput::make('meta_title')
                    ->label(__('admin.meta_title'))
                    ->maxLength(255)
                    ->required(),
                Textarea::make('meta_description')
                    ->label(__('admin.meta_description'))
                    ->rows(3)
                    ->required(),
                Textarea::make('meta_keyword')
                    ->label(__('admin.meta_keywords'))
                    ->placeholder(__('admin.eg_hotel_travel_luxury'))
                    ->rows(2)
                    ->required(),
                Textarea::make('schema_markup')
                    ->label(__('admin.schema_markup'))
                    ->placeholder(__('admin.enter_schema_markup'))
                    ->helperText(new HtmlString(__('admin.schema_markup_helper')))
                    ->rows(5),
            ])
            ->action(function (SeoPage $record, array $data): void {
                $record->update($data);

                Notification::make()
                    ->title(__('admin.seo_configuration_saved'))
                    ->success()
                    ->send();
            });
    }

    private function getDeleteAction(): Action
    {
        return Action::make('delete')
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('gray')
            ->before(static::enforceDeletePermission())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalHeading(__('admin.delete_seo_configuration'))
            ->modalDescription(__('admin.delete_seo_configuration_warning'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->action(function (SeoPage $record): void {
                $record->delete();

                Notification::make()
                    ->title(__('admin.seo_configuration_deleted'))
                    ->success()
                    ->send();
            });
    }
}
