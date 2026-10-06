<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SocialLoginRequest extends FormRequest
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
            'provider' => ['required', 'string', 'in:google,apple'],
            'id_token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'fcm_token' => ['nullable', 'string'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
        ];
    }
}
