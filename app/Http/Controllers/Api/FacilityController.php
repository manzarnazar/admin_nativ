<?php

namespace App\Http\Controllers\Api;

use App\Enums\FacilityStatus;
use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacilityController extends Controller
{
    /**
     * List facilities.
     *
     * Returns active facilities that are used by at least one active property, with pagination support.
     *
     * **Query Parameters:**
     * - `limit` — results per page (default: 10, max: 50)
     * - `offset` — pagination offset (default: 0)
     *
     * **Response:**
     * Returns paginated facilities with pagination metadata including total count, current page, and whether more results are available.
     *
     * **Example Request:**
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/facilities?limit=10&offset=0" \
     *      -H "Accept: application/json"
     * ```
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);
        $page = (int) floor($offset / $limit) + 1;

        $paginator = Facility::query()
            ->where('status', FacilityStatus::Active)
            ->whereHas('properties', fn ($q) => $q->where('status', PropertyStatus::Active))
            ->with('category:id,name')
            ->orderBy('sort_order')
            ->paginate(perPage: $limit, page: $page);

        $items = $paginator->getCollection()->map(fn (Facility $facility) => [
            'id' => $facility->id,
            'name' => $facility->name,
            'icon' => $facility->getIconUrl(),
            'category' => $facility->category?->name,
        ])->toArray();

        return $this->successResponse([
            'items' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'limit' => $paginator->perPage(),
                'offset' => ($paginator->currentPage() - 1) * $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], 'Facilities fetched successfully');
    }
}
