<?php

namespace App\Livewire;

use App\Filament\Actions\TableExportAction;
use App\Filament\Pages\CityManage;
use App\Jobs\FetchCityNearbyPlacesJob;
use App\Models\City;
use App\Models\NearbyPlaceCategory;
use App\Support\MapProvider;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;

class NearbyPlaceCategoryTable extends TableComponent
{
    public function mount(): void
    {
        // Pre-set the sort state to match the default sort so the column's
        // arrow icon reflects an "active" sort from page load. Without this,
        // Filament treats the first click as activation (ASC → ASC, no
        // visible change) instead of a real toggle.
        $this->tableSort = 'sort_order:asc';
    }

    /**
     * Override Filament's default 3-state sort cycle (ASC → DESC → null)
     * with a 2-state cycle (ASC ↔ DESC). The null state causes a "wasted"
     * click because the table falls back to defaultSort but the column
     * appears unsorted — next click "activates" without visible toggle.
     */
    public function sortTable(?string $column = null, ?string $direction = null): void
    {
        if ($column === $this->getTableSortColumn()) {
            $direction ??= $this->getTableSortDirection() === 'asc' ? 'desc' : 'asc';
        } else {
            $direction ??= 'asc';
        }

        $this->tableSort = "{$column}:{$direction}";

        $this->updatedTableSort();
    }

    public function getHasCategories(): bool
    {
        return NearbyPlaceCategory::query()->exists();
    }

