<?php

namespace App\Http\Requests\PartnerApi\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^([0-9]{7,15})?$/'],
            'dial_code' => ['nullable', 'string', 'max:10'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'verification_token' => ['nullable', 'string'],
            'firebase_id_token' => ['nullable', 'string'],
            'dob' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
        ];
    }

    /**
     * Additional cross-field validation after basic rules pass.
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                if (! $this->filled('email') && ! $this->filled('phone')) {
                    $validator->errors()->add('email', 'Either email or phone is required.');
                }

                if ($this->filled('phone') && ! $this->filled('dial_code')) {
                    $validator->errors()->add('dial_code', 'Dial code is required when phone is provided.');
                }

                if (! $this->filled('verification_token') && ! $this->filled('firebase_id_token')) {
                    $validator->errors()->add('verification_token', 'A verification token or Firebase ID token is required.');
                }
            },
        ];
    }
}
