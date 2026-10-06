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
        Schema::create('cancellation_policy_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cancellation_policy_id')->constrained()->cascadeOnDelete();
            $table->integer('days_before_checkin');
            $table->integer('refund_percentage');
            $table->timestamps();

            $table->unique(['cancellation_policy_id', 'days_before_checkin'], 'c_policy_days_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cancellation_policy_rules');
    }
};
