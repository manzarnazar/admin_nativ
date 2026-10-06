<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_sections', function (Blueprint $table) {
            $table->dropForeign(['property_type_id']);
            $table->dropColumn('property_type_id');
            $table->json('property_type_ids')->nullable()->after('target_city_id');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_sections', function (Blueprint $table) {
            $table->dropColumn('property_type_ids');
            $table->foreignId('property_type_id')->nullable()->after('target_city_id')->constrained('property_types')->nullOnDelete();
        });
    }
};
