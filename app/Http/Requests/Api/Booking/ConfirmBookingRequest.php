<?php

namespace App\Http\Requests\Api\Booking;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmBookingRequest extends FormRequest
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
            /** The ID returned by the /lock API. */
            'lock_id' => ['required', 'integer', 'exists:inventory_locks,id'],

            /** Payment method. Required for paid bookings (pay_at_property here; pay_online uses the gateway endpoint). Optional and ignored when the booking total is 0. */
            'payment_method' => ['nullable', 'string', 'in:pay_at_property,pay_online'],

            /** Final guest count (adults). */
            'adults' => ['required', 'integer', 'min:1'],

            /** Final guest count (children). */
            'children' => ['required', 'integer', 'min:0'],

            /** Final pet preference. */
            'has_pets' => ['required', 'boolean'],

            /** Final coupon check (optional). */
            'coupon_code' => ['nullable', 'string'],

            /** Full Name of the guest staying. */
            'guest_name' => ['required', 'string', 'max:255'],

            /** Contact Email for the stay. */
            'guest_email' => ['required', 'email', 'max:255'],

            /** Contact Phone for the stay. */
            'guest_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9]{7,15}$/'],

            /** Telephone prefix (e.g. +91). */
            'guest_dial_code' => ['nullable', 'string', 'max:10'],

            /** Accepted but no longer used — booking_source is now derived from the lock's platform (captured at /bookings/lock), not this field. Kept for backward compatibility with clients still sending it here. */
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
        ];
    }
}
