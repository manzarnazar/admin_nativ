<?php

namespace App\Filament\Pages;

use App\Enums\DisplayPlatform;
use App\Enums\HomepageSectionType;
use App\Enums\PropertyStatus;
use App\Enums\SortByRule;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\City;
use App\Models\Country;
use App\Models\HomepageSection;
use App\Models\Property;
use App\Models\PropertyType;
use App\Services\HomepagePreviewOverrideService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;
use Livewire\Attributes\Url;

class HomepageSectionManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithTable;

    #[Url(as: 'tab')]
    public string $activeTab = 'country';

    public static function topbarControls(): array
    {
        return ['country' => true, 'property' => false];
    }

    protected static ?string $slug = 'homepage-sections';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.homepage-section-manage';

    public static function getNavigationLabel(): string
    {
        return __('admin.manage_homepage');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::ContentManagement);
    }

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public function getTitle(): string
    {
        return __('admin.homepage_content_management');
    }

    public function getSubheading(): ?string
    {
        return __('admin.configure_and_moderate_property_recommendation_sections');
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetTable();
    }

    protected function getViewData(): array
    {
        $this->refreshPreviewOverride();

        return [
            'platformWarning' => $this->getPlatformWarning(),
            'countryName' => Auth::user()->currentCountry?->name ?? __('admin.country'),
        ];
    }

    /**
     * Keep the Live Preview's country/global override in sync with the active tab,
     * so the embedded iframe reflects what's being edited without any frontend
     * changes — see App\Services\HomepagePreviewOverrideService.
     */
    protected function refreshPreviewOverride(): void
    {
        app(HomepagePreviewOverrideService::class)->remember(
            ip: request()->ip(),
            countryId: $this->activeTab === 'global' ? null : Auth::user()->current_country_id,
            forceGlobal: $this->activeTab === 'global',
        );
    }

    protected function getPlatformWarning(): ?string
    {
        $countryId = Auth::user()->current_country_id;

        if (! $countryId) {
            return null;
        }

        $hasWeb = HomepageSection::query()
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->whereIn('display_platform', [DisplayPlatform::Web->value, DisplayPlatform::Both->value])
            ->exists();

        $hasApp = HomepageSection::query()
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->whereIn('display_platform', [DisplayPlatform::App->value, DisplayPlatform::Both->value])
            ->exists();

        if (! $hasWeb && ! $hasApp) {
            return 'both';
        }
        if (! $hasWeb) {
            return 'web';
        }
        if (! $hasApp) {
            return 'app';
        }

        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewLivePreview')
                ->label(__('admin.view_live_preview'))
                ->icon('phosphor-eye')
                ->color('dark')
                ->button()
                ->extraAttributes(['class' => 'hidden sm:inline-flex'])
                ->modalHeading('')
                ->modalContent(view('filament.pages.homepage-live-preview'))
                ->modalWidth('6xl')
                ->modalCancelAction(false)
                ->modalSubmitAction(fn (Action $action) => $action->label(__('admin.close_preview'))->color('primary')->button())
                ->action(fn () => app(HomepagePreviewOverrideService::class)->forget(request()->ip()))
                ->modalFooterActionsAlignment(Alignment::End)
                ->modalCloseButton(false)
                ->stickyModalFooter(),

            CreateAction::make()
                ->label(__('admin.add_new_section'))
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->button()
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->modalFooterActionsAlignment(Alignment::End)
                ->modalCancelAction(fn (Action $action) => $action->link()->color('gray'))
                ->modalHeading(__('admin.create_homepage_section'))
                ->modalSubmitActionLabel(__('admin.save_section'))
                ->extraModalWindowAttributes(['class' => 'homepage-section-modal'])
                ->schema($this->getFormSchema())
                ->model(HomepageSection::class)
                ->createAnother(false)
                ->mutateDataUsing(function (array $data): array {
                    $data['country_id'] = $this->activeTab === 'global' ? null : Auth::user()->current_country_id;

                    return $data;
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $countryId = Auth::user()->current_country_id;

        $query = $this->activeTab === 'global'
            ? HomepageSection::query()->whereNull('country_id')
            : HomepageSection::query()->where('country_id', $countryId);

        return $table
            ->query($query)
            ->defaultSort('web_display_order')
            ->columns([
                TextColumn::make('section_title')
                    ->label(__('admin.section_header'))
                    ->searchable()
                    ->weight('bold')
                    ->limit(40)
                    ->wrap(),

                TextColumn::make('section_type')
                    ->label(__('admin.section_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->description(fn (HomepageSection $record): ?string => $record->section_type === HomepageSectionType::CityBased ? $record->targetCity?->name : null)
                    ->toggleable(),

                TextColumn::make('property_types_label')
                    ->label(__('admin.property_type'))
                    ->state(function (HomepageSection $record): string {
                        $ids = $record->property_type_ids ?? [];
                        if (empty($ids)) {
                            return __('admin.all');
                        }

                        return PropertyType::query()->whereIn('id', $ids)->pluck('name')->join(', ');
                    })
                    ->toggleable(),

                TextColumn::make('display_platform')
                    ->label(__('admin.platform'))
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->icon(fn ($state): ?string => match ($state) {
                        DisplayPlatform::Web => 'heroicon-o-computer-desktop',
                        DisplayPlatform::App => 'heroicon-o-device-phone-mobile',
                        DisplayPlatform::Both => 'heroicon-o-globe-alt',
                        default => null,
                    })
                    ->toggleable(),

                TextColumn::make('matching_properties')
                    ->label(__('admin.matching_properties'))
                    ->state(fn (HomepageSection $record): string => $this->calculateMatchingProperties($record).' '.__('admin.properties'))
                    ->toggleable(),

                TextColumn::make('web_display_order')
                    ->label(__('admin.display_order'))
                    ->formatStateUsing(fn ($state): string => 'Web: '.$state)
                    ->description(fn (HomepageSection $record): string => 'App: '.$record->app_display_order)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('is_active')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? __('admin.active') : __('admin.inactive'))
                    ->color(fn ($state): string => $state ? 'success' : 'danger')
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.status'))
                    ->trueLabel(__('admin.active'))
                    ->falseLabel(__('admin.inactive')),
                SelectFilter::make('display_platform')
                    ->label(__('admin.platform'))
                    ->options(DisplayPlatform::class),
                SelectFilter::make('section_type')
                    ->label(__('admin.section_type'))
                    ->options(HomepageSectionType::class),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label(fn () => new HtmlString('<div class="flex items-center gap-2"><span>'.__('admin.filters').'</span><svg class="w-4 h-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg></div>'))
                    ->icon('heroicon-o-funnel')
                    ->color('gray')
                    ->extraAttributes([
                        'class' => 'bg-white border-gray-300 text-gray-700 shadow-sm rounded-md',
                    ])
            )
            ->columnManagerTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label(__('admin.columns'))
                    ->icon('heroicon-o-view-columns')
                    ->color('gray')
                    ->extraAttributes([
                        'class' => 'bg-white border-gray-300 text-gray-700 shadow-sm rounded-md',
                    ])
            )
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->modalCancelAction(fn (Action $action) => $action->link()->color('gray'))
                    ->modalHeading(__('admin.edit_homepage_section'))
                    ->modalSubmitActionLabel(__('admin.save_section'))
                    ->extraModalWindowAttributes(['class' => 'homepage-section-modal'])
                    ->schema($this->getFormSchema())
                    ->mutateDataUsing(function (array $data): array {
                        $data['country_id'] = $this->activeTab === 'global' ? null : Auth::user()->current_country_id;

                        return $data;
                    }),

                DeleteAction::make()
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray'),
            ])
            ->emptyStateHeading(__('admin.no_sections_yet'))
            ->emptyStateDescription(__('admin.no_sections_description'));
    }

    protected function getFormSchema(): array
    {
        $isCountryTab = $this->activeTab !== 'global';

        return [
            Section::make(__('admin.section_details'))
                ->description(__('admin.section_details_description'))
                ->compact()
                ->schema([
                    TextInput::make('section_title')
                        ->label(__('admin.section_title'))
                        ->required()
                        ->maxLength(255)
                        ->placeholder(__('admin.enter_section_title'))
                        ->columnSpanFull(),

                    Select::make('section_type')
                        ->label(__('admin.section_type'))
                        ->options(
                            // Recently Viewed is a synthetic, per-user section built at request
                            // time (see RecentlyViewedService) — never a real admin-managed row.
                            collect(HomepageSectionType::cases())
                                ->reject(fn (HomepageSectionType $type) => $type === HomepageSectionType::RecentlyViewed)
                                ->mapWithKeys(fn (HomepageSectionType $type) => [$type->value => $type->getLabel()])
                        )
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state) {
                            if ($state !== HomepageSectionType::CityBased->value) {
                                $set('target_city_id', null);
                            }
                        }),

                    ViewField::make('display_platform')
                        ->label(__('admin.display_platform'))
                        ->view('filament.forms.components.display-platform-toggle')
                        ->required()
                        ->live()
                        ->default(DisplayPlatform::Both->value),
                ])
                ->columns(2),

            Section::make(__('admin.filters_and_rules'))
                ->description(__('admin.filters_and_rules_description'))
                ->compact()
                ->schema([
                    Select::make('target_country_id')
                        ->label(__('admin.target_country'))
                        ->options(Country::query()->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->placeholder('e.g., India')
                        ->default($isCountryTab ? Auth::user()->current_country_id : null)
                        ->live(),

                    Select::make('target_city_id')
                        ->label(__('admin.target_city'))
                        ->options(fn (Get $get): array => City::query()
                            ->when(
                                $get('target_country_id') ?? ($isCountryTab ? Auth::user()->current_country_id : null),
                                fn ($q, $id) => $q->where('country_id', $id)
                            )
                            ->pluck('name', 'id')
                            ->toArray())
                        ->searchable()
                        ->nullable()
                        ->live()
                        ->placeholder('e.g., New Delhi')
                        ->required(fn (Get $get): bool => $get('section_type') === HomepageSectionType::CityBased->value)
                        ->hidden(fn (Get $get): bool => $get('section_type') !== HomepageSectionType::CityBased->value),

                    Select::make('property_type_ids')
                        ->label(__('admin.property_type'))
                        ->options(function (Get $get) use ($isCountryTab): array {
                            $countryId = $get('target_country_id') ?? ($isCountryTab ? Auth::user()->current_country_id : null);

                            return PropertyType::query()
                                ->where('is_active', true)
                                ->when(
                                    $countryId,
                                    fn ($query) => $query->whereHas(
                                        'countries',
                                        fn ($sub) => $sub
                                            ->where('country_property_types.country_id', $countryId)
                                            ->where('country_property_types.is_enabled', true)
                                    )
                                )
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->multiple()
                        ->searchable()
                        ->nullable()
                        ->live()
                        ->placeholder(__('admin.select_property_type')),

                    Select::make('sort_by_rule')
                        ->label(__('admin.sort_by_rule'))
                        ->options(
                            collect(SortByRule::cases())
                                ->reject(fn (SortByRule $rule) => $rule === SortByRule::Newest) // Temporarily disabled as per request
                                ->mapWithKeys(fn (SortByRule $rule) => [$rule->value => $rule->getLabel()])
                        )
                        ->required()
                        ->default(SortByRule::HighestRating->value),

                ])
                ->columns(2),

            Section::make(__('admin.sequence_and_visibility'))
                ->description(__('admin.sequence_and_visibility_description'))
                ->compact()
                ->schema([
                    Select::make('web_display_order')
                        ->label(__('admin.web_display_order'))
                        ->options(array_combine(range(1, 50), range(1, 50)))
                        ->required(fn (Get $get): bool => $get('display_platform') !== DisplayPlatform::App->value)
                        ->hidden(fn (Get $get): bool => $get('display_platform') === DisplayPlatform::App->value)
                        ->unique(
                            table: HomepageSection::class,
                            column: 'web_display_order',
                            ignoreRecord: true,
                            modifyRuleUsing: function (Unique $rule) use ($isCountryTab) {
                                $countryId = $isCountryTab ? Auth::user()->current_country_id : null;

                                return $rule->where('country_id', $countryId)
                                    ->where('display_platform', '!=', DisplayPlatform::App->value)
                                    ->withoutTrashed();
                            }
                        )
                        ->default(function () use ($isCountryTab) {
                            $countryId = $isCountryTab ? Auth::user()->current_country_id : null;
                            $max = HomepageSection::query()
                                ->where('country_id', $countryId)
                                ->where('display_platform', '!=', DisplayPlatform::App->value)
                                ->max('web_display_order');

                            $next = (int) $max + 1;

                            return $next > 50 ? 50 : $next;
                        }),

                    Select::make('app_display_order')
                        ->label(__('admin.app_display_order'))
                        ->options(array_combine(range(1, 50), range(1, 50)))
                        ->required(fn (Get $get): bool => $get('display_platform') !== DisplayPlatform::Web->value)
                        ->hidden(fn (Get $get): bool => $get('display_platform') === DisplayPlatform::Web->value)
                        ->unique(
                            table: HomepageSection::class,
                            column: 'app_display_order',
                            ignoreRecord: true,
                            modifyRuleUsing: function (Unique $rule) use ($isCountryTab) {
                                $countryId = $isCountryTab ? Auth::user()->current_country_id : null;

                                return $rule->where('country_id', $countryId)
                                    ->where('display_platform', '!=', DisplayPlatform::Web->value)
                                    ->withoutTrashed();
                            }
                        )
                        ->default(function () use ($isCountryTab) {
                            $countryId = $isCountryTab ? Auth::user()->current_country_id : null;
                            $max = HomepageSection::query()
                                ->where('country_id', $countryId)
                                ->where('display_platform', '!=', DisplayPlatform::Web->value)
                                ->max('app_display_order');

                            $next = (int) $max + 1;

                            return $next > 50 ? 50 : $next;
                        }),

                    Radio::make('is_active')
                        ->label(__('admin.status'))
                        ->boolean(
                            trueLabel: __('admin.active'),
                            falseLabel: __('admin.inactive'),
                        )
                        ->inline()
                        ->default(true),
                ])
                ->columns(3),

            Section::make('Property count preview')
                ->compact()
                ->schema([
                    Grid::make(3)
                        ->extraAttributes(['class' => 'items-center'])
                        ->schema([
                            TextEntry::make('property_count_preview')
                                ->hiddenLabel()
                                ->columnSpan(2)
                                ->state(function (Get $get) use ($isCountryTab): HtmlString {
                                    $countryId = $get('target_country_id') ?? ($isCountryTab ? Auth::user()->current_country_id : null);

                                    if (! $countryId) {
                                        return new HtmlString('');
                                    }

                                    $query = Property::query()
                                        ->where('status', PropertyStatus::Active)
                                        ->where('country_id', $countryId);

                                    if ($cityId = $get('target_city_id')) {
                                        $refCityId = City::find($cityId)?->ref_city_id;
                                        if ($refCityId) {
                                            $query->where('ref_city_id', $refCityId);
                                        }
                                    }

                                    if ($typeIds = $get('property_type_ids')) {
                                        $query->whereIn('property_type_id', $typeIds);
                                    }

                                    $count = $query->count();

                                    if ($count === 0) {
                                        return new HtmlString(
                                            '<div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 dark:border-amber-800 dark:bg-amber-950">'.
                                                '<svg class="h-4 w-4 flex-shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>'.
                                                '<span class="text-sm font-medium text-amber-700 dark:text-amber-400">'.__('admin.no_matching_properties_found').'</span>'.
                                                '</div>'
                                        );
                                    }

                                    $label = $count === 1 ? __('admin.matching_property_found') : __('admin.matching_properties_found');

                                    return new HtmlString(
                                        '<div class="flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-3 py-2.5 dark:border-green-800 dark:bg-green-950">'.
                                            '<div class="flex items-center gap-2">'.
                                            '<svg class="h-4 w-4 flex-shrink-0 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" /></svg>'.
                                            '<span class="text-sm font-medium text-green-700 dark:text-green-400">'.$count.' '.$label.'</span>'.
                                            '</div>'.
                                            '</div>'
                                    );
                                }),

                            Actions::make([
                                Action::make('previewProperties')
                                    ->label(__('admin.view_properties'))
                                    ->icon('heroicon-m-eye')
                                    ->color('gray')
                                    ->modalHeading(__('admin.matching_properties') ?? 'Matching Properties')
                                    ->modalSubmitAction(false)
                                    ->modalCancelAction(false)
                                    ->modalContent(function (Get $get) use ($isCountryTab) {
                                        $countryId = $get('target_country_id') ?? ($isCountryTab ? Auth::user()->current_country_id : null);
                                        if (! $countryId) {
                                            return view('filament.components.property-preview-modal', ['properties' => collect(), 'totalCount' => 0]);
                                        }

                                        $query = Property::query()
                                            ->where('status', PropertyStatus::Active)
                                            ->where('country_id', $countryId);

                                        if ($cityId = $get('target_city_id')) {
                                            $refCityId = City::find($cityId)?->ref_city_id;
                                            if ($refCityId) {
                                                $query->where('ref_city_id', $refCityId);
                                            }
                                        }

                                        if ($typeIds = $get('property_type_ids')) {
                                            $query->whereIn('property_type_id', $typeIds);
                                        }

                                        $totalCount = $query->count();
                                        $properties = $query->with(['propertyType', 'city'])->limit(20)->get();

                                        return view('filament.components.property-preview-modal', [
                                            'properties' => $properties,
                                            'totalCount' => $totalCount,
                                        ]);
                                    })
                                    ->visible(function (Get $get) use ($isCountryTab) {
                                        $countryId = $get('target_country_id') ?? ($isCountryTab ? Auth::user()->current_country_id : null);
                                        if (! $countryId) {
                                            return false;
                                        }

                                        $query = Property::query()
                                            ->where('status', PropertyStatus::Active)
                                            ->where('country_id', $countryId);

                                        if ($cityId = $get('target_city_id')) {
                                            $refCityId = City::find($cityId)?->ref_city_id;
                                            if ($refCityId) {
                                                $query->where('ref_city_id', $refCityId);
                                            }
                                        }

                                        if ($typeIds = $get('property_type_ids')) {
                                            $query->whereIn('property_type_id', $typeIds);
                                        }

                                        return $query->count() > 0;
                                    }),
                            ])
                                ->columnSpan(1)
                                ->alignEnd(),
                        ]),
                ]),
        ];
    }

    protected function calculateMatchingProperties(HomepageSection $section): int
    {
        $countryId = $section->target_country_id ?? $section->country_id ?? Auth::user()->current_country_id;

        $query = Property::query()
            ->where('status', PropertyStatus::Active)
            ->where('country_id', $countryId);

        if ($section->target_city_id) {
            $refCityId = $section->targetCity?->ref_city_id;
            if ($refCityId) {
                $query->where('ref_city_id', $refCityId);
            }
        }

        if (! empty($section->property_type_ids)) {
            $query->whereIn('property_type_id', $section->property_type_ids);
        }

        return $query->count();
    }
}
