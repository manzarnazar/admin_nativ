<?php

namespace Database\Seeders;

use App\Filament\Support\PermissionModule;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        PermissionModule::seed();

        $this->command->info('Seeded '.count(PermissionModule::allPermissionNames()).' permissions across '.count(PermissionModule::all()).' modules.');
    }
}
