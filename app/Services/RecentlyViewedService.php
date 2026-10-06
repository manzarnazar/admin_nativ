<?php

namespace App\Services;

use App\Enums\HomepageSectionType;
use App\Models\PropertyView;
use App\Services\Api\PropertyService;

class RecentlyViewedService
{
    private const PROPERTIES_LIMIT = 10;

    public function __construct(private PropertyService $propertyService) {}

    /**
     * Record (or bump) a property view for the given user.
     */
    public function recordView(int $userId, int $propertyId): void
    {
        PropertyView::query()->updateOrCreate(
            ['user_id' => $userId, 'property_id' => $propertyId],
            ['viewed_at' => now()],
        );
    }

    /**
     * Build the synthetic "Recently Viewed" homepage section for the given user,
     * shaped like a real HomepageSection entry. Returns null when the user has
     * no qualifying view history, so callers can skip prepending it entirely.
     *
     * @return array<string, mixed>|null
     */
    public function getSection(int $userId): ?array
    {
        $days = (int) config('homepage.recently_viewed_days', 30);

        $views = PropertyView::query()
            ->where('user_id', $userId)
            ->where('viewed_at', '>=', now()->subDays($days))
            ->whereHas('property', fn ($q) => $q->publiclyVisible())
            ->with(['property' => fn ($q) => $q->with(['primaryImages', 'country', 'rooms', 'facilities.category', 'refState', 'refCity'])
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')])
            ->orderByDesc('viewed_at')
            ->limit(self::PROPERTIES_LIMIT)
            ->get();

        if ($views->isEmpty()) {
            return null;
        }

        $properties = $views->map(fn (PropertyView $view) => $this->propertyService->formatCompactProperty($view->property))->values()->toArray();

        return [
            'id' => null,
            'section_title' => 'Recently Viewed',
            'section_type' => HomepageSectionType::RecentlyViewed->value,
            'display_platform' => 'both',
            'web_display_order' => 0,
            'app_display_order' => 0,
            'sort_by_rule' => null,
            'target_city' => null,
            'property_types' => [],
            'properties_count' => count($properties),
            'properties' => $properties,
        ];
    }
}
