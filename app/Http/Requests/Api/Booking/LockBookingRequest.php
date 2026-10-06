<?php

namespace App\Http\Requests\Api\Booking;

use Illuminate\Foundation\Http\FormRequest;

class LockBookingRequest extends FormRequest
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
            /** The ID of the room type to lock. Example: 2 */
            'property_room_id' => ['required', 'integer', 'exists:property_rooms,id'],

            /** The check-in date (Y-m-d). Example: 2026-08-24 */
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],

            /** The check-out date (Y-m-d). Example: 2026-08-26 */
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],

            /** Number of rooms to reserve. */
            'rooms' => ['required', 'integer', 'min:1'],

            /** Current platform making the request: android, ios, or web. Carried through to the booking created from this lock. */
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
        ];
    }
}
