<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Facility;
use App\Models\FacilityCategory;
use App\Services\FacilityCategoryService;
use App\Services\FacilityService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class FacilityManage extends Page implements DeclaresTopbarControls
{
    use HasPagePermission;

    protected static ?string $slug = 'facilities';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.facilities_amenities');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.facilities_amenities');
    }

    protected string $view = 'filament.pages.facility-manage';

    public static function getNavigationSort(): ?int
    {
        return SystemMode::isMulti() ? 5 : 3;
    }

    public string $search = '';

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::PropertyManagement);
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.facilities_amenities');
    }

    public function getSubheading(): ?string
    {
        return __('admin.manage_property_facilities_and_amenities');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    // ──────────────────────────────────────────────
    // Header Actions
    // ──────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addCategory')
                ->label(__('admin.add_facility_category'))
                ->disabled(static::disabledUnlessCanCreate())
                ->modalHeading(__('admin.add_new_facility_category'))
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->modalWidth('lg')
                ->modalSubmitActionLabel(__('admin.create_category'))
                ->modalFooterActionsAlignment(Alignment::End)

                ->schema($this->getCategoryFormSchema())
                ->action(function (array $data): void {
                    app(FacilityCategoryService::class)->createCategory($data);

                    Notification::make()
                        ->title(__('admin.category_created_successfully'))
                        ->success()
                        ->send();
                }),
        ];
    }

    // ──────────────────────────────────────────────
    // Category Actions
    // ──────────────────────────────────────────────

    public function editCategoryAction(): Action
    {
        return Action::make('editCategory')
            ->label(__('admin.edit_facility_category'))
            ->iconButton()
            ->icon('phosphor-pencil-simple-line')
            ->color('gray')
            ->disabled(static::disabledUnlessCanEdit())
            ->modalHeading(__('admin.edit_facility_category'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.save_changes'))
            ->modalFooterActionsAlignment(Alignment::End)

            ->schema($this->getCategoryFormSchema())
            ->fillForm(function (array $arguments): array {
                $category = FacilityCategory::find($arguments['category']);

                return [
                    'name' => $category?->name,
                    'icon' => $category?->icon,
                    'status' => $category?->status->value,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $category = FacilityCategory::find($arguments['category']);

                if (! $category) {
                    return;
                }

                app(FacilityCategoryService::class)->updateCategory($category, $data);

                Notification::make()
                    ->title(__('admin.category_updated_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function deleteCategoryAction(): Action
    {
        return Action::make('deleteCategory')
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->label(__('admin.delete_category'))
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('danger')
            ->before(static::enforceDeletePermission())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalHeading(__('admin.delete_category'))
            ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_category_this_action_cannot_be_undone'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))

            ->action(function (array $arguments): void {
                $category = FacilityCategory::find($arguments['category']);

                if (! $category) {
                    return;
                }

                try {
                    app(FacilityCategoryService::class)->deleteCategory($category);

                    Notification::make()
                        ->title(__('admin.category_deleted_successfully'))
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title(__('admin.cannot_delete_category'))
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    // ──────────────────────────────────────────────
    // Facility Actions
    // ──────────────────────────────────────────────

    public function addFacilityAction(): Action
    {
        return Action::make('addFacility')
            ->label(__('admin.add_amenity'))
            ->color('dark')
            ->disabled(static::disabledUnlessCanCreate())
            ->modalHeading(function (array $arguments): string {
                $category = FacilityCategory::find($arguments['category']);

                return __('admin.add_new_facility_in', ['category' => $category?->name ?? 'Unknown']);
            })
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.create_facility'))
            ->modalSubmitAction(fn (Action $action) => $action->color('primary'))
            ->modalFooterActionsAlignment(Alignment::End)

            ->schema($this->getFacilityFormSchema())
            ->action(function (array $data, array $arguments): void {
                $categoryId = $arguments['category'] ?? null;

                if (! $categoryId) {
                    return;
                }

                app(FacilityService::class)->createFacility($categoryId, $data);

                Notification::make()
                    ->title(__('admin.facility_created_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function editFacilityAction(): Action
    {
        return Action::make('editFacility')
            ->label(__('admin.editfacility'))
            ->iconButton()
            ->icon('phosphor-pencil-simple-line')
            ->color('gray')
            ->disabled(static::disabledUnlessCanEdit())
            ->modalHeading(function (array $arguments): string {
                $facility = Facility::with('category')->find($arguments['facility']);

                return __('admin.edit_facility_in', ['category' => $facility?->category?->name ?? 'Unknown']);
            })
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.save_changes'))
            ->modalFooterActionsAlignment(Alignment::End)

            ->schema($this->getFacilityFormSchema())
            ->fillForm(function (array $arguments): array {
                $facility = Facility::find($arguments['facility']);

                return [
                    'name' => $facility?->name,
                    'icon' => $facility?->icon,
                    'status' => $facility?->status->value,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $facility = Facility::find($arguments['facility']);

                if (! $facility) {
                    return;
                }

                app(FacilityService::class)->updateFacility($facility, $data);

                Notification::make()
                    ->title(__('admin.facility_updated_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function deleteFacilityAction(): Action
    {
        return Action::make('deleteFacility')
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->label(__('admin.deletefacility'))
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('danger')
            ->before(static::enforceDeletePermission())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalHeading(__('admin.delete_amenities_facilities'))
            ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_facility_or_amenity_deleting_this_facility_or_amenity_will_remove_it_from_all_associated_properties_permanently'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))

            ->action(function (array $arguments): void {
                $facility = Facility::find($arguments['facility']);

                if (! $facility) {
                    return;
                }

                app(FacilityService::class)->deleteFacility($facility);

                Notification::make()
                    ->title(__('admin.facility_deleted_successfully'))
                    ->success()
                    ->send();
            });
    }

    // ──────────────────────────────────────────────
    // Data Methods
    // ──────────────────────────────────────────────

    public function getCategories(): Collection
    {
        $search = trim($this->search);

        return FacilityCategory::query()
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('facilities', fn ($fq) => $fq->where('name', 'like', "%{$search}%"));
                });
            })
            ->withCount(['facilities' => function ($query) use ($search): void {
                if ($search) {
                    $query->where('name', 'like', "%{$search}%");
                }
            }])
            ->with(['facilities' => function ($query) use ($search): void {
                if ($search) {
                    $query->where('name', 'like', "%{$search}%");
                }
                $query->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();
    }

    public function getHasCategories(): bool
    {
        return FacilityCategory::query()->exists();
    }

    // ──────────────────────────────────────────────
    // Form Schemas
    // ──────────────────────────────────────────────

    /**
     * @return array<int, Component>
     */
    private function getCategoryFormSchema(): array
    {
        return [
            FileUpload::make('icon')
                ->label(__('admin.facility_category_icon'))
                ->disk('public')
                ->directory('facility-categories')
                ->visibility('public')
                ->maxSize(5120)
                ->acceptedFileTypes(['image/png', 'image/svg+xml'])
                ->helperText(__('admin.maximum_size_5mb_supported_files_pngsvg'))
                ->placeholder(new HtmlString(__('admin.drag_drop_browse')))
                ->columnSpanFull(),
            TextInput::make('name')
                ->label(__('admin.category_name'))
                ->placeholder(__('admin.enter_category_name'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Radio::make('status')
                ->label(__('admin.status'))
                ->options([
                    'active' => __('admin.active'),
                    'inactive' => __('admin.inactive'),
                ])
                ->default('active')
                ->required()
                ->inline()
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private function getFacilityFormSchema(): array
    {
        return [
            FileUpload::make('icon')
                ->label(__('admin.facility_icon'))
                ->disk('public')
                ->directory('facilities')
                ->visibility('public')
                ->maxSize(5120)
                ->acceptedFileTypes(['image/png', 'image/svg+xml'])
                ->helperText(__('admin.maximum_size_5mb_supported_files_pngsvg'))
                ->placeholder(new HtmlString(__('admin.drag_drop_browse')))
                ->columnSpanFull(),
            TextInput::make('name')
                ->label(__('admin.facility_name'))
                ->placeholder(__('admin.enter_facility_name'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Radio::make('status')
                ->label(__('admin.status'))
                ->options([
                    'active' => __('admin.active'),
                    'inactive' => __('admin.inactive'),
                ])
                ->default('active')
                ->required()
                ->inline()
                ->columnSpanFull(),
        ];
    }
}
