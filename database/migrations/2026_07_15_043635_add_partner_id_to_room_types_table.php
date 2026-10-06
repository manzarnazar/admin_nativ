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
        Schema::table('room_types', function (Blueprint $table): void {
            $table->foreignId('partner_id')
                ->nullable()
                ->after('id')
                ->constrained('partners')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table): void {
            $table->dropForeign(['partner_id']);
            $table->dropColumn('partner_id');
        });
    }
};
