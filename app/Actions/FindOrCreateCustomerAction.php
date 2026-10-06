<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class FindOrCreateCustomerAction
{
    /**
     * Find existing customer by phone (or email), or create a new one.
     *
     * When creating a new customer, a plaintext password is generated and returned
     * alongside the user so the caller can email it. For existing-customer lookups,
     * plain_password is null.
     *
     * @return array{user: User, plain_password: ?string}
     */
    public function handle(string $phone, ?string $name = null, ?string $email = null, ?string $dialCode = null): array
    {
        $user = User::query()
            ->where('phone', $phone)
            ->where('dial_code', $dialCode)
            ->first();

        if ($user) {
            return ['user' => $user, 'plain_password' => null];
        }

        if ($email) {
            $user = User::query()->where('email', $email)->first();

            if ($user) {
                return ['user' => $user, 'plain_password' => null];
            }
        }

        $plainPassword = $this->generatePassword($name ?? 'Guest');

        $user = User::query()->create([
            'name' => $name ?? 'Guest',
            'phone' => $phone,
            'dial_code' => $dialCode,
            'email' => $email,
            'password' => Hash::make($plainPassword),
            'role' => UserRole::Customer,
            'status' => 'active',
            'auth_provider' => 'admin',
        ]);

        return ['user' => $user, 'plain_password' => $plainPassword];
    }

    /**
     * Generate a password for an admin-created customer.
     *
     * Format: first 4 alphabetic chars of name + 1 special char + 4 random digits.
     * If name has fewer than 4 alphabetic chars, more digits are added to reach
     * a minimum of 9 characters total.
     */
    private function generatePassword(string $name): string
    {
        $alphaName = preg_replace('/[^A-Za-z]/', '', $name) ?? '';

        $namePart = mb_substr($alphaName, 0, 4);
        $namePartLen = mb_strlen($namePart);

        $digitCount = max(4, 9 - $namePartLen - 1);

        $specials = ['!', '@', '#', '$', '%', '&', '*'];
        $special = $specials[random_int(0, count($specials) - 1)];

        $digits = '';
        for ($i = 0; $i < $digitCount; $i++) {
            $digits .= random_int(0, 9);
        }

        return $namePart.$special.$digits;
    }
}
