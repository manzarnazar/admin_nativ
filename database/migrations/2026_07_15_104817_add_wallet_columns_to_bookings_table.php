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
        Schema::table('bookings', function (Blueprint $table) {
            // Commission snapshot (rate already added in Phase 8; add rule ref + source)
            $table->foreignId('commission_rate_id')
                ->nullable()
                ->after('commission_amount')
                ->constrained('commission_rates')
                ->nullOnDelete();
            $table->string('commission_source')->nullable()->after('commission_rate_id');

            // Wallet credit tracking
            $table->timestamp('wallet_credited_at')->nullable()->after('commission_source');

            // Refund formula audit trail
            $table->string('formula_version')->nullable()->after('wallet_credited_at');
            $table->json('refund_inputs')->nullable()->after('formula_version');

            // Booking-time snapshots for historic reporting
            $table->foreignId('booked_country_id')
                ->nullable()
                ->after('refund_inputs')
                ->constrained('countries')
                ->nullOnDelete();
            $table->foreignId('booked_property_type_id')
                ->nullable()
                ->after('booked_country_id')
                ->constrained('property_types')
                ->nullOnDelete();
            $table->json('cancellation_policy_snapshot')->nullable()->after('booked_property_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commission_rate_id');
            $table->dropColumn(['commission_source', 'wallet_credited_at', 'formula_version', 'refund_inputs']);
            $table->dropConstrainedForeignId('booked_country_id');
            $table->dropConstrainedForeignId('booked_property_type_id');
            $table->dropColumn('cancellation_policy_snapshot');
        });
    }
};
