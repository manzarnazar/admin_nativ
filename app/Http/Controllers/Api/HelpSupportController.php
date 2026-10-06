<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\HelpSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpSupportController extends Controller
{
    public function __construct(
        private HelpSupportService $helpSupportService,
    ) {}

    /**
     * Get Help & Support content.
     *
     * Returns "How It Works" steps and "Topics & FAQs" sections with paginated FAQs.
     *
     * **Query Parameters:**
     * - `type` — `help_support` (default) or `partner`. When `partner`, returns the
     *   Become-a-Partner FAQs instead, and `how_it_works` is returned empty (no
     *   partner-specific steps exist).
     * - `limit` — Number of FAQs to return (default: 10, max: 50)
     * - `offset` — Number of FAQs to skip (default: 0)
     *
     * **Response Structure:**
     * - `how_it_works` — Array of steps with title and description (always empty when type=partner)
     * - `topics_and_faqs` — Array of topics, each containing FAQs
     * - `pagination` — Pagination metadata (total, limit, offset, current_page, last_page, has_more)
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:help_support,partner'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $type = $validated['type'] ?? 'help_support';
        $search = $validated['search'] ?? null;
        $limit = isset($validated['limit']) ? (int) $validated['limit'] : null;
        $offset = isset($validated['offset']) ? (int) $validated['offset'] : null;

        $data = $this->helpSupportService->getHelpSupportData(
            limit: $limit,
            offset: $offset,
            search: $search,
            type: $type,
        );

        if (isset($data['paginator'])) {
            return $this->paginatedResponse(
                $data['paginator'],
                $data['topics_and_faqs'],
                'Help & support content fetched successfully',
                ['how_it_works' => $data['how_it_works']],
                $offset ?? 0
            );
        }

        return $this->successResponse([
            'how_it_works' => $data['how_it_works'],
            'topics_and_faqs' => $data['topics_and_faqs'],
        ], 'Help & support content fetched successfully');
    }
}
