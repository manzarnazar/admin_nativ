<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_room_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_rooms');
            $table->unsignedInteger('booked_rooms')->default(0);
            $table->unsignedInteger('locked_rooms')->default(0);
            $table->timestamps();

            $table->unique(['property_room_id', 'date']);
            $table->index(['property_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_inventory');
    }
};
