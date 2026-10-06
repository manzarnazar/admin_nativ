<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class Controller
{
    protected function successResponse(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'error' => false,
            'message' => $message,
            'data' => $data,
            'code' => $code,
        ], $code, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function errorResponse(string $message = 'Something went wrong', int $code = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'error' => true,
            'message' => $message,
            'data' => $data,
            'code' => $code,
        ], $code);
    }

    /**
     * Paginated success response with consistent format.
     *
     * @param  array<int, mixed>  $items
     * @param  array<string, mixed>  $additionalData
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator, array $items, string $message = 'Data fetched successfully', array $additionalData = [], int $offset = 0): JsonResponse
    {
        $limit = $paginator->perPage();

        return $this->successResponse(array_merge([
            'items' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'limit' => $limit,
                'offset' => $offset,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], $additionalData), $message);
    }
}
