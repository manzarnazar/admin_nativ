<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'identifier' => ['required', 'string', 'max:255'],
            'dial_code' => ['nullable', 'string', 'max:10'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/'],
            'verification_token' => ['nullable', 'string'],
            'firebase_id_token' => ['nullable', 'string'],
        ];
    }

    /**
     * Additional validation after the basic rules pass.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if (! $this->filled('verification_token') && ! $this->filled('firebase_id_token')) {
                    $validator->errors()->add('verification_token', 'Either verification token or Firebase ID token is required.');
                }
            },
        ];
    }
}
