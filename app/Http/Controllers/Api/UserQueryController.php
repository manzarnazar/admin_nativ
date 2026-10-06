<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmitUserQueryRequest;
use App\Services\UserQueryService;
use Illuminate\Http\JsonResponse;

class UserQueryController extends Controller
{
    public function __construct(
        private UserQueryService $userQueryService,
    ) {}

    /**
     * Submit a new user query.
     *
     * @authenticated
     */
    public function store(SubmitUserQueryRequest $request): JsonResponse
    {
        $user = $request->user();

        $query = $this->userQueryService->submitQuery([
            'user_id' => $user?->id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'dial_code' => $user?->dial_code,
            'phone' => $user?->phone,
            'subject' => $request->input('subject'),
            'message' => $request->input('message'),
        ]);

        return $this->successResponse([
            'query_number' => $query->query_number,
            'status' => $query->status->value,
        ], 'Your query has been submitted successfully.', 201);
    }
}
