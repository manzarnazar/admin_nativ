<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Language::query()->firstOrCreate(
            ['code' => 'en'],
            [
                'name' => 'English',
                'is_rtl' => false,
                'status' => true,
                'is_default' => true,
            ]
        );
    }
}
