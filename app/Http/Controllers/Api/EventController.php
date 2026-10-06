<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    public function __construct(
        private EventService $eventService,
    ) {}

    /**
     * List all active events.
     *
     * Returns events with title, slug, description, image URL, and features.
     * Used to display event cards on the homepage or listing pages.
     */
    public function index(): JsonResponse
    {
        $events = $this->eventService->getActiveEventsForApi();

        return $this->successResponse(['items' => $events], 'Events fetched successfully');
    }
}
