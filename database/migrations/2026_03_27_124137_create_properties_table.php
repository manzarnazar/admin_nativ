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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_type_id')->nullable()->constrained()->nullOnDelete();

            // General Information
            $table->string('name');
            $table->text('description')->nullable();

            // Contact Info
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('landline', 50)->nullable();

            // Address
            $table->text('street_address')->nullable();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('zip_code', 20)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Bank Details
            $table->string('bank_account_holder')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_code', 100)->nullable();

            // Property Rules
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();

            // Payment Configuration
            $table->boolean('pay_at_property')->default(false);
            $table->decimal('advance_percentage', 5, 2)->nullable();

            // Wizard Progress
            $table->tinyInteger('completed_step')->default(0);
            $table->string('status')->default('draft');

            $table->timestamps();
            $table->softDeletes();

            $table->index('country_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
