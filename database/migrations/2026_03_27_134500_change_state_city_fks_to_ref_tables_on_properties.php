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
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['state_id']);
            $table->dropForeign(['city_id']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['state_id', 'city_id']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedMediumInteger('ref_state_id')->nullable()->after('street_address');
            $table->unsignedMediumInteger('ref_city_id')->nullable()->after('ref_state_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['ref_state_id', 'ref_city_id']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
