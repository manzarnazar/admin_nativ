<?php

namespace App\Filament\Pages;

use App\Enums\FacilityStatus;
use App\Enums\ReviewStatus;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Facility;
use App\Models\HomepageAboutUs;
use App\Models\HomepageAmenity;
use App\Models\Review;
use App\Services\HomepageSectionService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class HomepageManage extends Page implements DeclaresTopbarControls
{
    use HasPagePermission;

    protected static ?string $slug = 'manage-homepage';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.manage_homepage');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.manage_homepage');
    }

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.homepage-manage';

    // ── About Us ────────────────────────────────────────────────────
    /** @var array<string, mixed>|null */
    public ?array $aboutUsData = [];

    // ── Amenities & Facilities ──────────────────────────────────────
    /** @var array<int> Selected facility IDs */
    public array $amenities_selected = [];

    /** @var array<int|string, string> Descriptions keyed by facility_id */
    public array $amenities_descriptions = [];

    // ── Guest Reviews ───────────────────────────────────────────────
    /** @var array<int> Selected review IDs */
    public array $reviews_selected = [];

    public string $reviewSearch = '';

    public ?int $reviewStarsFilter = null;

    // ────────────────────────────────────────────────────────────────

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::ContentManagement);
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.manage_homepage_sections');
    }

    public function getSubheading(): ?string
    {
        return __('admin.customize_your_property_homepage_sections_with_realtime_preview');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function mount(): void
    {
        // About Us
        $aboutUs = HomepageAboutUs::first();
        $this->aboutUsForm->fill([
            'title' => $aboutUs?->title,
            'description' => $aboutUs?->description,
            'button_text' => $aboutUs?->button_text,
            'contact_no' => $aboutUs?->contact_no,
            'image' => $aboutUs?->image,
        ]);

        // Amenities — filter out stale refs to deleted/inactive facilities
        $activeFacilityIds = Facility::query()->where('status', FacilityStatus::Active)->pluck('id')->toArray();
        $amenities = HomepageAmenity::orderBy('sort_order')->get()->filter(fn ($item) => in_array($item->facility_id, $activeFacilityIds));
        $this->amenities_selected = $amenities->pluck('facility_id')->toArray();
        $this->amenities_descriptions = $amenities->mapWithKeys(fn ($item) => [(int) $item->facility_id => $item->description ?? ''])->toArray();

        // Guest Reviews
        $featuredReviews = Review::where('is_featured', true)->orderBy('featured_order')->get();
        $this->reviews_selected = $featuredReviews->pluck('id')->toArray();
    }

    // ── About Us Form & Save ──────────────────────────────────────────
    public function aboutUsForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Form::make([
                    TextInput::make('title')
                        ->label(__('admin.section_title'))
                        ->required()
                        ->maxLength(255)
                        ->placeholder(__('admin.eg_search_your_stay')),
                    Textarea::make('description')
                        ->label(__('admin.section_description'))
                        ->required()
                        ->rows(4)
                        ->placeholder(__('admin.brief_description_of_this_property_type')),
                    Grid::make(2)->schema([
                        TextInput::make('button_text')
                            ->label(__('admin.button_text'))
                            ->required()
                            ->maxLength(100)
                            ->placeholder(__('admin.enter_button_text')),
                        TextInput::make('contact_no')
                            ->label(__('admin.contact_no'))
                            ->required()
                            ->tel()
                            ->maxLength(20)
                            ->placeholder(__('admin.enter_contact_number')),
                    ]),
                    FileUpload::make('image')
                        ->label(__('admin.section_image'))
                        ->disk('public')
                        ->directory('homepage')
                        ->acceptedFileTypes(['image/png', 'image/svg+xml'])
                        ->maxSize(2048)
                        ->helperText(__('admin.maximum_size_770px_width_and_600px_height').' | '.__('admin.supported_files_pngsvg')),
                ])->livewireSubmitHandler('saveAboutUs'),
            ])
            ->statePath('aboutUsData');
    }

    public function saveAboutUs(): void
    {
        if (! static::canEdit()) {
            Notification::make()->title(__('admin.no_permission_action'))->danger()->send();

            return;
        }

        $data = $this->aboutUsForm->getState();

        app(HomepageSectionService::class)->saveAboutUs($data);

        Notification::make()
            ->title(__('admin.about_us_section_saved_successfully'))
            ->success()
            ->send();
    }

    // ── Amenities Save ───────────────────────────────────────────────
    public function saveAmenities(): void
    {
        if (! static::canEdit()) {
            Notification::make()->title(__('admin.no_permission_action'))->danger()->send();

            return;
        }

        $this->validate([
            'amenities_selected' => ['required', 'array', 'min:1'],
            'amenities_descriptions.*' => ['nullable', 'string', 'max:120'],
        ]);

        app(HomepageSectionService::class)->saveAmenities(
            $this->amenities_selected,
            $this->amenities_descriptions,
        );

        Notification::make()
            ->title(__('admin.amenities_facilities_saved_successfully'))
            ->success()
            ->send();
    }

    // ── Guest Reviews Save ───────────────────────────────────────────
    public function saveReviews(): void
    {
        if (! static::canEdit()) {
            Notification::make()->title(__('admin.no_permission_action'))->danger()->send();

            return;
        }

        $this->validate([
            'reviews_selected' => ['nullable', 'array'],
            'reviews_selected.*' => ['integer', 'exists:reviews,id'],
        ]);

        app(HomepageSectionService::class)->saveReviews($this->reviews_selected);

        Notification::make()
            ->title(__('admin.guest_reviews_saved_successfully'))
            ->success()
            ->send();
    }

    public function getAvailableReviewsProperty(): EloquentCollection|Collection
    {
        return Review::query()
            ->with('user')
            ->where('status', ReviewStatus::Published->value)
            ->when($this->reviewSearch, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('review', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($this->reviewStarsFilter, function ($query, $stars) {
                if ($stars == 5) {
                    $query->where('rating', 5);
                } else {
                    $query->where('rating', '>=', $stars)
                        ->where('rating', '<', $stars + 1);
                }
            })
            ->latest()
            ->limit(20)
            ->get();
    }

    public function getSelectedReviewsModelsProperty(): EloquentCollection|Collection
    {
        if (empty($this->reviews_selected)) {
            return collect();
        }

        return Review::query()
            ->with('user')
            ->whereIn('id', $this->reviews_selected)
            ->get()
            ->sortBy(fn ($r) => array_search($r->id, $this->reviews_selected))
            ->values();
    }

    // ── View Data ────────────────────────────────────────────────────
    protected function getViewData(): array
    {
        return [
            'facilities' => Facility::query()
                ->with('category')
                ->where('status', FacilityStatus::Active)
                ->orderBy('sort_order')
                ->get(),
        ];
    }

    /**
     * Return facilities keyed by ID for easy lookup in the Blade view.
     *
     * @return Collection<int, Facility>
     */
    public function getFacilitiesMapProperty(): Collection
    {
        return $this->getViewData()['facilities']->keyBy('id');
    }
}
