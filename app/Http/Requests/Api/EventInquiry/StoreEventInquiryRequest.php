<?php

namespace App\Http\Requests\Api\EventInquiry;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /** Slug of the property the inquiry is being made for. */
            'property_slug' => ['required', 'string', 'max:255'],
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'dial_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^([0-9]{7,15})?$/'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
