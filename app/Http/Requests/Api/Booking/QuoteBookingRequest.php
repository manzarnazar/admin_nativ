<?php

namespace App\Http\Requests\Api\Booking;

use Illuminate\Foundation\Http\FormRequest;

class QuoteBookingRequest extends FormRequest
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
        return [
            /** The ID of the room type to quote. Example: 2 */
            'property_room_id' => ['required', 'integer', 'exists:property_rooms,id'],

            /** The check-in date (Y-m-d). Example: 2026-08-24 */
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],

            /** The check-out date (Y-m-d). Example: 2026-08-26 */
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],

            /** Number of adults. Default: 1. */
            'adults' => ['nullable', 'integer', 'min:1'],

            /** Number of children (below 12). Default: 0. */
            'children' => ['nullable', 'integer', 'min:0'],

            /** Number of rooms requested. Default: 1. */
            'rooms' => ['nullable', 'integer', 'min:1'],

            /** Whether the guest is bringing pets. */
            'has_pets' => ['nullable', 'boolean'],

            /** Optional coupon code to apply. */
            'coupon_code' => ['nullable', 'string', 'max:50'],

            /** Skip auto-apply promo codes. Default: false. */
            'skip_auto_promo' => ['nullable', 'boolean'],
        ];
    }
}
