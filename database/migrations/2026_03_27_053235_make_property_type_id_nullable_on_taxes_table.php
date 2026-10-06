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
        Schema::table('taxes', function (Blueprint $table) {
            $table->dropForeign(['property_type_id']);
        });

        Schema::table('taxes', function (Blueprint $table) {
            $table->unsignedBigInteger('property_type_id')->nullable()->change();
            $table->foreign('property_type_id')->references('id')->on('property_types')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->dropForeign(['property_type_id']);
        });

        Schema::table('taxes', function (Blueprint $table) {
            $table->unsignedBigInteger('property_type_id')->nullable(false)->change();
            $table->foreign('property_type_id')->references('id')->on('property_types')->cascadeOnDelete();
        });
    }
};
