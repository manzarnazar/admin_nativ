<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'password' => ['required', 'string'],
            'fcm_token' => ['nullable', 'string'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
        ];
    }
}
