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
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('email');                // Who the OTP was sent to
            $table->string('otp');                  // The 6-digit code
            $table->string('purpose');              // "registration" or "password_reset"
            $table->string('verification_token')->nullable(); // Temp token issued after successful verification
            $table->boolean('is_verified')->default(false);   // Has this OTP been verified?
            $table->timestamp('expires_at');        // OTP expiry time (5 minutes)
            $table->timestamps();

            $table->index(['email', 'purpose']);    // Quick lookup: latest OTP for this email + purpose
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
