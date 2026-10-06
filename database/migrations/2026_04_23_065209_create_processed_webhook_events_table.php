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
        Schema::create('processed_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway'); // razorpay, stripe, flutterwave
            $table->string('event_id'); // unique event id from gateway
            $table->string('event_type')->nullable(); // payment.captured, refund.processed, etc.
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->unique(['gateway', 'event_id']);
            $table->index('gateway');
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_webhook_events');
    }
};
