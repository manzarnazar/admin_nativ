<?php

namespace App\Filament\Partner\Pages;

use App\Enums\BookingStatus;
use App\Enums\RegistrationFieldType;
use App\Enums\ReviewStatus;
use App\Enums\WithdrawalStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Concerns\ResolvesBackUrl;
use App\Filament\Concerns\ResolvesRuleAnswerLabels;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Livewire\PropertyPendingSettlementsTable;
use App\Livewire\PropertyWalletTransactionsTable;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\FacilityCategory;
use App\Models\NearbyPlace;
use App\Models\NearbyPlaceCategory;
use App\Models\Partner;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyRegistrationValue;
use App\Models\PropertyRoom;
use App\Models\PropertyRule;
use App\Models\PropertyWallet;
use App\Models\User;
use App\Services\CancellationPolicyService;
use App\Services\CommissionService;
use App\Services\ExportService;
use App\Support\DemoMode;
use App\Support\Geo;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Spatie\Activitylog\Models\Activity;

class PartnerPropertyView extends Page implements DeclaresTopbarControls, HasTable
{
    use InteractsWithTable;
    use RequiresApprovedPartner;
    use ResolvesBackUrl;
    use ResolvesRuleAnswerLabels;

    protected static ?string $slug = 'properties/{record}/view';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.partner.pages.property-view';

    public ?int $propertyId = null;

    public ?string $backUrl = null;

    #[Url(as: 'tab')]
    public string $activeTab = 'overview';

    /**
     * Media tab type filter: all|photos|videos.
     */
    public string $mediaFilter = 'all';

    /**
     * Wallet tab sub-view: transactions|pending_settlement.
     */
    #[Url(as: 'wallet_tab')]
    public string $walletSubTab = 'transactions';

    /**
     * Wallet Transactions date filter. Lives here (not on the embedded
     * PropertyWalletTransactionsTable) so the Filter row can sit above the
     * stat cards per Figma — the child is re-mounted with fresh values via
     * a key() that includes them (see tab-wallet.blade.php).
     */
    public string $walletDatePreset = 'all_time';

    public ?string $walletCustomDate = null;

    /**
     * Analytics tab date filter. Lives here so the filter bar can sit above
     * the stat cards, same pattern as the wallet tab.
     */
    public string $analyticsDatePreset = 'this_week';

    public string $revenueChartPreset = 'this_year';

    public string $bookingStatusPreset = 'this_year';

    public array $revenueChartData = [];

    public array $bookingStatusData = [];

    public ?string $analyticsCustomDate = null;

