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
        Schema::table('processed_webhook_events', function (Blueprint $table) {
            // Nullable: a gateway can have multiple active settings rows (one per country)
            // sharing the same webhook URL — without this, "last webhook received" for one
            // country's settings was actually reflecting whichever country's traffic arrived
            // most recently. nullOnDelete rather than cascade so historical webhook events
            // survive a settings row being deleted/reconfigured.
            $table->foreignId('payment_gateway_setting_id')
                ->nullable()
                ->after('gateway')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processed_webhook_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_gateway_setting_id');
        });
    }
};
