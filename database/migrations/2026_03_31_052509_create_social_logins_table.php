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
        Schema::create('social_logins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');         // google, apple
            $table->string('provider_id');      // Google/Apple user ID
            $table->string('provider_email')->nullable(); // Email received from provider
            $table->timestamps();

            $table->unique(['provider', 'provider_id']); // One Google/Apple account can only link to one user
            $table->index(['user_id', 'provider']);       // Quick lookup: which providers does this user have?
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_logins');
    }
};
