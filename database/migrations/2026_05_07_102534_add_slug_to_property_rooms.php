<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('property_rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('property_rooms', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('room_type_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('property_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('property_rooms', 'slug')) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            }
        });
    }
};
