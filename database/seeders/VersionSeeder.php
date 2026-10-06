<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class VersionSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set('system_version', '2.0.0');
    }
}
