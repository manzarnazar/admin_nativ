<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'profile' => ['nullable', 'file', 'image', 'max:2048'],
            'email' => ['nullable', 'email', 'max:255'],
            'verification_token' => ['nullable', 'string'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'dial_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^([0-9]{7,15})?$/'],
            'firebase_id_token' => ['nullable', 'string'],
            'referral_code' => ['nullable', 'string', 'max:20', Rule::exists('users', 'referral_code')->whereNot('id', $this->user()?->id)],
        ];
    }
}
