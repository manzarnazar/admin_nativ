<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cancellation_policies', function (Blueprint $table): void {
            $table->foreignId('partner_id')->nullable()->after('country_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cancellation_policies', function (Blueprint $table): void {
            $table->dropForeign(['partner_id']);
            $table->dropColumn('partner_id');
        });
    }
};