    public function createCategoryAction(): Action
    {
        return Action::make('createCategory')
            ->label(__('admin.create_category'))
            ->disabled(CityManage::disabledUnlessCanCreate())
            ->modalHeading(__('admin.add_new_category'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('md')
            ->modalSubmitActionLabel(__('admin.save_category'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
            ->schema($this->getCategoryFormSchema())
            ->action(function (array $data): void {
                $category = NearbyPlaceCategory::query()->create($data);

                $this->dispatchFetchJobsForCategory($category);

                Notification::make()
                    ->title(__('admin.category_created_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(NearbyPlaceCategory::query())
            ->defaultSort('sort_order', 'asc')
            ->columns([
                ImageColumn::make('icon')
                    ->label(__('admin.icon'))
                    ->disk('public')
                    ->square()
                    ->imageSize(40)
                    ->grow(false),
                TextColumn::make('name')
                    ->label(__('admin.category_name'))
                    ->searchable()
                    ->limit(30)
                    ->wrap(),
                TextColumn::make('sort_order')
                    ->label(__('admin.sort_order'))
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('admin.active'))
                    ->disabled(CityManage::disabledUnlessCanEdit()),
            ])
            ->recordActions([
                Action::make('edit')
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->disabled(CityManage::disabledUnlessCanEdit())
                    ->modalHeading(__('admin.edit_category'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('md')
                    ->modalSubmitActionLabel(__('admin.save_category'))
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
                    ->fillForm(fn (NearbyPlaceCategory $record): array => [
                        'name' => $record->name,
                        'icon' => $record->icon,
                        'sort_order' => $record->sort_order,
                        'is_active' => $record->is_active,
                        'google_place_type' => $record->google_place_type,
                        'osm_place_type' => $record->osm_place_type,
                        'radius' => $record->radius,
                        'total_places' => $record->total_places,
                    ])
                    ->schema(fn (NearbyPlaceCategory $record): array => $this->getCategoryFormSchema($record->id))
                    ->action(function (NearbyPlaceCategory $record, array $data): void {
                        $placeTypeChanged = ($record->osm_place_type !== ($data['osm_place_type'] ?? null))
                            || ($record->google_place_type !== ($data['google_place_type'] ?? null));

                        $record->update($data);
                        $record->refresh();

                        if ($placeTypeChanged && SystemMode::isMulti()) {
                            $record->nearbyPlaces()->delete();
                        }

                        $this->dispatchFetchJobsForCategory($record);

                        Notification::make()
                            ->title(__('admin.category_updated_successfully'))
                            ->success()
                            ->send();
                    }),
                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->before(CityManage::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_category'))
                    ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_category_all_places_under_this_category_will_also_be_removed'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
                    ->action(function (NearbyPlaceCategory $record): void {
                        $record->nearbyPlaces()->delete();
                        $record->delete();

                        Notification::make()
                            ->title(__('admin.category_deleted_successfully'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('nearby-place-categories')
                    ->exports([
                        'name' => 'Category Name',
                        'sort_order' => 'Sort Order',
                        'is_active' => ['label' => 'Status', 'formatter' => fn (NearbyPlaceCategory $record): string => $record->is_active ? 'Active' : 'Inactive'],
                    ])
                    ->toActionGroup(),
                Action::make('createNewCategory')
                    ->label('+ '.__('admin.create_category'))
                    ->disabled(CityManage::disabledUnlessCanCreate())
                    ->modalHeading(__('admin.add_new_category'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('md')
                    ->modalSubmitActionLabel(__('admin.save_category'))
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
                    ->schema($this->getCategoryFormSchema())
                    ->action(function (array $data): void {
                        $category = NearbyPlaceCategory::query()->create($data);

                        if (SystemMode::isMulti() && $this->categoryHasPlaceType($category)) {
                            City::query()->each(function (City $city) use ($category): void {
                                FetchCityNearbyPlacesJob::dispatch($city, $category->id);
                            });
                        }

                        Notification::make()
                            ->title(__('admin.category_created_successfully'))
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading(__('admin.no_categories_created'))
            ->emptyStateDescription(__('admin.categories_help_organize_nearby_places'))
            ->emptyStateIcon('heroicon-o-tag')
            ->emptyStateActions([
                Action::make('createCategory')
                    ->label(__('admin.create_category'))
                    ->disabled(CityManage::disabledUnlessCanCreate())
                    ->schema($this->getCategoryFormSchema())
                    ->modalHeading(__('admin.add_new_category'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('md')
                    ->modalSubmitActionLabel(__('admin.save_category'))
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
                    ->action(function (array $data): void {
                        $category = NearbyPlaceCategory::query()->create($data);

                        if (SystemMode::isMulti() && $this->categoryHasPlaceType($category)) {
                            City::query()->each(function (City $city) use ($category): void {
                                FetchCityNearbyPlacesJob::dispatch($city, $category->id);
                            });
                        }

                        Notification::make()
                            ->title(__('admin.category_created_successfully'))
                            ->success()
                            ->send();
                    }),
            ])
            ->queryStringIdentifier('categories')
            ->defaultPaginationPageOption(10);
    }

    /**
     * @return array<int, Component>
     */
    private function getCategoryFormSchema(?int $ignoreId = null): array
    {
        return [
            TextInput::make('name')
                ->label(__('admin.category_name'))
                ->placeholder('e.g, Hospital')
                ->required()
                ->maxLength(255)
                ->unique(table: NearbyPlaceCategory::class, column: 'name', ignorable: $ignoreId ? NearbyPlaceCategory::find($ignoreId) : null)
                ->validationMessages(['unique' => __('admin.this_category_name_already_exists')]),
            Select::make('google_place_type')
                ->label(__('admin.google_place_type'))
                ->options([
                    'Transport' => [
                        'gas_station' => 'Gas Station',
                        'airport' => 'Airport',
                        'bus_station' => 'Bus Station',
                        'train_station' => 'Train Station',
                        'subway_station' => 'Subway Station',
                        'light_rail_station' => 'Light Rail Station',
                        'taxi_stand' => 'Taxi Stand',
                        'parking' => 'Parking',
                    ],
                    'Food & Drink' => [
                        'restaurant' => 'Restaurant',
                        'cafe' => 'Cafe',
                        'bar' => 'Bar',
                        'bakery' => 'Bakery',
                        'fast_food_restaurant' => 'Fast Food Restaurant',
                        'food_court' => 'Food Court',
                        'ice_cream_shop' => 'Ice Cream Shop',
                    ],
                    'Health' => [
                        'hospital' => 'Hospital',
                        'pharmacy' => 'Pharmacy',
                        'dentist' => 'Dentist',
                        'doctor' => 'Doctor',
                        'emergency_room_hospital' => 'Emergency Room',
                    ],
                    'Shopping' => [
                        'shopping_mall' => 'Shopping Mall',
                        'supermarket' => 'Supermarket',
                        'convenience_store' => 'Convenience Store',
                        'clothing_store' => 'Clothing Store',
                        'book_store' => 'Book Store',
                    ],
                    'Entertainment' => [
                        'movie_theater' => 'Movie Theater',
                        'museum' => 'Museum',
                        'art_gallery' => 'Art Gallery',
                        'zoo' => 'Zoo',
                        'amusement_park' => 'Amusement Park',
                        'tourist_attraction' => 'Tourist Attraction',
                        'national_park' => 'National Park',
                        'historical_landmark' => 'Historical Landmark',
                    ],
                    'Religion' => [
                        'place_of_worship' => 'Place of Worship',
                        'mosque' => 'Mosque',
                        'hindu_temple' => 'Hindu Temple',
                        'church' => 'Church',
                        'synagogue' => 'Synagogue',
                    ],
                    'Sports & Wellness' => [
                        'park' => 'Park',
                        'gym' => 'Gym',
                        'swimming_pool' => 'Swimming Pool',
                        'sports_club' => 'Sports Club',
                        'spa' => 'Spa',
                    ],
                    'Services' => [
                        'atm' => 'ATM',
                        'bank' => 'Bank',
                        'post_office' => 'Post Office',
                        'police' => 'Police',
                        'school' => 'School',
                        'university' => 'University',
                        'library' => 'Library',
                    ],
                ])
                ->searchable()
                ->nullable()
                ->visible(fn (): bool => ! MapProvider::isOsm())
                ->helperText(__('admin.google_place_type_helper')),

            Select::make('osm_place_type')
                ->label(__('admin.osm_place_type'))
                ->options(function () use ($ignoreId): array {
                    $usedTypes = NearbyPlaceCategory::query()
                        ->whereNotNull('osm_place_type')
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->pluck('osm_place_type')
                        ->all();

                    return collect(config('maps.osm_types'))
                        ->map(fn (array $group) => array_diff_key($group, array_flip($usedTypes)))
                        ->filter()
                        ->all();
                })
                ->searchable()
                ->nullable()
                ->unique(
                    table: NearbyPlaceCategory::class,
                    column: 'osm_place_type',
                    ignorable: $ignoreId ? NearbyPlaceCategory::find($ignoreId) : null,
                    modifyRuleUsing: fn ($rule) => $rule->whereNull('deleted_at'),
                )
                ->visible(fn (): bool => MapProvider::isOsm())
                ->helperText(__('admin.osm_place_type_helper')),
            TextInput::make('radius')
                ->label(__('admin.radius'))
                ->numeric()
                ->minValue(1)
                ->maxValue(50)
                ->default(5)
                ->suffix('km')
                ->required(),
            TextInput::make('total_places')
                ->label(__('admin.total_places'))
                ->numeric()
                ->minValue(1)
                ->maxValue(20)
                ->default(10)
                ->required()
                ->helperText(__('admin.maximum_20_places')),
            FileUpload::make('icon')
                ->label(__('admin.icon'))
                ->image()
                ->disk('public')
                ->directory('nearby-place-categories')
                ->maxSize(512)
                ->helperText(__('admin.upload_an_icon_for_this_category')),
            TextInput::make('sort_order')
                ->label(__('admin.sort_order'))
                ->numeric()
                ->minValue(1)
                ->required()
                ->default(fn (): int => (NearbyPlaceCategory::query()->max('sort_order') ?? 0) + 1)
                ->unique(
                    table: NearbyPlaceCategory::class,
                    column: 'sort_order',
                    ignorable: $ignoreId ? NearbyPlaceCategory::find($ignoreId) : null,
                    modifyRuleUsing: fn ($rule) => $rule->whereNull('deleted_at'),
                )
                ->validationMessages(['unique' => __('admin.sort_order_already_exists')]),
            Toggle::make('is_active')
                ->label(__('admin.active'))
                ->default(true),
        ];
    }

    private function categoryHasPlaceType(NearbyPlaceCategory $category): bool
    {
        return MapProvider::isOsm()
            ? filled($category->osm_place_type)
            : filled($category->google_place_type);
    }

    private function dispatchFetchJobsForCategory(NearbyPlaceCategory $category): void
    {
        if (! SystemMode::isMulti() || ! $this->categoryHasPlaceType($category)) {
            return;
        }

        $delay = 0;
        City::query()->each(function (City $city) use ($category, &$delay): void {
            FetchCityNearbyPlacesJob::dispatch($city, $category->id)
                ->delay(now()->addSeconds($delay));
            $delay += 20;
        });
    }

    public function render(): View
    {
        return view('livewire.nearby-place-category-table');
    }
}
