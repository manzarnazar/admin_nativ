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
        if (! Schema::hasTable('notification_user')) {
            Schema::create('notification_user', function (Blueprint $table) {
                $table->id();
                $table->uuid('notification_id');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                // Indexing for high performance unread count and fetching
                $table->index(['user_id', 'read_at']);

                $table->foreign('notification_id')->references('id')->on('notifications')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('notification_user')) {
            Schema::dropIfExists('notification_user');
        }
    }
};
