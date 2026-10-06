<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/'],
        ];
    }

    /**
     * Additional validation after the basic rules pass.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if (! $this->user()) {
                    return;
                }

                // Check if new password is same as old password
                if (password_verify($this->input('password'), $this->user()->password)) {
                    $validator->errors()->add('password', 'You cannot use your old password. Please choose a different password.');
                }
            },
        ];
    }
}
