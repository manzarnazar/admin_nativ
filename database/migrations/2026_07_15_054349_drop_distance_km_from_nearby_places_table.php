<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nearby_places', function (Blueprint $table): void {
            $table->dropColumn('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('nearby_places', function (Blueprint $table): void {
            $table->decimal('distance_km', 8, 2)->nullable()->after('longitude');
        });
    }
};
