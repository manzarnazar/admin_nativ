<?php

namespace App\Services\Api;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Models\Booking;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    /**
     * Get reviews for a property or room type with aggregate stats.
     *
     * @param  array{property_slug?: ?string, room_type_slug?: ?string, room_type_id?: mixed, limit?: mixed, offset?: mixed}  $filters
     * @return array<string, mixed>
     */
    public function getReviews(array $filters): array
    {
        $property = null;
        $propertyRoom = null;
        $roomType = null;

        if (! empty($filters['property_slug'])) {
            $property = Property::query()
                ->select(['id', 'name', 'slug', 'street_address', 'zip_code', 'ref_city_id', 'ref_state_id'])
                ->with(['primaryImages:id,property_id,image_path', 'refCity:id,name', 'refState:id,name'])
                ->where('slug', $filters['property_slug'])
                ->first();

            if (! $property) {
                throw ValidationException::withMessages([
                    'property_slug' => 'Property not found.',
                ]);
            }
        }

        if (! empty($filters['room_type_slug'])) {
            $roomType = RoomType::query()
                ->select(['id', 'name', 'slug'])
                ->where('slug', $filters['room_type_slug'])
                ->first();

            if (! $roomType) {
                throw ValidationException::withMessages([
                    'room_type_slug' => 'Room type not found.',
                ]);
            }

            if ($property) {
                $propertyRoom = PropertyRoom::query()
                    ->with('roomType:id,name,slug')
                    ->where('property_id', $property->id)
                    ->where('room_type_id', $roomType->id)
                    ->first();

                if (! $propertyRoom) {
                    throw ValidationException::withMessages([
                        'room_type_slug' => 'The selected room type does not belong to the given property.',
                    ]);
                }
            }
        }

        if (! empty($filters['room_type_id'])) {
            $propertyRoom = PropertyRoom::query()
                ->with('roomType:id,name,slug')
                ->find($filters['room_type_id']);

            if (! $propertyRoom) {
                throw ValidationException::withMessages([
                    'room_type_id' => 'Room type not found.',
                ]);
            }

            $roomType = $propertyRoom->roomType;

            if ($property && $propertyRoom->property_id !== $property->id) {
                throw ValidationException::withMessages([
                    'room_type_id' => 'The selected room type does not belong to the given property.',
                ]);
            }
        }

        $baseQuery = Review::query()
            ->with([
                'user:id,name,avatar',
                'images:id,review_id,image_path,sort_order',
                'propertyRoom.roomType:id,name',
            ])
            ->where('status', ReviewStatus::Published->value)
            ->where('is_visible', true);

        if ($property) {
            $baseQuery->where('property_id', $property->id);
        }

        if ($propertyRoom) {
            $baseQuery->where('property_room_id', $propertyRoom->id);
        } elseif ($roomType) {
            $baseQuery->whereHas('propertyRoom', function ($query) use ($roomType) {
                $query->where('room_type_id', $roomType->id);
            });
        }

        $summaryQuery = clone $baseQuery;
        $distributionQuery = clone $baseQuery;

        $limit = max(1, min((int) ($filters['limit'] ?? 10), 50));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $total = (clone $baseQuery)->count();

        $sort = $filters['sort'] ?? 'newest_first';
        $baseQuery = match ($sort) {
            'oldest_first' => $baseQuery->oldest(),
            'high_low' => $baseQuery->orderByDesc('rating')->latest(),
            'low_high' => $baseQuery->orderBy('rating')->latest(),
            default => $baseQuery->latest(),
        };

        $reviews = $baseQuery
            ->skip($offset)
            ->take($limit)
            ->get();

        // $summary = $summaryQuery
        //     ->selectRaw('COUNT(*) as total_reviews, COALESCE(SUM(rating), 0) as total_ratings, COALESCE(AVG(rating), 0) as average_rating')
        //     ->first();
        $summary = $summaryQuery
            ->selectRaw('COUNT(*) as total_reviews, COUNT(*) as total_ratings, COALESCE(AVG(rating), 0) as average_rating')
            ->first();

        /** @var Collection<int, object{rating: string, count: int}> $distribution */
        $distribution = $distributionQuery
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $ratingBreakdown = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $key = number_format((float) $star, 1, '.', '');
            $ratingBreakdown[] = [
                'rating' => $star,
                'count' => (int) ($distribution[$key] ?? 0),
            ];
        }

        $propertyRating = null;
        if ($property) {
            $propertyStats = Review::query()
                ->where('property_id', $property->id)
                ->where('status', ReviewStatus::Published->value)
                ->where('is_visible', true)
                ->selectRaw('COUNT(*) as total_reviews, COALESCE(AVG(rating), 0) as average_rating')
                ->first();

            $propertyRating = [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'street_address' => implode(', ', array_filter([
                    $property->street_address,
                    $property->refCity?->name,
                    $property->refState?->name,
                    $property->zip_code,
                ])),
                'image' => $property->primaryImages->first()?->image_path
                    ? asset('storage/'.$property->primaryImages->first()->image_path)
                    : null,
                'rating_overview' => [
                    'average_rating' => round((float) ($propertyStats->average_rating ?? 0), 1),
                    'total_reviews' => (int) ($propertyStats->total_reviews ?? 0),
                ],
            ];
        }

        return [
            'property' => $propertyRating,
            'room_type' => $roomType ? [
                'id' => $roomType->id,
                'slug' => $roomType->slug,
                'name' => $roomType->name,
                'property_room_id' => $propertyRoom?->id,
            ] : null,
            'summary' => [
                'total_reviews' => (int) ($summary->total_reviews ?? 0),

                'total_ratings' => (float) ($summary->total_ratings ?? 0),

                'average_rating' => $summary && (int) $summary->total_reviews > 0
                    ? round((float) $summary->average_rating, 1)
                    : 0.0,
                'rating_breakdown' => $ratingBreakdown,
            ],
            'items' => $reviews->map(fn (Review $review) => $this->formatReview($review))->values()->toArray(),
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'current_page' => (int) floor($offset / $limit) + 1,
                'last_page' => max(1, (int) ceil($total / $limit)),
                'has_more' => ($offset + $limit) < $total,
            ],
        ];
    }

    /**
     * Submit a new review.
     */
    public function submitReview(User $user, array $data): Review
    {
        $booking = Booking::query()
            ->where('booking_number', $data['booking_number'])
            ->where('user_id', $user->id)
            ->first();

        if (! $booking) {
            throw ValidationException::withMessages([
                'booking_number' => 'Booking not found or does not belong to you.',
            ]);
        }

        // Only completed bookings can be reviewed
        if ($booking->status !== BookingStatus::Completed) {
            throw ValidationException::withMessages([
                'booking_id' => 'You can only review completed stays.',
            ]);
        }

        // Check if a non-deleted review already exists
        if (Review::where('booking_id', $booking->id)->exists()) {
            throw ValidationException::withMessages([
                'booking_id' => 'You have already reviewed this stay.',
            ]);
        }

        return DB::transaction(function () use ($user, $booking, $data) {
            // If admin previously soft-deleted the review, restore and update it
            // to avoid unique constraint violation on booking_id
            $review = Review::withTrashed()
                ->where('booking_id', $booking->id)
                ->first();

            if ($review) {
                $review->restore();
                $review->update([
                    'user_id' => $user->id,
                    'rating' => $data['rating'],
                    'review' => $data['review'] ?? '',
                    'stayed_nights' => $booking->total_nights,
                    'status' => ReviewStatus::Published,
                    'is_visible' => true,
                ]);
                $review->images()->delete();
            } else {
                $review = Review::create([
                    'booking_id' => $booking->id,
                    'user_id' => $user->id,
                    'property_id' => $booking->property_id,
                    'property_room_id' => $booking->property_room_id,
                    'rating' => $data['rating'],
                    'review' => $data['review'] ?? '',
                    'stayed_nights' => $booking->total_nights,
                    'status' => ReviewStatus::Published,
                    'is_visible' => true,
                ]);
            }

            if (! empty($data['images'])) {
                foreach ($data['images'] as $index => $image) {
                    $path = $image->store('reviews', 'public');

                    ReviewImage::create([
                        'review_id' => $review->id,
                        'image_path' => $path,
                        'sort_order' => $index,
                    ]);
                }
            }

            return $review;
        });
    }

    /**
     * Edit an existing review (only once allowed).
     */
    public function editReview(User $user, array $data): Review
    {
        $booking = Booking::query()
            ->where('booking_number', $data['booking_number'])
            ->where('user_id', $user->id)
            ->first();

        if (! $booking) {
            throw ValidationException::withMessages([
                'booking_number' => 'Booking not found or does not belong to you.',
            ]);
        }

        $review = Review::where('booking_id', $booking->id)->first();

        if (! $review) {
            throw ValidationException::withMessages([
                'booking_number' => 'Review not found for this booking.',
            ]);
        }

        // Check if already edited
        if ($review->is_edited) {
            throw ValidationException::withMessages([
                'booking_number' => 'You can only edit a review once.',
            ]);
        }

        return DB::transaction(function () use ($review, $data) {
            // Update review fields
            $review->update([
                'rating' => $data['rating'],
                'review' => $data['review'] ?? '',
                'is_edited' => true,
                'edited_at' => now(),
            ]);

            // Delete images whose IDs were not passed in existing_image_ids
            $keepIds = ! empty($data['existing_image_ids']) ? $data['existing_image_ids'] : [];

            ReviewImage::where('review_id', $review->id)
                ->when(! empty($keepIds), fn ($q) => $q->whereNotIn('id', $keepIds))
                ->delete();

            // Upload new images
            $currentCount = ReviewImage::where('review_id', $review->id)->count();

            foreach ($data['images'] ?? [] as $index => $image) {
                $path = $image->store('reviews', 'public');

                ReviewImage::create([
                    'review_id' => $review->id,
                    'image_path' => $path,
                    'sort_order' => $currentCount + $index,
                ]);
            }

            return $review->fresh(['booking', 'images']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReview(Review $review): array
    {
        return [
            'id' => $review->id,
            'rating' => (float) $review->rating,
            'review' => $review->review,
            // 'stayed_nights' => (int) ($review->stayed_nights ?? 0),
            'stayed_nights' => (int) ($review->booking?->total_nights ?? 0),
            'date' => $review->created_at->format('M d, Y'),
            'user' => [
                'id' => $review->user?->id,
                'name' => $review->user?->name,
                'avatar' => $review->user?->avatar ? asset('storage/'.$review->user->avatar) : null,
            ],
            'room_type' => [
                'id' => $review->propertyRoom?->roomType?->id,
                'slug' => $review->propertyRoom?->roomType?->slug,
                'name' => $review->propertyRoom?->roomType?->name,
                'property_room_id' => $review->propertyRoom?->id,
            ],
            'images' => $review->images->map(fn (ReviewImage $img) => [
                'id' => $img->id,
                'url' => asset('storage/'.$img->image_path),
            ])->values()->toArray(),
        ];
    }
}
