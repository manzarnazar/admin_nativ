<?php

namespace App\Http\Requests\Api\Review;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $maxImages = config('services.reviews.max_images', 5);

        return [
            'booking_number' => ['required', 'string', 'exists:bookings,booking_number'],
            'rating' => ['required', 'numeric', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:1000'],
            'existing_image_ids' => ['nullable', 'array'],
            'existing_image_ids.*' => ['integer'],
            'images' => ['nullable', 'array', 'max:'.$maxImages],
            'images.*' => ['image', 'mimes:jpeg,png,jpg', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxImages = config('services.reviews.max_images', 5);

        return [
            'booking_number.required' => 'Booking number is required.',
            'booking_number.exists' => 'The booking number you entered could not be found.',
            'rating.required' => 'Please select a rating.',
            'rating.min' => 'Rating must be at least 1 star.',
            'rating.max' => 'Rating cannot exceed 5 stars.',
            'review.max' => 'Your review cannot exceed 1000 characters.',
            'images.max' => "You can upload a maximum of {$maxImages} photos.",
            'images.*.image' => 'Each photo must be a valid image file.',
            'images.*.mimes' => 'Each photo must be a JPEG or PNG image.',
            'images.*.max' => 'Each photo must not exceed 5 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'booking_number' => 'booking number',
            'images.*' => 'photo',
        ];
    }
}
