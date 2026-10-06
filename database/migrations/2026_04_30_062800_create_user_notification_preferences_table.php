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
        if (! Schema::hasTable('user_notification_preferences')) {
            Schema::create('user_notification_preferences', function (Blueprint $user) {
                $user->id();
                $user->foreignId('user_id')->constrained()->cascadeOnDelete();
                $user->string('category');
                $user->boolean('is_enabled')->default(true);
                $user->timestamps();

                $user->unique(['user_id', 'category']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::dropIfExists('user_notification_preferences');
    }
};