    /**
     * Logs tab single-date filter (exact-day match), matching the pattern
     * already established on AllPartnersDetail's audit_logs tab.
     */
    public ?string $auditLogDate = null;

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    private function getPartner(): ?Partner
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->partner;
    }

    public function mount(int $record): void
    {
        $property = Property::query()->findOrFail($record);
        $partner = $this->getPartner();

        // Ownership check — a partner must never be able to view another
        // partner's property by guessing IDs in the URL.
        if (! $partner || $property->partner_id !== $partner->id) {
            Notification::make()
                ->title(__('admin.property_access_denied'))
                ->warning()
                ->send();

            $this->redirect(PartnerPropertiesManage::getUrl());

            return;
        }

        $this->propertyId = $property->id;
        $this->backUrl = $this->resolveBackUrl(PartnerPropertiesManage::getUrl());
        $this->revenueChartData = $this->getRevenueChartData();
        $this->bookingStatusData = $this->getBookingStatusData();
    }

    public function updatedRevenueChartPreset(): void
    {
        $this->revenueChartData = $this->getRevenueChartData();
    }

    public function updatedBookingStatusPreset(): void
    {
        $this->bookingStatusData = $this->getBookingStatusData();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getProperty()->name;
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getProperty(): Property
    {
        return Property::query()
            ->with(['propertyType', 'country', 'refCity', 'refState', 'primaryImages', 'verifiedBy'])
            ->findOrFail($this->propertyId);
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    public function getTabs(): array
    {
        return [
            'overview' => ['label' => __('admin.overview'), 'icon' => 'partner.question'],
            'rooms_pricing' => ['label' => __('admin.rooms_and_pricing'), 'icon' => 'others.bed'],
            'facilities' => ['label' => __('admin.facilities'), 'icon' => 'others.swimmingpool'],
            'property_rules' => ['label' => __('admin.property_rules'), 'icon' => 'others.notepad'],
            'location_nearby' => ['label' => __('admin.location_and_nearby'), 'icon' => 'others.mappinarea'],
            'media' => ['label' => __('admin.media'), 'icon' => 'others.image'],
            'documentations' => ['label' => __('admin.documentations'), 'icon' => 'others.files'],
            'wallet' => ['label' => __('admin.wallet'), 'icon' => 'others.wallet'],
            'analytics' => ['label' => __('admin.analytics'), 'icon' => 'others.chartline'],
            'logs' => ['label' => __('admin.logs'), 'icon' => 'others.notebook'],
        ];
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function switchWalletTab(string $tab): void
    {
        $this->walletSubTab = $tab;
    }

    public function updatedAnalyticsDatePreset(): void
    {
        $this->analyticsCustomDate = null;
    }

    public function setAuditLogDate(string $date): void
    {
        $this->auditLogDate = $date ?: null;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getAuditLogs(): Collection
    {
        $query = Activity::query()
            ->where('subject_type', Property::class)
            ->where('subject_id', $this->propertyId)
            ->with('causer')
            ->latest();

        if ($this->auditLogDate) {
            $query->whereDate('created_at', $this->auditLogDate);
        }

        return $query->limit(50)->get();
    }

    public function getReviewsAvgRating(): ?float
    {
        $rating = $this->getProperty()->reviews()
            ->where('status', ReviewStatus::Published)
            ->avg('rating');

        return $rating !== null ? (float) $rating : null;
    }

    public function getReviewsCount(): int
    {
        return $this->getProperty()->reviews()
            ->where('status', ReviewStatus::Published)
            ->count();
    }

    public function table(Table $table): Table
    {
        $currency = $this->getCurrencySymbol();
        $propertyId = $this->propertyId;

        return $table
            ->query(
                PropertyRoom::query()
                    ->where('property_id', $propertyId)
                    ->with('roomType.images')
                    ->withAvg(['reviews as reviews_avg_rating' => fn (Builder $q) => $q->where('status', ReviewStatus::Published)], 'rating')
                    ->withCount(['reviews as reviews_count' => fn (Builder $q) => $q->where('status', ReviewStatus::Published)])
            )
            ->columns([
                TextColumn::make('roomType.name')
                    ->label(__('admin.room_types'))
                    ->html()
                    ->searchable(['id'])
                    ->state(function (PropertyRoom $record): string {
                        $image = $record->roomType?->images->first();
                        $imageUrl = $image ? asset('storage/'.$image->image_path) : null;
                        $imageHtml = $imageUrl
                            ? '<img src="'.e($imageUrl).'" class="h-[60px] w-[72px] flex-shrink-0 rounded object-cover" alt="">'
                            : '<div class="h-[60px] w-[72px] flex-shrink-0 rounded bg-[var(--brand-primary-light)] dark:bg-blue-900/20"></div>';

                        return '<div class="flex items-center gap-4">'.$imageHtml.'<span class="font-semibold text-gray-950 dark:text-white">'.e($record->roomType?->name ?? '-').'</span></div>';
                    }),

                TextColumn::make('specification')
                    ->label(__('admin.specification'))
                    ->html()
                    ->state(function (PropertyRoom $record): Htmlable {
                        return new HtmlString(
                            '<div class="flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-400">'
                                .'<div class="flex items-center gap-1"><span class="h-4 w-4 inline-flex items-center">'.$this->getSpecIcon('users').'</span> '.__('admin.max_guests').' : <span class="font-medium text-gray-950 dark:text-white">'.e($record->roomType?->max_guests ?? '-').' '.e(str('adult')->plural($record->roomType?->max_guests ?? 1)).'</span></div>'
                                .'<div class="flex items-center gap-1"><span class="h-4 w-4 inline-flex items-center">'.$this->getSpecIcon('bed').'</span> '.__('admin.bed_type').' : <span class="font-medium text-gray-950 dark:text-white">'.e($record->roomType?->bed_type ?? '-').'</span></div>'
                                .'<div class="flex items-center gap-1"><span class="h-4 w-4 inline-flex items-center">'.$this->getSpecIcon('resize').'</span> '.__('admin.room_size').' : <span class="font-medium text-gray-950 dark:text-white">'.e($record->room_size ?? '-').'</span></div>'
                                .'</div>'
                        );
                    }),

                TextColumn::make('total_rooms')
                    ->label(__('admin.total_rooms'))
                    ->formatStateUsing(fn (PropertyRoom $record): string => $record->total_rooms.' '.Str::upper(Str::plural(__('admin.room'), $record->total_rooms)))
                    ->color('danger')
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('reviews_avg_rating')
                    ->label(__('admin.reviews'))
                    ->html()
                    ->state(function (PropertyRoom $record): string {
                        $rating = $record->reviews_count > 0 ? number_format((float) $record->reviews_avg_rating, 1) : '-';

                        return '<div class="flex items-center gap-1"><span class="text-yellow-400">★</span><span class="font-semibold text-gray-950 dark:text-white">'.$rating.'</span></div>'
                            .'<p class="text-xs text-gray-500 dark:text-gray-400">('.$record->reviews_count.' '.__('admin.reviews').')</p>';
                    }),

                TextColumn::make('base_price_per_night')
                    ->label(__('admin.base_price'))
                    ->formatStateUsing(fn (PropertyRoom $record): string => $currency.number_format((float) $record->base_price_per_night, 2).' / '.__('admin.night')),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('admin.room_details'))
                    ->icon('phosphor-eye')
                    ->color('primary')
                    ->link()
                    ->url(fn (PropertyRoom $record): string => PartnerRoomTypeView::getUrl(['record' => $record->room_type_id])),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('rooms')
                    ->exports([
                        'roomType.name' => __('admin.room_types'),
                        'roomType.max_guests' => __('admin.max_guests'),
                        'roomType.bed_type' => __('admin.bed_type'),
                        'room_size' => __('admin.room_size'),
                        'total_rooms' => __('admin.total_rooms'),
                        'base_price_per_night' => __('admin.base_price'),
                    ])
                    ->toActionGroup(),
            ])
            ->searchPlaceholder(__('admin.search_rooms_by_name_or_id'))
            ->defaultPaginationPageOption(10);
    }

    /**
     * @return array{room_types: int, total_rooms: int}
     */
    public function getRoomStats(): array
    {
        $rooms = PropertyRoom::query()->where('property_id', $this->propertyId)->get();

        return [
            'room_types' => $rooms->count(),
            'total_rooms' => (int) $rooms->sum('total_rooms'),
        ];
    }

    /**
     * Inline SVG for the Rooms & Pricing table's Specification column,
     * read once per request rather than per row. The source files have
     * varying native width/height (14-20px) — normalized to a fixed 16px
     * here rather than relying on a parent flex container to shrink a
     * replaced element, which renders inconsistently across browsers.
     */
    private function getSpecIcon(string $name): string
    {
        static $cache = [];

        if (isset($cache[$name])) {
            return $cache[$name];
        }

        $svg = file_get_contents(resource_path('svg/others/'.$name.'.svg'));
        $svg = preg_replace('/width="[\d.]+"/', 'width="16"', $svg, 1);
        $svg = preg_replace('/height="[\d.]+"/', 'height="16"', $svg, 1);

        return $cache[$name] = $svg;
    }

    /**
     * Tab bar icons (e.g. "others.wallet", "partner.question") have a
     * baked-in hex fill rather than currentColor, so the active/inactive
     * button text-color classes can't recolor them via CSS alone — rewrite
     * the fill so they inherit the button's own color like a normal icon.
     */
    public function getTabIconHtml(string $name): string
    {
        static $cache = [];

        if (isset($cache[$name])) {
            return $cache[$name];
        }

        $svg = file_get_contents(resource_path('svg/'.str_replace('.', '/', $name).'.svg'));
        $svg = preg_replace('/fill="#[0-9A-Fa-f]{3,8}"/', 'fill="currentColor"', $svg);
        $svg = preg_replace('/width="[\d.]+"/', 'width="20"', $svg, 1);
        $svg = preg_replace('/height="[\d.]+"/', 'height="20"', $svg, 1);

        return $cache[$name] = $svg;
    }

    /**
     * Property's active facilities grouped by category, each group carrying
     * its FacilityCategory (for name/icon) alongside its sorted facilities.
     * Icon rendering mirrors FacilityManage.php exactly.
     *
     * @return \Illuminate\Support\Collection<int, array{category: ?FacilityCategory, facilities: Collection<int, Facility>}>
     */
    public function getGroupedFacilities(): \Illuminate\Support\Collection
    {
        return $this->getProperty()->facilities()
            ->with('category')
            ->get()
            ->groupBy('facility_category_id')
            ->map(fn (Collection $facilities): array => [
                'category' => $facilities->first()->category,
                'facilities' => $facilities->sortBy('sort_order')->values(),
            ])
            ->sortBy(fn (array $group): int => $group['category']?->sort_order ?? 0)
            ->values();
    }

    public function getCancellationPolicy(): ?CancellationPolicy
    {
        return app(CancellationPolicyService::class)->getPolicyForProperty($this->getProperty());
    }

    /**
     * Dynamic PropertyRule categories admin configured at /property-rules,
     * paired with this property's PropertyRuleAnswer values. Whatever
     * categories exist show up here automatically — nothing hardcoded.
     *
     * @return array<int, array{name: string, icon: ?string, questions: array<int, array{question: string, answer_type: string, answer: mixed}>}>
     */
    public function getPropertyRuleAnswers(): array
    {
        $property = $this->getProperty();
        $answers = $property->ruleAnswers;

        $rules = PropertyRule::query()
            ->where('status', 'active')
            ->applicableTo($property->country_id, $property->property_type_id)
            ->with(['questions' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        $grouped = [];

        foreach ($rules as $rule) {
            $ruleData = [
                'name' => $rule->name,
                'description' => $rule->description,
                'icon' => $rule->icon,
                'questions' => [],
            ];

            foreach ($rule->questions as $question) {
                $answer = $answers->firstWhere('property_rule_question_id', $question->id);
                $ruleData['questions'][] = [
                    'question' => $question->question_text,
                    'answer_type' => $question->answer_type->value,
                    'answer' => $this->resolveRuleAnswerLabel($question, $answer?->answer_value),
                ];
            }

            if (count($ruleData['questions']) > 0) {
                $grouped[] = $ruleData;
            }
        }

        return $grouped;
    }

    public function getNearbyPlaces(): Collection
    {
        $property = $this->getProperty();

        if (! $property->ref_city_id) {
            return new Collection;
        }

        $city = City::query()
            ->where('ref_city_id', $property->ref_city_id)
            ->first();

        if (! $city) {
            return new Collection;
        }

        $query = NearbyPlace::query()
            ->where('city_id', $city->id)
            ->with('nearbyPlaceCategory');

        // Distance is specific to this property's exact coordinates, not the
        // city as a whole, so it's computed here rather than stored on the row.
        if ($property->latitude && $property->longitude) {
            $query->select('nearby_places.*')
                ->addSelect(Geo::haversineExpression((float) $property->latitude, (float) $property->longitude))
                ->orderBy('distance_km');
        } else {
            $query->orderBy('name');
        }

        return $query->get();
    }

    /**
     * Nearby places grouped by category, categories ordered by their own
     * sort_order; places within each category keep the distance ordering
     * already applied by getNearbyPlaces().
     *
     * @return \Illuminate\Support\Collection<int, array{category: ?NearbyPlaceCategory, places: Collection<int, NearbyPlace>}>
     */
    public function getGroupedNearbyPlaces(): \Illuminate\Support\Collection
    {
        return $this->getNearbyPlaces()
            ->groupBy('nearby_place_category_id')
            ->map(fn (Collection $places): array => [
                'category' => $places->first()->nearbyPlaceCategory,
                'places' => $places,
            ])
            ->sortBy(fn (array $group): int => $group['category']?->sort_order ?? 0)
            ->values();
    }

    /**
     * Gallery images grouped by group_name, filtered by the current
     * mediaFilter (all|photos|videos) selection.
     *
     * @return array<int, array{name: string, count: int, images: Collection<int, PropertyImage>}>
     */
    public function getGalleryGroups(): array
    {
        $images = $this->getProperty()->galleryImages;

        if ($this->mediaFilter === 'photos') {
            $images = $images->where('media_type', 'image');
        } elseif ($this->mediaFilter === 'videos') {
            $images = $images->where('media_type', 'video');
        }

        return $images
            ->groupBy('group_name')
            ->map(fn (Collection $group, string $name): array => [
                'name' => $name,
                'count' => $group->count(),
                'images' => $group->values(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * The mandatory showcase photo(s)/video captured during the property
     * creation wizard, filtered by the current mediaFilter selection.
     *
     * @return Collection<int, PropertyImage>
     */
    public function getPrimaryImages(): Collection
    {
        $images = $this->getProperty()->primaryImages;

        if ($this->mediaFilter === 'photos') {
            return $images->where('media_type', 'image')->values();
        }

        if ($this->mediaFilter === 'videos') {
            return $images->where('media_type', 'video')->values();
        }

        return $images;
    }

    /**
     * Counts across ALL of the property's media — primary showcase and
     * gallery combined — so the filter dropdown reflects the full set.
     *
     * @return array{all: int, photos: int, videos: int}
     */
    public function getMediaCounts(): array
    {
        $images = $this->getProperty()->images;

        return [
            'all' => $images->count(),
            'photos' => $images->where('media_type', 'image')->count(),
            'videos' => $images->where('media_type', 'video')->count(),
        ];
    }

    public function getDocumentCount(): int
    {
        return PropertyRegistrationValue::query()
            ->where('property_id', $this->propertyId)
            ->whereHas('registrationField', fn (Builder $q) => $q->where('field_type', RegistrationFieldType::FileUpload))
            ->count();
    }

    /**
     * @return array{balance: string, pending_settlement: string, total_withdrawn: string}
     */
    public function getWalletStats(): array
    {
        $symbol = $this->getCurrencySymbol();
        $wallet = PropertyWallet::query()->where('property_id', $this->propertyId)->first();

        if (! $wallet) {
            return [
                'balance' => $symbol.'0.00',
                'pending_settlement' => $symbol.'0.00',
                'total_withdrawn' => $symbol.'0.00',
            ];
        }

        $pendingSettlement = Booking::query()
            ->where('property_id', $this->propertyId)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->whereNull('wallet_credited_at')
            ->get()
            ->sum(fn (Booking $booking): float => app(CommissionService::class)->calculateCheckInPartnerCredit($booking));

        $totalWithdrawn = $wallet->withdrawalRequests()->where('status', WithdrawalStatus::Approved)->sum('amount');

        return [
            'balance' => $symbol.number_format((float) $wallet->balance, 2),
            'pending_settlement' => $symbol.number_format($pendingSettlement, 2),
            'total_withdrawn' => $symbol.number_format((float) $totalWithdrawn, 2),
        ];
    }

    /**
     * Page-level "Exports" button for the Wallet tab, sitting above the
     * stat cards per Figma. Exports whichever sub-view is currently active
     * by calling the same static buildQuery()/exportColumns() the embedded
     * table component itself uses — a page-level button can't reach into
     * a child Livewire component's own mounted table.
     */
    /**
     * @return array<int, Action>
     */
    public function getWalletExportActions(): array
    {
        return [
            $this->walletExportCsvAction(),
            $this->walletExportXlsxAction(),
        ];
    }

    public function walletExportCsvAction(): Action
    {
        return $this->buildWalletExportAction('csv');
    }

    public function walletExportXlsxAction(): Action
    {
        return $this->buildWalletExportAction('xlsx');
    }

    /**
     * Named per-format so each is independently resolvable via Filament's
     * `{name}Action()` convention — resolveAction() only recognizes methods
     * whose return type is `Action`, so an ActionGroup-returning method
     * (wrapping both formats) is never actually mountable. The visual
     * dropdown grouping is done separately in the Blade view via
     * <x-filament-actions::group>, which only needs the Action objects
     * themselves, not a resolvable wrapper method.
     */
    private function buildWalletExportAction(string $format): Action
    {
        $label = $format === 'xlsx' ? __('admin.excel_xlsx') : __('admin.csv_csv');
        $icon = $format === 'xlsx' ? Heroicon::TableCells : Heroicon::DocumentText;

        return Action::make('walletExport'.ucfirst($format))
            ->label($label)
            ->icon($icon)
            ->hidden(fn (): bool => DemoMode::isActive())
            ->action(function () use ($format): void {
                if ($this->walletSubTab === 'transactions') {
                    $query = PropertyWalletTransactionsTable::buildQuery($this->propertyId, $this->walletDatePreset, $this->walletCustomDate);
                    $columns = PropertyWalletTransactionsTable::exportColumns();
                    $filename = 'wallet-transactions';
                } else {
                    $query = PropertyPendingSettlementsTable::buildQuery($this->propertyId);
                    $columns = PropertyPendingSettlementsTable::exportColumns();
                    $filename = 'pending-settlements';
                }

                $extension = $format === 'xlsx' ? 'xlsx' : 'csv';
                $file = "{$filename}.{$extension}";

                $path = app(ExportService::class)->exportToFile(
                    query: $query,
                    columns: $columns,
                    filename: $file,
                    format: $format,
                );

                $url = route('export.download', ['path' => encrypt($path)]);

                $this->js("window.open('{$url}', '_blank')");
            });
    }

    public function getCurrencySymbol(): string
    {
        return Country::query()->where('id', $this->getProperty()->country_id)->value('currency_symbol') ?? '$';
    }

    /**
     * Non-file registration field values (e.g. GST/tax IDs) for the Overview
     * tab's "Other Information" card. File uploads are shown in the
     * Documentations tab instead.
     *
     * @return Collection<int, PropertyRegistrationValue>
     */
    public function getOtherRegistrationValues(): Collection
    {
        return $this->getProperty()->registrationValues()
            ->with('registrationField')
            ->whereHas('registrationField', fn (Builder $q) => $q->where('field_type', '!=', RegistrationFieldType::FileUpload))
            ->get();
    }

    /**
     * @return array{start: Carbon, end: Carbon, prev_start: ?Carbon, prev_end: ?Carbon, group_by: string, day_label_format: ?string, comparison_label: ?string}
     */
    private function getAnalyticsDateRange(): array
    {
        $now = now();

        if ($this->analyticsCustomDate) {
            $date = Carbon::parse($this->analyticsCustomDate);

            return [
                'start' => $date->copy()->startOfDay(),
                'end' => $date->copy()->endOfDay(),
                'prev_start' => null,
                'prev_end' => null,
                'group_by' => 'hour',
                'day_label_format' => null,
                'comparison_label' => null,
            ];
        }

        return match ($this->analyticsDatePreset) {
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'prev_start' => $now->copy()->subDay()->startOfDay(),
                'prev_end' => $now->copy()->subDay()->endOfDay(),
                'group_by' => 'hour',
                'day_label_format' => null,
                'comparison_label' => __('admin.from_yesterday'),
            ],
            'this_month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
                'prev_start' => $now->copy()->subMonth()->startOfMonth(),
                'prev_end' => $now->copy()->subMonth()->endOfMonth(),
                'group_by' => 'day',
                'day_label_format' => 'j',
                'comparison_label' => __('admin.from_last_month'),
            ],
            'this_week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
                'prev_start' => $now->copy()->subWeek()->startOfWeek(),
                'prev_end' => $now->copy()->subWeek()->endOfWeek(),
                'group_by' => 'day',
                'day_label_format' => 'D',
                'comparison_label' => __('admin.from_last_week'),
            ],
            default => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
                'prev_start' => $now->copy()->subYear()->startOfYear(),
                'prev_end' => $now->copy()->subYear()->endOfYear(),
                'group_by' => 'month',
                'day_label_format' => 'M',
                'comparison_label' => __('admin.from_last_year'),
            ],
        };
    }

    /**
     * @return array{start: Carbon, end: Carbon, group_by: string, day_label_format?: string}
     */
    private function getChartDateRange(string $preset): array
    {
        $now = now();

        if ($preset === 'all_time') {
            $firstDate = Booking::query()->where('property_id', $this->propertyId)->min('created_at');
            $startYear = $firstDate ? Carbon::parse($firstDate)->year : $now->year;

            return [
                'start' => Carbon::create($startYear, 1, 1)->startOfYear(),
                'end' => $now->copy()->endOfYear(),
                'group_by' => 'year',
            ];
        }

        return match ($preset) {
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'group_by' => 'hour',
            ],
            'this_week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
                'group_by' => 'day',
                'day_label_format' => 'D',
            ],
            'this_month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
                'group_by' => 'day',
                'day_label_format' => 'j',
            ],
            default => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
                'group_by' => 'month',
            ],
        };
    }

    /**
     * @return array{comparison_label: ?string, total_bookings: array{value: string, change: ?float, subtitle: string}, gross_revenue: array{value: string, change: ?float, subtitle: string}, partner_revenue: array{value: string, change: ?float, subtitle: string}, platform_earnings: array{value: string, change: ?float, subtitle: string}}
     */
    public function getAnalyticsStats(): array
    {
        $range = $this->getAnalyticsDateRange();
        $symbol = $this->getCurrencySymbol();
        $activeStatuses = [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed];

        $currentQuery = Booking::query()
            ->where('property_id', $this->propertyId)
            ->whereIn('status', $activeStatuses)
            ->whereBetween('created_at', [$range['start'], $range['end']]);

        $currentBookings = (clone $currentQuery)->count();
        $currentGross = (float) (clone $currentQuery)->sum('total_amount');
        $currentPartner = (clone $currentQuery)->get(['id', 'base_amount', 'commission_amount', 'total_amount', 'payment_status'])
            ->sum(fn (Booking $booking): float => app(CommissionService::class)->calculateCheckInPartnerCredit($booking));
        $currentPlatform = (float) (clone $currentQuery)->sum('commission_amount');

        $prevBookings = null;
        $prevGross = null;
        $prevPartner = null;
        $prevPlatform = null;

        if ($range['prev_start'] && $range['prev_end']) {
            $prevQuery = Booking::query()
                ->where('property_id', $this->propertyId)
                ->whereIn('status', $activeStatuses)
                ->whereBetween('created_at', [$range['prev_start'], $range['prev_end']]);

            $prevBookings = (clone $prevQuery)->count();
            $prevGross = (float) (clone $prevQuery)->sum('total_amount');
            $prevPartner = (clone $prevQuery)->get(['id', 'base_amount', 'commission_amount', 'total_amount', 'payment_status'])
                ->sum(fn (Booking $booking): float => app(CommissionService::class)->calculateCheckInPartnerCredit($booking));
            $prevPlatform = (float) (clone $prevQuery)->sum('commission_amount');
        }

        $calcChange = function (int|float $current, int|float|null $prev): ?float {
            if ($prev === null) {
                return null;
            }

            if ($prev == 0) {
                return 0.0;
            }

            return round(($current - $prev) / $prev * 100, 1);
        };

        $money = fn (float $amount): string => $symbol.number_format($amount, 0);

        return [
            'comparison_label' => $range['comparison_label'],
            'total_bookings' => [
                'value' => number_format($currentBookings),
                'change' => $calcChange($currentBookings, $prevBookings),
                'subtitle' => __('admin.confirmed_reservations'),
            ],
            'gross_revenue' => [
                'value' => $money($currentGross),
                'change' => $calcChange($currentGross, $prevGross),
                'subtitle' => __('admin.total_before_commission'),
            ],
            'partner_revenue' => [
                'value' => $money($currentPartner),
                'change' => $calcChange($currentPartner, $prevPartner),
                'subtitle' => __('admin.after_platform_commission'),
            ],
            'platform_earnings' => [
                'value' => $money($currentPlatform),
                'change' => $calcChange($currentPlatform, $prevPlatform),
                'previous_value' => $money($prevPlatform),
                'subtitle' => __('admin.earned_after_commission'),
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, partner_data: list<float>, commission_data: list<float>}
     */
    public function getRevenueChartData(): array
    {
        $range = $this->getChartDateRange($this->revenueChartPreset);
        $activeStatuses = [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed];

        $bookings = Booking::query()
            ->where('property_id', $this->propertyId)
            ->whereIn('status', $activeStatuses)
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->get(['id', 'created_at', 'base_amount', 'commission_amount', 'total_amount', 'payment_status']);

        if ($range['group_by'] === 'hour') {
            $labels = array_map(fn (int $h): string => sprintf('%02d:00', $h), range(0, 23));
            $buckets = array_fill_keys(range(0, 23), ['partner' => 0.0, 'commission' => 0.0]);

            foreach ($bookings as $booking) {
                $h = Carbon::parse($booking->created_at)->hour;
                $buckets[$h]['partner'] += app(CommissionService::class)->calculateCheckInPartnerCredit($booking);
                $buckets[$h]['commission'] += (float) $booking->commission_amount;
            }
        } elseif ($range['group_by'] === 'month') {
            $months = [];
            $cursor = $range['start']->copy()->startOfMonth();

            while ($cursor->lte($range['end'])) {
                $months[] = $cursor->copy();
                $cursor->addMonth();
            }

            $labels = array_map(fn (Carbon $m): string => $m->format('M'), $months);
            $buckets = [];

            foreach ($months as $month) {
                $buckets[$month->format('Y-m')] = ['partner' => 0.0, 'commission' => 0.0];
            }

            foreach ($bookings as $booking) {
                $key = Carbon::parse($booking->created_at)->format('Y-m');

                if (isset($buckets[$key])) {
                    $buckets[$key]['partner'] += app(CommissionService::class)->calculateCheckInPartnerCredit($booking);
                    $buckets[$key]['commission'] += (float) $booking->commission_amount;
                }
            }
        } elseif ($range['group_by'] === 'year') {
            $startYear = (int) $range['start']->format('Y');
            $endYear = (int) $range['end']->format('Y');
            $labels = [];
            $buckets = [];

            for ($y = $startYear; $y <= $endYear; $y++) {
                $labels[] = (string) $y;
                $buckets[(string) $y] = ['partner' => 0.0, 'commission' => 0.0];
            }

            foreach ($bookings as $booking) {
                $year = Carbon::parse($booking->created_at)->format('Y');

                if (isset($buckets[$year])) {
                    $buckets[$year]['partner'] += app(CommissionService::class)->calculateCheckInPartnerCredit($booking);
                    $buckets[$year]['commission'] += (float) $booking->commission_amount;
                }
            }
        } else {
            $days = [];
            $cursor = $range['start']->copy();

            while ($cursor->lte($range['end'])) {
                $days[] = $cursor->copy();
                $cursor->addDay();
            }

            $labelFormat = $range['day_label_format'] ?? 'D';
            $labels = array_map(fn (Carbon $d): string => $d->format($labelFormat), $days);
            $buckets = [];

            foreach ($days as $day) {
                $buckets[$day->toDateString()] = ['partner' => 0.0, 'commission' => 0.0];
            }

            foreach ($bookings as $booking) {
                $date = Carbon::parse($booking->created_at)->toDateString();

                if (isset($buckets[$date])) {
                    $buckets[$date]['partner'] += app(CommissionService::class)->calculateCheckInPartnerCredit($booking);
                    $buckets[$date]['commission'] += (float) $booking->commission_amount;
                }
            }
        }

        return [
            'labels' => $labels,
            'partner_data' => array_map(fn (array $b): float => round($b['partner'], 2), array_values($buckets)),
            'commission_data' => array_map(fn (array $b): float => round($b['commission'], 2), array_values($buckets)),
            'partner_label' => __('admin.partner_earning'),
            'commission_label' => __('admin.platform_commission'),
        ];
    }

    /**
     * @return array{total: int, completed: int, cancelled: int, refunded: int, labels: array<string>}
     */
    public function getBookingStatusData(): array
    {
        $range = $this->getChartDateRange($this->bookingStatusPreset);

        $base = Booking::query()
            ->where('property_id', $this->propertyId)
            ->whereBetween('created_at', [$range['start'], $range['end']]);

        $completed = (clone $base)->where('status', BookingStatus::Completed)->count();
        $refunded = (clone $base)->where('status', BookingStatus::Cancelled)->whereNotNull('refund_inputs')->count();
        $cancelled = (clone $base)->where('status', BookingStatus::Cancelled)->whereNull('refund_inputs')->count();

        return [
            'total' => $completed + $cancelled + $refunded,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'refunded' => $refunded,
            'labels' => [__('admin.completed'), __('admin.cancelled'), __('admin.refunded')],
        ];
    }

    /**
     * @return array{total_pending: string, available_balance: string, total_withdrawn: string}
     */
    public function getPayoutData(): array
    {
        $symbol = $this->getCurrencySymbol();
        $wallet = PropertyWallet::query()->where('property_id', $this->propertyId)->first();

        $pendingSettlement = Booking::query()
            ->where('property_id', $this->propertyId)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->whereNull('wallet_credited_at')
            ->get()
            ->sum(fn (Booking $booking): float => app(CommissionService::class)->calculateCheckInPartnerCredit($booking));

        $totalWithdrawn = $wallet?->withdrawalRequests()->where('status', WithdrawalStatus::Approved)->sum('amount') ?? 0;

        return [
            'total_pending' => $symbol.number_format($pendingSettlement, 2),
            'available_balance' => $symbol.number_format((float) ($wallet?->balance ?? 0), 2),
            'total_withdrawn' => $symbol.number_format((float) $totalWithdrawn, 2),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public function getAnalyticsExportActions(): array
    {
        return [
            $this->analyticsExportCsvAction(),
            $this->analyticsExportXlsxAction(),
        ];
    }

    public function analyticsExportCsvAction(): Action
    {
        return $this->buildAnalyticsExportAction('csv');
    }

    public function analyticsExportXlsxAction(): Action
    {
        return $this->buildAnalyticsExportAction('xlsx');
    }

    private function buildAnalyticsExportAction(string $format): Action
    {
        $label = $format === 'xlsx' ? __('admin.excel_xlsx') : __('admin.csv_csv');
        $icon = $format === 'xlsx' ? Heroicon::TableCells : Heroicon::DocumentText;

        return Action::make('analyticsExport'.ucfirst($format))
            ->label($label)
            ->icon($icon)
            ->hidden(fn (): bool => DemoMode::isActive())
            ->action(function () use ($format): void {
                $range = $this->getAnalyticsDateRange();
                $activeStatuses = [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed];

                $query = Booking::query()
                    ->where('property_id', $this->propertyId)
                    ->whereIn('status', $activeStatuses)
                    ->whereBetween('created_at', [$range['start'], $range['end']]);

                $columns = [
                    'id' => __('admin.booking_id'),
                    'check_in' => __('admin.check_in'),
                    'check_out' => __('admin.check_out'),
                    'total_amount' => __('admin.gross_revenue'),
                    'commission_amount' => __('admin.platform_commission'),
                    'partner_revenue_col' => [
                        'label' => __('admin.partner_revenue'),
                        'formatter' => fn (Booking $b): float => app(CommissionService::class)->calculateCheckInPartnerCredit($b),
                    ],
                    'status_col' => [
                        'label' => __('admin.status'),
                        'formatter' => fn (Booking $b): string => $b->status->label(),
                    ],
                ];

                $extension = $format === 'xlsx' ? 'xlsx' : 'csv';
                $path = app(ExportService::class)->exportToFile(
                    query: $query,
                    columns: $columns,
                    filename: "analytics-bookings.{$extension}",
                    format: $format,
                );

                $url = route('export.download', ['path' => encrypt($path)]);
                $this->js("window.open('{$url}', '_blank')");
            });
    }
}
