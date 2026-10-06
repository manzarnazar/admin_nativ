<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EventInquiry\StoreEventInquiryRequest;
use App\Services\EventInquiryService;
use Illuminate\Http\JsonResponse;

class EventInquiryController extends Controller
{
    public function __construct(
        private EventInquiryService $eventInquiryService,
    ) {}

    /**
     * Submit a new event inquiry.
     *
     * The user selects a property and an event, provides contact details and a message.
     * All admins and staff are notified via database notification.
     */
    public function store(StoreEventInquiryRequest $request): JsonResponse
    {
        $inquiry = $this->eventInquiryService->submitInquiry($request->validated());

        return $this->successResponse([
            'inquiry_number' => $inquiry->inquiry_number,
            'status' => $inquiry->status->value,
        ], 'Your inquiry has been submitted successfully.', 201);
    }
}
