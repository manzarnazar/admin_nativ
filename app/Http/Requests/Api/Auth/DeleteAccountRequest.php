<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
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
            'password' => ['nullable', 'string'],
            'provider' => ['nullable', 'string', 'in:google,apple'],
        ];
    }

    /**
     * Additional validation after the basic rules pass.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if (! $this->filled('password') && ! $this->filled('provider')) {
                    $validator->errors()->add('password', 'Either password or provider is required.');
                }
            },
        ];
    }
}
