<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('type');                          // push | email | both
            $table->string('title');
            $table->text('body');
            $table->string('redirect_url')->nullable();
            $table->string('image')->nullable();             // stored in public disk
            $table->string('audience');                      // all | city_based
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->unsignedInteger('sent_to')->nullable();
            $table->decimal('open_rate', 5, 2)->nullable();  // percentage 0–100
            $table->unsignedInteger('clicks')->nullable();
            $table->string('status')->default('scheduled'); // scheduled | sent
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['country_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_messages');
    }
};
