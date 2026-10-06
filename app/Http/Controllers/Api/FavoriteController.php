<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\FavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function __construct(
        private FavoriteService $favoriteService,
    ) {}

    /**
     * Add a property to favorites.
     *
     * Idempotent — adding an already-favorited property is a no-op, not an error.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $this->favoriteService->add($request->user()->id, $slug);

        return $this->successResponse(null, 'Property added to favorites');
    }

    /**
     * Remove a property from favorites.
     *
     * Idempotent — removing a property that isn't favorited is not an error.
     */
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $this->favoriteService->remove($request->user()->id, $slug);

        return $this->successResponse(null, 'Property removed from favorites');
    }

    /**
     * List the current user's favorited properties.
     *
     * Only currently active/approved properties are included — a favorited
     * property that later became inactive drops out of this list (though it
     * can still be removed via DELETE /favorites/{slug}).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $limit = $request->integer('limit', 10);
        $offset = $request->integer('offset', 0);

        $result = $this->favoriteService->list($request->user()->id, $limit, $offset);

        return $this->paginatedResponse($result['paginator'], $result['items'], 'Favorites fetched successfully', [], $offset);
    }
}
