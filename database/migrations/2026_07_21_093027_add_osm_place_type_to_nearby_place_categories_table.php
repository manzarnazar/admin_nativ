<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nearby_place_categories', function (Blueprint $table): void {
            $table->string('osm_place_type')->nullable()->after('google_place_type');
        });

        // Backfill osm_place_type for any category whose google_place_type
        // already has a known OSM equivalent.
        $mapping = config('maps.google_to_osm', []);

        foreach ($mapping as $googleType => $osmType) {
            DB::table('nearby_place_categories')
                ->where('google_place_type', $googleType)
                ->whereNull('osm_place_type')
                ->update(['osm_place_type' => $osmType]);
        }
    }

    public function down(): void
    {
        Schema::table('nearby_place_categories', function (Blueprint $table): void {
            $table->dropColumn('osm_place_type');
        });
    }
};
