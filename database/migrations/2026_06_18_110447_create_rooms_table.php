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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('floor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_room_id')->constrained()->cascadeOnDelete();
            $table->string('room_number');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'room_number']);
            $table->index(['property_id', 'status']);
            $table->index('property_room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
