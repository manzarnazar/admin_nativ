<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_auto_apply')->default(false);
            $table->string('discount_type')->default('percentage'); // percentage | fixed
            $table->decimal('discount_value', 8, 2);
            $table->decimal('max_discount_cap', 12, 2)->nullable();
            $table->decimal('min_booking_amount', 12, 2)->nullable();
            $table->boolean('is_first_booking_only')->default(false);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('usage_limit')->default(0);
            $table->unsignedInteger('used_count')->default(0);
            $table->string('customer_segment')->default('all'); // new | returning | all
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
