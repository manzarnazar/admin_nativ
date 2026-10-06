<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert existing meter values to km
        DB::table('nearby_place_categories')->update([
            'radius' => DB::raw('ROUND(radius / 1000)'),
        ]);

        Schema::table('nearby_place_categories', function (Blueprint $table): void {
            $table->unsignedSmallInteger('radius')->default(5)->change();
        });
    }

    public function down(): void
    {
        Schema::table('nearby_place_categories', function (Blueprint $table): void {
            $table->unsignedInteger('radius')->default(5000)->change();
        });

        // Convert km values back to meters
        DB::table('nearby_place_categories')->update([
            'radius' => DB::raw('radius * 1000'),
        ]);
    }
};
