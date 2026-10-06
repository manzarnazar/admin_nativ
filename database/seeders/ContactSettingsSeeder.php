<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class ContactSettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set('contact_address', 'Office No 262, Time Square Empire, Mandvi Road, Uma Nagar, Bhuj, Mirjapar Part, Gujarat 370040');
        Setting::set('contact_email', 'wrteam@gmail.com');
        Setting::set('contact_phone', '+91-8527419635');
        Setting::set('map_provider', 'openstreetmap');
    }
}
