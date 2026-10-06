<?php

namespace App\Services\Api;

use App\Models\Favorite;
use App\Models\Property;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class FavoriteService
{
    public function __construct(private PropertyService $propertyService) {}

    /**
     * Add a property to the user's favorites. Idempotent — re-adding an
     * already-favorited property is a no-op, not an error.
     */
    public function add(int $userId, string $slug): void
    {
        $property = Property::query()->publiclyVisible()->where('slug', $slug)->first();

        if (! $property) {
            throw ValidationException::withMessages([
                'slug' => 'Property not found.',
            ]);
        }

        Favorite::query()->firstOrCreate([
            'user_id' => $userId,
            'property_id' => $property->id,
        ]);
    }

    /**
     * Remove a property from the user's favorites. Deliberately permissive —
     * no visibility check, and an unknown slug is a silent no-op rather than
     * an error, so a stale entry for a since-deactivated property can always
     * be removed.
     */
    public function remove(int $userId, string $slug): void
    {
        $propertyId = Property::query()->where('slug', $slug)->value('id');

        if (! $propertyId) {
            return;
        }

        Favorite::query()
            ->where('user_id', $userId)
            ->where('property_id', $propertyId)
            ->delete();
    }

    /**
     * Paginated list of the user's favorited properties, most recently added first.
     * Only currently publicly visible properties are included.
     *
     * @return array{paginator: LengthAwarePaginator, items: array<int, array<string, mixed>>}
     */
    public function list(int $userId, int $limit, int $offset): array
    {
        $page = (int) floor($offset / $limit) + 1;

        $paginator = Favorite::query()
            ->where('user_id', $userId)
            ->whereHas('property', fn ($q) => $q->publiclyVisible())
            ->with(['property' => fn ($q) => $q->with(['primaryImages', 'country', 'rooms', 'facilities.category', 'refState', 'refCity'])
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')])
            ->latest('id')
            ->paginate(perPage: $limit, page: $page);

        $items = $paginator->getCollection()
            ->map(fn (Favorite $favorite) => $this->propertyService->formatCompactProperty($favorite->property, true))
            ->toArray();

        return [
            'paginator' => $paginator,
            'items' => $items,
        ];
    }
}
