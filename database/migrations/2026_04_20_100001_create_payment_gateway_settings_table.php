<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_type'); // razorpay, stripe, flutterwave
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();

            // Gateway Credentials (will be encrypted via model casts)
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->text('public_key')->nullable();

            // Gateway Configuration
            $table->boolean('is_active')->default(true);
            $table->string('mode')->default('test'); // test or live

            // Additional Settings
            $table->json('settings')->nullable();

            $table->timestamps();

            $table->unique(['gateway_type', 'country_id']);
            $table->index('gateway_type');
            $table->index('country_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
