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
        Schema::table('properties', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('partner_id');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
            $table->string('pre_suspension_status')->nullable()->after('suspension_reason');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'suspension_reason', 'pre_suspension_status']);
        });
    }
};
