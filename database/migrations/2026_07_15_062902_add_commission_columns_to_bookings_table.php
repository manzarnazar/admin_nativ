<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->decimal('commission_rate', 5, 2)->nullable()->after('total_amount');
            $table->decimal('commission_amount', 10, 2)->nullable()->after('commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['commission_rate', 'commission_amount']);
        });
    }
};
