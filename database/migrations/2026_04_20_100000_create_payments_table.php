<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Gateway Information
            $table->string('gateway_type'); // razorpay, stripe, flutterwave
            $table->string('gateway_payment_id')->nullable(); // Gateway's payment ID
            $table->string('gateway_order_id')->nullable(); // Gateway's order ID
            $table->string('gateway_event_id')->nullable(); // Unique webhook event ID for idempotency

            // Payment Details
            $table->decimal('amount', 12, 2); // Original amount in property currency
            $table->string('currency', 3); // Property currency (INR, AED, etc)
            $table->decimal('converted_amount', 12, 2)->nullable(); // Optional: for analytics
            $table->string('payment_type')->default('full'); // full or partial
            $table->decimal('remaining_amount', 12, 2)->nullable(); // Balance for partial payments

            // Status
            $table->string('status')->default('pending');

            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('processed_at')->nullable(); // Idempotency flag

            // Gateway Response Data
            $table->json('gateway_response')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('booking_id');
            $table->index('user_id');
            $table->index('gateway_order_id');
            $table->index('status');
            $table->index('created_at');

            // Unique constraints to protect against duplicate webhooks/inserts
            $table->unique(['gateway_type', 'gateway_payment_id'], 'payments_gateway_type_payment_id_unique');
            $table->unique(['gateway_type', 'gateway_event_id'], 'payments_gateway_type_event_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
