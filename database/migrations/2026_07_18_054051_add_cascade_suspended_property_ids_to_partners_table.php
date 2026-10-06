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
        Schema::table('partners', function (Blueprint $table) {
            // IDs of properties this specific suspension cascade flipped to
            // Suspended (only ones that were Active at the time) — read back
            // on unsuspend to restore precisely those, leaving anything the
            // partner had already paused (Inactive) or admin had already
            // suspended independently completely untouched either way.
            $table->json('cascade_suspended_property_ids')->nullable()->after('suspended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('cascade_suspended_property_ids');
        });
    }
};
