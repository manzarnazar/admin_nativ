<?php

namespace App\Services;

use App\Enums\DisplayPlatform;
use App\Enums\ReviewStatus;
use App\Enums\SortByRule;
use App\Models\Favorite;
use App\Models\HomepageSection;
use App\Models\Property;
use App\Models\PropertyType;
use App\Services\Api\PropertyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HomepageContentService
{
    public function __construct(
        private PropertyService $propertyService,
        private RecentlyViewedService $recentlyViewedService,
    ) {}

    /**
     * Resolve and return active homepage sections for the given country and platform.
     *
     * Country fallback order:
     *   1. Sections scoped to $countryId (if any active exist)
     *   2. Global sections (country_id = null)
     *
     * When $userId is given and has qualifying view history, a synthetic
     * "Recently Viewed" section is prepended first — it isn't an admin-managed
     * HomepageSection row, so it's built and inserted here rather than stored.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSections(?int $countryId, ?string $platform, int $limitPerSection = 10, ?int $userId = null): array
    {
        $userFavoriteIds = $userId ? Favorite::where('user_id', $userId)->pluck('property_id')->toArray() : [];

        $sections = $this->resolveSections($countryId, $platform)->map(function (HomepageSection $section) use ($limitPerSection, $userFavoriteIds) {
            $propertyTypeIds = $section->property_type_ids ?? [];

            return [
                'id' => $section->id,
                'section_title' => $section->section_title,
                'section_type' => $section->section_type->value,
                'display_platform' => $section->display_platform->value,
                'web_display_order' => $section->web_display_order,
                'app_display_order' => $section->app_display_order,
                'sort_by_rule' => $section->sort_by_rule->value,
                // The country/city a "Show All" for this section should filter
                // properties by — pass straight through to GET /properties as
                // country_id / city_slug. Use target_city.slug, never target_city.id
                // (that's the internal city record ID, not a valid filter value).
                'target_country_id' => $section->target_country_id ?? $section->country_id,
                'target_city' => $section->targetCity
                    ? [
                        'id' => $section->targetCity->id,
                        'name' => $section->targetCity->name,
                        'slug' => $section->targetCity->slug,
                    ]
                    : null,
                'property_types' => $propertyTypeIds
                    ? PropertyType::query()
                        ->whereIn('id', $propertyTypeIds)
                        ->get()
                        ->map(fn ($pt) => ['id' => $pt->id, 'name' => $pt->name])
                        ->toArray()
                    : [],
                'properties_count' => $this->buildSectionPropertyQuery($section)->count(),
                'properties' => $this->fetchPropertiesForSection($section, $limitPerSection, $userFavoriteIds),
            ];
        })->toArray();

        if ($userId && $recentlyViewed = $this->recentlyViewedService->getSection($userId)) {
            array_unshift($sections, $recentlyViewed);
        }

        return $sections;
    }

    private function resolveSections(?int $countryId, ?string $platform): Collection
    {
        $orderColumn = $platform === 'app' ? 'app_display_order' : 'web_display_order';

        $buildQuery = fn (?int $scopeId) => HomepageSection::query()
            ->with('targetCity')
            ->where('is_active', true)
            ->when(
                $platform === 'web',
                fn ($q) => $q->whereIn('display_platform', [DisplayPlatform::Web->value, DisplayPlatform::Both->value])
            )
            ->when(
                $platform === 'app',
                fn ($q) => $q->whereIn('display_platform', [DisplayPlatform::App->value, DisplayPlatform::Both->value])
            )
            ->when(
                $scopeId !== null,
                fn ($q) => $q->where('country_id', $scopeId),
                fn ($q) => $q->whereNull('country_id')
            )
            ->orderBy($orderColumn);

        if ($countryId) {
            $countrySections = $buildQuery($countryId)->get();

            if ($countrySections->isNotEmpty()) {
                return $countrySections;
            }
        }

        return $buildQuery(null)->get();
    }

    /**
     * Return paginated properties for a single section (used by the "Show All" endpoint).
     *
     * @return array{total: int, limit: int, offset: int, properties: array<int, array<string, mixed>>}
     */
    public function getSectionProperties(HomepageSection $section, int $limit, int $offset, ?int $userId = null): array
    {
        $query = $this->buildSectionPropertyQuery($section);

        $total = (clone $query)->count();
        $userFavoriteIds = $userId ? Favorite::where('user_id', $userId)->pluck('property_id')->toArray() : [];

        $properties = $query
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn (Property $property) => $this->propertyService->formatCompactProperty($property, in_array($property->id, $userFavoriteIds)))
            ->toArray();

        return [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'properties' => $properties,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPropertiesForSection(HomepageSection $section, int $limit, array $userFavoriteIds = []): array
    {
        return $this->buildSectionPropertyQuery($section)
            ->take($limit)
            ->get()
            ->map(fn (Property $property) => $this->propertyService->formatCompactProperty($property, in_array($property->id, $userFavoriteIds)))
            ->toArray();
    }

    private function buildSectionPropertyQuery(HomepageSection $section): Builder
    {
        $countryId = $section->target_country_id ?? $section->country_id;

        $query = Property::query()
            ->publiclyVisible()
            ->with(['primaryImages', 'country', 'rooms', 'facilities.category', 'refState', 'refCity'])
            ->withAvg(['reviews' => fn ($q) => $q->where('status', ReviewStatus::Published)->where('is_visible', true)], 'rating')
            ->withCount(['reviews' => fn ($q) => $q->where('status', ReviewStatus::Published)->where('is_visible', true)])
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId));

        if ($section->target_city_id) {
            $refCityId = $section->targetCity?->ref_city_id;
            if ($refCityId) {
                $query->where('ref_city_id', $refCityId);
            }
        }

        if (! empty($section->property_type_ids)) {
            $query->whereIn('property_type_id', $section->property_type_ids);
        }

        match ($section->sort_by_rule) {
            // Same Bayesian formula property.index's sort_by=highly_rated uses, so a
            // property's rank is never different between a section's teaser and the
            // "Show All" listing.
            SortByRule::HighestRating => $this->propertyService->applyHighlyRatedSort($query)->orderByDesc('id'),
            // A section is always scoped to a single country, so unlike /api/properties
            // (which can span currencies) a plain price comparison here needs no
            // currency-factor conversion — every property already shares one currency.
            SortByRule::PriceLowToHigh => $query->withMin('rooms', 'base_price_per_night')->orderBy('rooms_min_base_price_per_night'),
            SortByRule::PriceHighToLow => $query->withMin('rooms', 'base_price_per_night')->orderByDesc('rooms_min_base_price_per_night'),
            default => $query->latest('id'),
        };

        return $query;
    }
}
