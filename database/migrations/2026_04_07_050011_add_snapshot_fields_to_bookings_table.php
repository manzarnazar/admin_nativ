<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('price_per_night', 10, 2)->nullable()->after('room_number');
            $table->string('currency_code', 3)->nullable()->after('price_per_night');
            $table->string('currency_symbol', 10)->nullable()->after('currency_code');
            $table->json('tax_details')->nullable()->after('currency_symbol');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['price_per_night', 'currency_code', 'currency_symbol', 'tax_details']);
        });
    }
};
