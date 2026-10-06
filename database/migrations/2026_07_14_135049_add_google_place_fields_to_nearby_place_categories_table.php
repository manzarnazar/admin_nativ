<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nearby_place_categories', function (Blueprint $table): void {
            $table->string('google_place_type')->nullable()->after('name');
            $table->unsignedInteger('radius')->default(5000)->after('google_place_type');
            $table->unsignedSmallInteger('total_places')->default(10)->after('radius');
        });
    }

    public function down(): void
    {
        Schema::table('nearby_place_categories', function (Blueprint $table): void {
            $table->dropColumn(['google_place_type', 'radius', 'total_places']);
        });
    }
};
