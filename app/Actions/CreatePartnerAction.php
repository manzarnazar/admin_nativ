<?php

namespace App\Actions;

use App\Enums\PartnerVerificationStatus;
use App\Enums\UserRole;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePartnerAction
{
    public function handle(array $data): Partner
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'dial_code' => $data['dial_code'] ?? null,
                'password' => $data['password'],
                'date_of_birth' => $data['dob'] ?? null,
                'gender' => $data['gender'] ?? null,
                'role' => UserRole::Partner,
                'auth_provider' => $data['auth_provider'] ?? null,
                'email_verified_at' => ! empty($data['email']) ? now() : null,
                'phone_verified_at' => ! empty($data['phone']) && ! empty($data['firebase_id_token']) ? now() : null,
            ]);

            $partner = Partner::create([
                'user_id' => $user->id,
                'property_type_id' => $data['property_type_id'] ?? null,
                'verification_status' => PartnerVerificationStatus::Pending,
                'address' => $data['address'] ?? null,
                'country' => $data['country'] ?? null,
                'state_province' => $data['state'] ?? null,
                'city' => $data['city'] ?? null,
                'zip_code' => $data['zip_code'] ?? null,
            ]);

            if (! empty($data['country_id'])) {
                $partner->countries()->attach($data['country_id']);
            }

            return $partner;
        });
    }
}
