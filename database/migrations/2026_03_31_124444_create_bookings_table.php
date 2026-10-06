<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number')->unique();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();

            // Stay details
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedInteger('total_nights');
            $table->unsignedInteger('adults')->default(1);
            $table->unsignedInteger('children')->default(0);
            $table->boolean('has_pets')->default(false);
            $table->unsignedInteger('booked_rooms')->default(1);
            $table->string('room_number')->nullable();

            // Financial
            $table->decimal('base_amount', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);

            // Booking metadata
            $table->string('booking_source')->default('admin');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('status')->default('confirmed');
            $table->timestamp('cancelled_at')->nullable();

            // Future: coupon support
            $table->unsignedBigInteger('coupon_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'check_in', 'check_out']);
            $table->index('user_id');
            $table->index('status');
            $table->index('booking_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
