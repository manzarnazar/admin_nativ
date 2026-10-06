<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterCompleteRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'dial_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^([0-9]{7,15})?$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/'],
            'referral_code' => ['nullable', 'string', 'max:20', Rule::exists('users', 'referral_code')],
            'verification_token' => ['nullable', 'string'],
            'firebase_id_token' => ['nullable', 'string'],
            'fcm_token' => ['nullable', 'string'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
        ];
    }

    /**
     * Additional validation after the basic rules pass.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if (! $this->filled('email') && ! $this->filled('phone')) {
                    $validator->errors()->add('email', 'Either email or phone is required.');
                }

                if ($this->filled('phone') && ! $this->filled('dial_code')) {
                    $validator->errors()->add('dial_code', 'Dial code is required when phone is provided.');
                }

                if (! $this->filled('verification_token') && ! $this->filled('firebase_id_token')) {
                    $validator->errors()->add('verification_token', 'Verification token or Firebase ID token is required.');
                }
            },
        ];
    }
}
