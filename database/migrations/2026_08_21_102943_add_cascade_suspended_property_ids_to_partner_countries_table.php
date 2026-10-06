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
        Schema::table('partner_countries', function (Blueprint $table) {
            $table->json('cascade_suspended_property_ids')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_countries', function (Blueprint $table) {
            $table->dropColumn('cascade_suspended_property_ids');
        });
    }
};
