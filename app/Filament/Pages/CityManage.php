<?php

namespace App\Filament\Pages;

use App\Enums\CityStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Jobs\FetchCityNearbyPlacesJob;
use App\Models\City;
use App\Models\Country;
use App\Models\NearbyPlace;
use App\Models\NearbyPlaceCategory;
use App\Models\RefCity;
use App\Models\RefState;
use App\Models\User;
use App\Services\CityService;
use App\Services\GooglePlacesService;
use App\Services\OpenStreetMapService;
use App\Support\MapProvider;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

class CityManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'cities';

    #[Url(as: 'tab')]
    public string $currentTab = 'cities';

    public ?int $editingCityId = null;

    public string $nearbySearchQuery = '';

    /** @var array<int, array<string, mixed>> */
    public array $nearbySearchResults = [];

    public ?int $nearbySearchCategoryId = null;

    /** @var array<int, array<int, array<string, mixed>>> Places to add, keyed by category ID */
    public array $pendingPlaces = [];

    /** @var array<int, int> IDs of existing places to remove on save */
    public array $pendingRemovals = [];

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function searchNearbyPlaces(int $categoryId, string $query = ''): void
    {
        $this->nearbySearchCategoryId = $categoryId;
        $this->nearbySearchQuery = $query;

        if (blank($query) || strlen($query) < 3) {
            $this->nearbySearchResults = [];

            return;
        }

        /** @var User $user */
        $user = auth()->user();

        $countryCode = Country::query()
            ->where('id', $user->current_country_id)
            ->value('iso_code');

        $city = $this->editingCityId ? City::find($this->editingCityId) : null;
        $cityLat = $city && filled($city->latitude) ? (float) $city->latitude : null;
        $cityLng = $city && filled($city->longitude) ? (float) $city->longitude : null;

        if (MapProvider::isOsm()) {
            $osmResults = app(OpenStreetMapService::class)
                ->searchAddress($query, limit: 5, countryCode: strtolower($countryCode ?? ''), lat: $cityLat, lng: $cityLng);

            $this->nearbySearchResults = array_map(function (array $r): array {
                $addr = is_array($r['address']) ? $r['address'] : [];
                $addrParts = array_filter([
                    $addr['road'] ?? null,
                    $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? null,
                ]);

                return [
                    'place_id' => $r['place_id'],
                    'name' => explode(',', $r['display_name'])[0],
                    'latitude' => $r['lat'],
                    'longitude' => $r['lon'],
                    'address' => $addrParts ? implode(', ', $addrParts) : $r['display_name'],
                    'rating' => null,
                ];
            }, $osmResults);
        } else {
            $this->nearbySearchResults = app(GooglePlacesService::class)
                ->searchPlaces($query, countryCode: $countryCode, lat: $cityLat, lng: $cityLng);
        }
    }

    public function addNearbyPlace(int $categoryId, int $resultIndex): void
    {
        if (! isset($this->nearbySearchResults[$resultIndex])) {
            return;
        }

        $result = $this->nearbySearchResults[$resultIndex];

        // Check if already in pending list
        $existingPending = $this->pendingPlaces[$categoryId] ?? [];
        foreach ($existingPending as $pending) {
            if ($pending['place_id'] === $result['place_id']) {
                Notification::make()
                    ->title(__('admin.place_already_added'))
                    ->warning()
                    ->send();

                return;
            }
        }

        // Check if already exists in DB for this city
        if (NearbyPlace::query()->where('city_id', $this->editingCityId)->where('google_place_id', $result['place_id'])->exists()) {
            Notification::make()
                ->title(__('admin.place_already_added'))
                ->warning()
                ->send();

            return;
        }

        $this->pendingPlaces[$categoryId][] = [
            'place_id' => $result['place_id'],
            'name' => $result['name'],
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
            'address' => $result['address'],
            'rating' => $result['rating'],
        ];

        // Remove from search results
        unset($this->nearbySearchResults[$resultIndex]);
        $this->nearbySearchResults = array_values($this->nearbySearchResults);
    }

    public function removePendingPlace(int $categoryId, int $index): void
    {
        if (isset($this->pendingPlaces[$categoryId][$index])) {
            unset($this->pendingPlaces[$categoryId][$index]);
            $this->pendingPlaces[$categoryId] = array_values($this->pendingPlaces[$categoryId]);

            if (empty($this->pendingPlaces[$categoryId])) {
                unset($this->pendingPlaces[$categoryId]);
            }
        }
    }

    public function markPlaceForRemoval(int $placeId): void
    {
        if (! in_array($placeId, $this->pendingRemovals)) {
            $this->pendingRemovals[] = $placeId;
        }
    }

    public function unmarkPlaceForRemoval(int $placeId): void
    {
        $this->pendingRemovals = array_values(array_filter(
            $this->pendingRemovals,
            fn (int $id): bool => $id !== $placeId,
        ));
    }

    /**
     * @return Collection<int, Collection<int, NearbyPlace>>
     */
    public function getExistingNearbyPlaces(): Collection
    {
        if (! $this->editingCityId) {
            return collect();
        }

        return NearbyPlace::query()
            ->where('city_id', $this->editingCityId)
            ->whereNotIn('id', $this->pendingRemovals)
            ->get()
            ->groupBy('nearby_place_category_id');
    }

    private function resetNearbyState(): void
    {
        $this->nearbySearchQuery = '';
        $this->nearbySearchResults = [];
        $this->nearbySearchCategoryId = null;
        $this->pendingPlaces = [];
        $this->pendingRemovals = [];
    }

    private function saveNearbyPlaces(): void
    {
        // Delete places marked for removal
        if (! empty($this->pendingRemovals)) {
            NearbyPlace::query()->whereIn('id', $this->pendingRemovals)->forceDelete();
        }

        // Insert new pending places
        foreach ($this->pendingPlaces as $categoryId => $places) {
            foreach ($places as $place) {
                NearbyPlace::query()->updateOrCreate(
                    [
                        'city_id' => $this->editingCityId,
                        'google_place_id' => $place['place_id'],
                    ],
                    [
                        'nearby_place_category_id' => $categoryId,
                        'name' => $place['name'],
                        'latitude' => $place['latitude'],
                        'longitude' => $place['longitude'],
                        'address' => $place['address'],
                        'rating' => $place['rating'],
                    ],
                );
            }
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.city_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.city_manage');
    }

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.city-manage';

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
        return __('admin.city_management');
    }

    public function getSubheading(): ?string
    {
        return __('admin.manage_city_boundaries_states_and_location_services');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function switchTab(string $tab): void
    {
        $this->currentTab = $tab;
    }

    public function hasCities(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return City::query()->forCountry($user->current_country_id)->exists();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function addNewCityAction(): Action
    {
        return Action::make('addNewCity')
            ->label(__('admin.add_new_city'))
            ->modalHeading(__('admin.add_new_city'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('md')
            ->modalSubmitActionLabel(__('admin.add_city'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->disabled(static::disabledUnlessCanCreate())
            ->schema($this->getCityFormSchema())
            ->action(function (array $data): void {
                /** @var User $user */
                $user = auth()->user();

                app(CityService::class)->createCity($data, $user->current_country_id);

                Notification::make()
                    ->title(__('admin.city_created_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->query(
                City::query()->forCountry($user->current_country_id)->with('country')
            )
            ->columns([
                ViewColumn::make('name')
                    ->label(__('admin.city_name'))
                    ->view('filament.tables.columns.city-name')
                    ->searchable(),
                TextColumn::make('state.name')
                    ->label(__('admin.stateprovince'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latitude')
                    ->label(__('admin.latitude'))
                    ->formatStateUsing(function (string $state): string {
                        $value = (float) $state;
                        $direction = $value >= 0 ? 'N' : 'S';

                        return number_format(abs($value), 2).' '.$direction;
                    })
                    ->color('info'),
                TextColumn::make('longitude')
                    ->label(__('admin.longitude'))
                    ->formatStateUsing(function (string $state): string {
                        $value = (float) $state;
                        $direction = $value >= 0 ? 'E' : 'W';

                        return number_format(abs($value), 2).' '.$direction;
                    })
                    ->color('info'),
                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (CityStatus $state): string => $state->label())
                    ->color(fn (CityStatus $state): string => match ($state) {
                        CityStatus::Active => 'success',
                        CityStatus::Inactive => 'gray',
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
                    ->label(__('admin.edit'))
                    ->icon('phosphor-pencil-simple-line')
                    ->iconButton()
                    ->color('gray')
                    ->tooltip(__('admin.edit'))
                    ->disabled(static::disabledUnlessCanEdit())
                    ->modalHeading(__('admin.edit_city'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('2xl')
                    ->modalSubmitActionLabel(__('admin.save_city'))
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->mountUsing(function (Schema $form, City $record): void {
                        $this->editingCityId = $record->id;
                        $this->resetNearbyState();

                        $refStateId = $record->state?->ref_state_id;

                        // State-as-city fallback: ref_city_id is null
                        $refCityValue = $record->ref_city_id
                            ? $record->ref_city_id
                            : ($refStateId ? "state:{$refStateId}" : null);

                        $form->fill([
                            'ref_state_id' => $refStateId,
                            'ref_city_id' => $refCityValue,
                            'latitude' => $record->latitude,
                            'longitude' => $record->longitude,
                            'status' => $record->status->value,
                        ]);
                    })
                    ->schema(fn (City $record): array => $this->getEditCitySchema($record))
                    ->action(function (City $record, array $data): void {
                        /** @var User $user */
                        $user = auth()->user();

                        if ($data['status'] === 'inactive' && $record->status === CityStatus::Active) {
                            $activeCityCount = City::query()
                                ->forCountry($user->current_country_id)
                                ->active()
                                ->count();

                            if ($activeCityCount <= 1) {
                                Notification::make()
                                    ->title(__('admin.at_least_one_active_city_is_required'))
                                    ->danger()
                                    ->send();

                                return;
                            }
                        }

                        app(CityService::class)->updateCity($record, $data, $user->current_country_id);

                        $this->saveNearbyPlaces();
                        $this->resetNearbyState();

                        Notification::make()
                            ->title(__('admin.city_updated_successfully'))
                            ->success()
                            ->send();
                    }),
                Action::make('refetchNearbyPlaces')
                    ->iconButton()
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->tooltip(__('admin.refetch_nearby_places'))
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-arrow-path')
                    ->modalHeading(__('admin.refetch_nearby_places'))
                    ->modalDescription(__('admin.refetch_nearby_places_description'))
                    ->modalSubmitActionLabel(__('admin.yes_refetch'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('primary'))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->action(function (City $record): void {
                        FetchCityNearbyPlacesJob::dispatch($record);

                        Notification::make()
                            ->title(__('admin.nearby_places_fetch_queued'))
                            ->body(__('admin.nearby_places_will_be_fetched_in_background'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('cities')
                    ->exports([
                        'name' => 'City Name',
                        'state.name' => 'State/Province',
                        'latitude' => 'Latitude',
                        'longitude' => 'Longitude',
                        'status' => ['label' => 'Status', 'formatter' => fn (City $record): string => $record->status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_cities_added_yet'))
            ->emptyStateDescription(__('admin.cities_are_used_to_help_customers_search_and_discover_properties_on_the_website_add_cities_to_improve_locationbased_search_results_and_user_experience'))
            ->emptyStateIcon('heroicon-o-map-pin')
            ->defaultPaginationPageOption(4);
    }

    /**
     * @return array<int, Component|\Filament\Schemas\Components\Component>
     */
    private function getCityFormSchema(bool $isEdit = false, ?int $excludeRefCityId = null, ?int $excludeCityId = null): array
    {
        /** @var User $user */
        $user = auth()->user();
        $country = $user->currentCountry;
        $countryName = e($country->name);
        $isoCode = e($country->iso_code);
        $flagUrl = asset('assets/flags/'.strtolower($country->iso_code).'.svg');
        $refCountryId = $country->ref_country_id;

        // Get already-added ref_city_ids to exclude from the dropdown
        $addedRefCityIds = City::query()
            ->forCountry($user->current_country_id)
            ->whereNotNull('ref_city_id')
            ->when($excludeRefCityId, fn ($q) => $q->where('ref_city_id', '!=', $excludeRefCityId))
            ->pluck('ref_city_id')
            ->all();

        // Get ref_state_ids that already have a state-as-city entry (ref_city_id is null)
        $addedStateAsCityRefStateIds = City::query()
            ->where('cities.country_id', $user->current_country_id)
            ->whereNull('cities.ref_city_id')
            ->when($excludeCityId, fn ($q) => $q->where('cities.id', '!=', $excludeCityId))
            ->join('states', 'cities.state_id', '=', 'states.id')
            ->pluck('states.ref_state_id')
            ->all();

        $schema = [];

        $schema[] = Text::make(new HtmlString(
            '<div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">'.
            '<p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Selected Country</p>'.
            '<div class="flex items-center gap-3">'.
            '<img src="'.$flagUrl.'" class="h-6 w-8 rounded object-cover" alt="'.$countryName.'">'.
            '<div>'.
            '<p class="font-semibold text-gray-900 dark:text-white">'.$countryName.'</p>'.
            '<p class="text-xs text-gray-500 dark:text-gray-400">ISO · '.$isoCode.'</p>'.
            '</div>'.
            '</div>'.
            '</div>'
        ));

        $schema[] = Text::make(new HtmlString(
            '<div class="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">'.
            '<p class="text-sm text-gray-600 dark:text-gray-300">'.
            '<span class="font-medium">Note:</span> City will be added under the selected country. Only cities belonging to '.$countryName.' can be added here.'.
            '</p>'.
            '</div>'
        ));

        if ($isEdit) {
            $schema[] = Text::make(new HtmlString(
                '<div class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">'.
                '<p class="text-sm text-gray-600 dark:text-gray-300">'.
                '<span class="font-medium">Warning:</span> Changing the city details may affect properties linked to this city.'.
                '</p>'.
                '</div>'
            ));
        }

        $schema[] = Select::make('ref_state_id')
            ->label(__('admin.state_province'))
            ->options(fn (): Collection => RefState::query()
                ->where('country_id', $refCountryId)
                ->orderBy('name')
                ->pluck('name', 'id'))
            ->searchable()
            ->required()
            ->live()
            ->afterStateUpdated(fn (Select $component) => $component
                ->getContainer()
                ->getComponent('citySelectGrid')
                ->getChildSchema()
                ->fill());

        $schema[] = Grid::make(1)
            ->schema(fn (Get $get): array => [
                Select::make('ref_city_id')
                    ->label(__('admin.city'))
                    ->options(function () use ($get, $addedRefCityIds, $addedStateAsCityRefStateIds): Collection {
                        $stateId = $get('ref_state_id');
                        if (! $stateId) {
                            return collect();
                        }

                        $cities = RefCity::query()
                            ->where('state_id', $stateId)
                            ->when($addedRefCityIds, fn ($q) => $q->whereNotIn('id', $addedRefCityIds))
                            ->orderBy('name')
                            ->pluck('name', 'id');

                        // Fallback: only offer the state itself when the state has NO
                        // ref_cities at all (not when they're all filtered out as already added)
                        if ($cities->isEmpty() && ! RefCity::query()->where('state_id', $stateId)->exists()) {
                            if (in_array((int) $stateId, $addedStateAsCityRefStateIds, true)) {
                                return collect();
                            }

                            $refState = RefState::find($stateId);
                            if ($refState) {
                                return collect(["state:{$stateId}" => "{$refState->name} (state)"]);
                            }
                        }

                        return $cities;
                    })
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if (! $state) {
                            $set('latitude', null);
                            $set('longitude', null);

                            return;
                        }

                        // Handle state-as-city fallback
                        if (str_starts_with((string) $state, 'state:')) {
                            $refState = RefState::find((int) str_replace('state:', '', $state));
                            if ($refState) {
                                $set('latitude', $refState->latitude);
                                $set('longitude', $refState->longitude);
                            }

                            return;
                        }

                        $refCity = RefCity::find($state);
                        if ($refCity) {
                            $set('latitude', $refCity->latitude);
                            $set('longitude', $refCity->longitude);
                        }
                    }),
            ])
            ->key('citySelectGrid');

        $schema[] = Grid::make(2)
            ->schema([
                TextInput::make('latitude')
                    ->label(__('admin.latitude'))
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('longitude')
                    ->label(__('admin.longitude'))
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
            ]);

        $schema[] = Radio::make('status')
            ->label(__('admin.status'))
            ->options([
                'active' => __('admin.active'),
                'inactive' => __('admin.inactive'),
            ])
            ->default('active')
            ->required()
            ->inline();

        return $schema;
    }

    /**
     * @return array<int, Component|\Filament\Schemas\Components\Component>
     */
    private function getEditCitySchema(City $record): array
    {
        $schema = $this->getCityFormSchema(isEdit: true, excludeRefCityId: $record->ref_city_id, excludeCityId: $record->id);

        $schema[] = View::make('filament.schemas.components.nearby-places-editor')
            ->viewData([
                'cityId' => $record->id,
                'categories' => NearbyPlaceCategory::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(),
                'hasApiKey' => MapProvider::isOsm()
                    || app(GooglePlacesService::class)->hasApiKey(),
            ]);

        return $schema;
    }
}
