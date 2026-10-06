<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Review\StoreReviewRequest;
use App\Http\Requests\Api\Review\UpdateReviewRequest;
use App\Models\Review;
use App\Services\Api\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService
    ) {}

    /**
     * Get reviews for a property or room type.
     *
     * - If `property_slug` is passed, returns all reviews for that property.
     * - If `room_type_slug` is passed, returns reviews for that room type within the property.
     * - If `room_type_id` is passed, returns reviews for that property room record.
     * - If both property and room type are passed, the room type is validated against the property.
     *
     * Sort options: newest_first, oldest_first, high_low (highest rating first), low_high (lowest rating first)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'property_slug' => ['nullable', 'string'],
            'room_type_slug' => ['nullable', 'string'],
            'room_type_id' => ['nullable', 'integer', 'exists:property_rooms,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'string', 'in:newest_first,oldest_first,high_low,low_high'],
        ]);

        if (! $request->filled('property_slug') && ! $request->filled('room_type_slug') && ! $request->filled('room_type_id')) {
            return $this->errorResponse(
                'Please provide property_slug, room_type_slug, or room_type_id.',
                422,
            );
        }

        $result = $this->reviewService->getReviews($request->only([
            'property_slug',
            'room_type_slug',
            'room_type_id',
            'limit',
            'offset',
            'sort',
        ]));

        return $this->successResponse($result, 'Reviews fetched successfully');
    }

    /**
     * Post a review.
     *
     * Submit a rating and optional text/images for a completed booking.
     *
     * `images` — optional array of photos (send as `multipart/form-data`).
     * Max 5MB per image, JPG/PNG only, up to 5 images by default (configurable via `services.reviews.max_images`).
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $review = $this->reviewService->submitReview($request->user(), $request->all());

        $propertyStats = Review::query()
            ->where('property_id', $review->property_id)
            ->selectRaw('COUNT(*) as total_reviews, COALESCE(AVG(rating), 0) as average_rating')
            ->first();

        return response()->json([
            'error' => false,
            'message' => 'Review published successfully',
            'data' => [
                'booking_number' => $review->booking->booking_number,
                'rating' => $review->rating,
                'review' => $review->review,
                'status' => $review->status,
                'is_edited' => $review->is_edited,
                'edited_at' => $review->edited_at?->toIso8601String(),
                'images' => $review->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => asset('storage/'.$img->image_path),
                ])->values()->toArray(),
                'property_rating' => round((float) ($propertyStats->average_rating ?? 0), 1),
                'property_review_count' => (int) ($propertyStats->total_reviews ?? 0),
            ],
            'code' => 201,
        ], 201);
    }

    /**
     * Update an existing review.
     *
     * Edit a previously submitted review (only once allowed).
     *
     * `images` — optional array of new photos to add (send as `multipart/form-data`).
     * Max 5MB per image, JPG/PNG only, up to 5 images by default (configurable via `services.reviews.max_images`).
     * `existing_image_ids` — IDs of previously uploaded images to keep; omitted ones are removed.
     */
    public function update(UpdateReviewRequest $request): JsonResponse
    {
        Log::info('Review update request', [
            'token' => $request->bearerToken(),
            'data' => $request->except('images'),
            'all_data' => $request->all(),
            'image_count' => is_array($request->input('images')) ? count($request->input('images')) : 0,
        ]);

        $review = $this->reviewService->editReview($request->user(), $request->all());

        $propertyStats = Review::query()
            ->where('property_id', $review->property_id)
            ->selectRaw('COUNT(*) as total_reviews, COALESCE(AVG(rating), 0) as average_rating')
            ->first();

        return response()->json([
            'error' => false,
            'message' => 'Review updated successfully',
            'data' => [
                'booking_number' => $review->booking->booking_number,
                'rating' => $review->rating,
                'review' => $review->review,
                'status' => $review->status,
                'is_edited' => $review->is_edited,
                'edited_at' => $review->edited_at?->toIso8601String(),
                'images' => $review->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => asset('storage/'.$img->image_path),
                ])->values()->toArray(),
                'property_rating' => round((float) ($propertyStats->average_rating ?? 0), 1),
                'property_review_count' => (int) ($propertyStats->total_reviews ?? 0),
            ],
            'code' => 200,
        ], 200);
    }
}
